<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Services\Translator;
use App\Support\Locales;
use App\Support\PropertyCache;
use Illuminate\Console\Command;
use Throwable;

/**
 * imoveis:traduzir — enche os textos em falta nos outros idiomas, ficha a ficha.
 *
 * O botão do backoffice trata de um imóvel de cada vez e deixa o texto no
 * formulário para ser lido. Este comando é para o atraso acumulado: a carteira
 * inteira de uma vez, quando ninguém escreveu nada no outro idioma.
 *
 * Ao contrário do botão, isto GRAVA — e o que está gravado aparece no site.
 * Por isso avisa antes, conta o que vai fazer, e nunca escreve por cima do que
 * já lá está (salvo com --forcar, que se pede de propósito).
 *
 *   php artisan imoveis:traduzir                      tudo o que falta
 *   php artisan imoveis:traduzir --referencia=MF26-001
 *   php artisan imoveis:traduzir --idioma=en --simular
 */
class PropertiesTranslate extends Command
{
    protected $signature = 'imoveis:traduzir
        {--idioma=* : Idiomas a preencher (por omissão, todos menos o de partida)}
        {--referencia= : Só este imóvel}
        {--forcar : Escreve por cima do que já estiver traduzido}
        {--simular : Diz o que faria, sem gravar nem gastar tradução}';

    protected $description = 'Preenche os textos dos imóveis nos outros idiomas';

    /** Campos de texto de um imóvel, e se o conteúdo é HTML. */
    private const CAMPOS = [
        'title' => false,
        'keywords' => false,
        'seo_description' => false,
        'short_description' => false,
        'description' => false,
        'website_html' => true,
        'brochure_title' => false,
        'brochure_text' => false,
        'email_subject' => false,
        'email_text' => false,
    ];

    public function handle(Translator $tradutor): int
    {
        if (! $tradutor->available()) {
            $this->error('Falta a DEEPL_KEY no .env.');

            return self::FAILURE;
        }

        $origem = Locales::default();
        $idiomas = $this->option('idioma') ?: array_values(array_diff(Locales::enabled(), [$origem]));

        if ($idiomas === []) {
            $this->warn('Só há um idioma ligado — não há nada para traduzir.');

            return self::SUCCESS;
        }

        $imoveis = Property::query()
            ->when($this->option('referencia'), fn ($q, $r) => $q->where('reference', $r))
            ->orderBy('reference')
            ->get();

        if ($imoveis->isEmpty()) {
            $this->warn('Não encontrei imóveis com esse critério.');

            return self::FAILURE;
        }

        $simular = (bool) $this->option('simular');
        $linhas = [];
        $gravados = 0;

        foreach ($imoveis as $imovel) {
            foreach ($idiomas as $idioma) {
                try {
                    $feitos = $this->traduzirImovel($tradutor, $imovel, $origem, $idioma, $simular);
                } catch (Throwable $e) {
                    $this->error("{$imovel->reference} ({$idioma}): {$e->getMessage()}");

                    return self::FAILURE;
                }

                $linhas[] = [
                    $imovel->reference,
                    $idioma,
                    $feitos === [] ? '—' : implode(', ', $feitos),
                ];

                if ($feitos !== []) {
                    $gravados++;
                }
            }
        }

        $this->table(['Imóvel', 'Idioma', 'Campos preenchidos'], $linhas);

        if ($simular) {
            $this->info('Simulação: nada foi gravado nem traduzido.');

            return self::SUCCESS;
        }

        if ($gravados > 0) {
            PropertyCache::flush();
        }

        $this->info($gravados === 0
            ? 'Não havia nada por preencher.'
            : "{$gravados} ficha(s) actualizada(s). O texto já está no site — vale a pena lê-lo.");

        if ($uso = $tradutor->usage()) {
            $this->line("Consumo do plano: {$uso['count']} de {$uso['limit']} caracteres.");
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, string> nomes dos campos preenchidos
     */
    private function traduzirImovel(Translator $tradutor, Property $imovel, string $de, string $para, bool $simular): array
    {
        $traducoes = $imovel->translations ?? [];
        $pedidos = [];

        foreach (self::CAMPOS as $campo => $html) {
            $valor = $traducoes[$de][$campo] ?? null;

            if (Property::isBlankText($valor)) {
                continue;
            }

            if (! $this->option('forcar') && ! Property::isBlankText($traducoes[$para][$campo] ?? null)) {
                continue;
            }

            $lista = is_array($valor);

            foreach ((array) $valor as $i => $texto) {
                if (is_string($texto) && trim($texto) !== '') {
                    $pedidos[($html ? 'h' : 't').'|'.$campo.'|'.$i.'|'.($lista ? 'l' : 's')] = $texto;
                }
            }
        }

        if ($pedidos === [] || $simular) {
            return $pedidos === [] ? [] : array_values(array_unique(array_map(
                fn (string $k) => explode('|', $k)[1],
                array_keys($pedidos)
            )));
        }

        $alvo = $para === 'en' ? (string) config('deepl.target') : mb_strtoupper($para);
        $traduzidos = [];

        foreach (['t' => false, 'h' => true] as $prefixo => $html) {
            $grupo = array_filter(
                $pedidos,
                fn (string $k): bool => str_starts_with($k, $prefixo.'|'),
                ARRAY_FILTER_USE_KEY
            );

            if ($grupo !== []) {
                $traduzidos += $tradutor->translateMany($grupo, $alvo, mb_strtoupper($de), $html);
            }
        }

        foreach ($traduzidos as $k => $texto) {
            [, $campo, $i, $tipo] = explode('|', $k);

            if ($tipo === 'l') {
                $traducoes[$para][$campo][(int) $i] = $texto;
            } else {
                $traducoes[$para][$campo] = $texto;
            }
        }

        foreach ($traducoes[$para] ?? [] as $campo => $valor) {
            if (is_array($valor)) {
                $traducoes[$para][$campo] = array_values($valor);
            }
        }

        $imovel->translations = $traducoes;
        $imovel->save();

        return array_values(array_unique(array_map(
            fn (string $k) => explode('|', $k)[1],
            array_keys($traduzidos)
        )));
    }
}
