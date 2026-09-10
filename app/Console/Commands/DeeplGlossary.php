<?php

namespace App\Console\Commands;

use App\Services\Translator;
use Illuminate\Console\Command;
use Throwable;

/**
 * deepl:glossario — carrega para a conta DeepL as palavras do ramo que a
 * tradução automática engana, a partir de config/deepl.php.
 *
 * Corre-se uma vez, e outra vez sempre que os termos mudarem. O glossário
 * anterior com o mesmo nome é substituído, para não ficarem cópias na conta.
 *
 * Sem isto a tradução funciona à mesma, só que pior: "moradia T3 com terraço"
 * saía como "three-bedroom house with a garden".
 */
class DeeplGlossary extends Command
{
    protected $signature = 'deepl:glossario {--listar : Mostra os glossários que já estão na conta}';

    protected $description = 'Carrega o glossário imobiliário para o serviço de tradução';

    public function handle(Translator $tradutor): int
    {
        if (! $tradutor->available()) {
            $this->error('Falta a DEEPL_KEY no .env — sem chave não há nada a carregar.');

            return self::FAILURE;
        }

        try {
            if ($this->option('listar')) {
                $glossarios = $tradutor->glossaries();

                if ($glossarios === []) {
                    $this->line('A conta não tem glossários.');

                    return self::SUCCESS;
                }

                $this->table(
                    ['Nome', 'Idiomas', 'Termos', 'Criado'],
                    array_map(fn (array $g) => [
                        $g['name'] ?? '?',
                        ($g['source_lang'] ?? '?').' → '.($g['target_lang'] ?? '?'),
                        $g['entry_count'] ?? '?',
                        $g['creation_time'] ?? '?',
                    ], $glossarios)
                );

                return self::SUCCESS;
            }

            $r = $tradutor->pushGlossary();
            $this->info("Glossário carregado: {$r['terms']} termos (id {$r['id']}).");

            if ($uso = $tradutor->usage()) {
                $this->line("Consumo do plano: {$uso['count']} de {$uso['limit']} caracteres.");
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
