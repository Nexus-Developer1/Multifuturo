<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Tradução automática dos textos dos imóveis, pelo DeepL.
 *
 * Serve o botão "Traduzir do português" do backoffice: escreve nos campos do
 * outro idioma e fica por ali. Não publica nada, não corre sozinho e não toca
 * em texto que já esteja escrito — quem decide é quem grava a ficha.
 *
 * Regras da casa que isto respeita:
 *   · a chave vive no .env do servidor e nunca no repositório;
 *   · sem chave, `available()` diz que não e o backoffice esconde os botões;
 *   · uma falha do serviço nunca parte a gravação: devolve-se o erro em texto
 *     e a pessoa continua a poder escrever à mão.
 *
 * O glossário resolve as palavras do ramo que a máquina engana (o "terraço"
 * que saía "garden"). É procurado pelo nome na conta e guardado em cache; se
 * não existir, traduz-se à mesma, só sem ele.
 */
class Translator
{
    /** Quanto tempo se guarda o identificador do glossário antes de o procurar de novo. */
    private const CACHE_GLOSSARIO = 86400;

    public function available(): bool
    {
        return filled(config('deepl.key'));
    }

    /**
     * Traduz uma lista de textos de uma vez (o DeepL aceita vários por pedido).
     * As chaves do array mantêm-se, para quem chama saber o que é o quê.
     *
     * @param  array<string, string>  $textos
     * @return array<string, string>
     *
     * @throws RuntimeException quando o serviço recusa ou não responde
     */
    public function translateMany(array $textos, ?string $para = null, string $de = 'PT', bool $html = false): array
    {
        $textos = array_filter($textos, fn ($t) => is_string($t) && trim($t) !== '');

        if ($textos === []) {
            return [];
        }

        $chaves = array_keys($textos);

        $parametros = [
            'text' => array_values($textos),
            'source_lang' => $de,
            'target_lang' => $para ?? (string) config('deepl.target'),
            // Mantém quebras de linha e espaçamento como estão no original.
            // Booleano, não a palavra "1": em JSON o DeepL recusa o texto com 400.
            'preserve_formatting' => true,
        ];

        if ($html) {
            // Sem isto, as etiquetas do editor iriam para tradução como se fossem texto.
            $parametros['tag_handling'] = 'html';
        }

        if ($glossario = $this->glossaryId()) {
            $parametros['glossary_id'] = $glossario;
        }

        $resposta = $this->request()->asJson()->post('/v2/translate', $parametros);

        if ($resposta->failed()) {
            throw new RuntimeException($this->erro($resposta->status()));
        }

        $traduzidos = array_map(
            fn (array $t) => (string) ($t['text'] ?? ''),
            (array) $resposta->json('translations', [])
        );

        if (count($traduzidos) !== count($chaves)) {
            throw new RuntimeException('O serviço de tradução devolveu um número de textos diferente do pedido.');
        }

        return array_combine($chaves, $traduzidos);
    }

    /** Um texto só. Devolve null quando não há nada para traduzir. */
    public function translate(string $texto, ?string $para = null, string $de = 'PT', bool $html = false): ?string
    {
        return $this->translateMany(['t' => $texto], $para, $de, $html)['t'] ?? null;
    }

    /**
     * Quanto já se gastou este mês.
     *
     * @return array{count: int, limit: int}|null
     */
    public function usage(): ?array
    {
        if (! $this->available()) {
            return null;
        }

        $resposta = $this->request()->post('/v2/usage');

        if ($resposta->failed()) {
            return null;
        }

        return [
            'count' => (int) $resposta->json('character_count', 0),
            'limit' => (int) $resposta->json('character_limit', 0),
        ];
    }

    /**
     * Carrega (ou recarrega) o glossário da configuração para a conta DeepL.
     * O antigo com o mesmo nome é apagado, para não ficarem cópias a acumular.
     *
     * @return array{id: string, terms: int}
     */
    public function pushGlossary(): array
    {
        $nome = (string) config('deepl.glossary.name');
        $termos = (array) config('deepl.glossary.terms');

        if ($termos === []) {
            throw new RuntimeException('Não há termos definidos em config/deepl.php.');
        }

        foreach ($this->glossaries() as $g) {
            if (($g['name'] ?? null) === $nome) {
                $this->request()->delete('/v2/glossaries/'.$g['glossary_id']);
            }
        }

        $resposta = $this->request()->asJson()->post('/v2/glossaries', [
            'name' => $nome,
            'source_lang' => 'pt',
            // O glossário é por idioma base: serve tanto o EN-GB como o EN-US.
            'target_lang' => 'en',
            'entries' => implode("\n", array_map(
                fn ($pt, $en) => $pt."\t".$en,
                array_keys($termos),
                array_values($termos)
            )),
            'entries_format' => 'tsv',
        ]);

        if ($resposta->failed()) {
            throw new RuntimeException($this->erro($resposta->status()));
        }

        Cache::forget($this->chaveCache());

        return [
            'id' => (string) $resposta->json('glossary_id'),
            'terms' => count($termos),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function glossaries(): array
    {
        $resposta = $this->request()->get('/v2/glossaries');

        return $resposta->successful() ? (array) $resposta->json('glossaries', []) : [];
    }

    /** Identificador do glossário desta agência, ou null se ainda não tiver sido carregado. */
    private function glossaryId(): ?string
    {
        $nome = (string) config('deepl.glossary.name');

        if ($nome === '') {
            return null;
        }

        return Cache::remember($this->chaveCache(), self::CACHE_GLOSSARIO, function () use ($nome) {
            foreach ($this->glossaries() as $g) {
                if (($g['name'] ?? null) === $nome) {
                    return (string) $g['glossary_id'];
                }
            }

            // Guarda-se o "não há" também, senão procurava-se a cada tradução.
            return '';
        }) ?: null;
    }

    private function chaveCache(): string
    {
        return 'deepl:glossario:'.config('deepl.glossary.name');
    }

    private function request(): PendingRequest
    {
        $chave = (string) config('deepl.key');

        if ($chave === '') {
            throw new RuntimeException('Falta a chave do serviço de tradução (DEEPL_KEY).');
        }

        return Http::baseUrl($this->endpoint($chave))
            ->withHeader('Authorization', 'DeepL-Auth-Key '.$chave)
            ->timeout((int) config('deepl.timeout'))
            ->retry(2, 400, throw: false);
    }

    /** As contas gratuitas do DeepL têm a chave terminada em ":fx" e outro endereço. */
    private function endpoint(string $chave): string
    {
        return str_ends_with($chave, ':fx')
            ? 'https://api-free.deepl.com'
            : 'https://api.deepl.com';
    }

    /** O que dizer à pessoa quando o serviço recusa — sem a deixar às escuras. */
    private function erro(int $estado): string
    {
        Log::warning('DeepL respondeu '.$estado.' a um pedido de tradução.');

        return match ($estado) {
            403 => 'A chave do serviço de tradução foi recusada. Confirme a DEEPL_KEY.',
            429 => 'O serviço de tradução está a receber pedidos a mais. Tente daqui a pouco.',
            456 => 'Acabaram os caracteres do plano de tradução deste mês.',
            503 => 'O serviço de tradução está indisponível neste momento.',
            default => 'O serviço de tradução respondeu com um erro ('.$estado.').',
        };
    }
}
