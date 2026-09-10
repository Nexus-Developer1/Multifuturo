<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Services\Translator;
use App\Support\Features;
use App\Support\Locales;
use App\Support\PropertyCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * caracteristicas:traduzir — constrói o dicionário das características.
 *
 * As características são texto livre guardado uma só vez, em português: é esse
 * texto que os filtros procuram e que fica na base de dados. Para as mostrar
 * noutro idioma há um dicionário por idioma em lang/{idioma}/features.php, e é
 * este comando que o enche a partir do que existe mesmo na carteira.
 *
 * O que já está escrito no ficheiro nunca é tocado — quem corrigir uma tradução
 * à mão não a vê desaparecer na próxima passagem. Para refazer tudo, --forcar.
 *
 *   php artisan caracteristicas:traduzir --simular
 *   php artisan caracteristicas:traduzir
 */
class FeaturesTranslate extends Command
{
    protected $signature = 'caracteristicas:traduzir
        {--idioma=* : Idiomas a preencher (por omissão, todos menos o de partida)}
        {--forcar : Refaz também as que já estão traduzidas}
        {--simular : Diz o que faria, sem escrever nem gastar tradução}';

    protected $description = 'Traduz as características dos imóveis para os outros idiomas';

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

        $caracteristicas = $this->daCarteira();

        if ($caracteristicas === []) {
            $this->warn('Não há características nenhumas na carteira.');

            return self::SUCCESS;
        }

        $this->line(count($caracteristicas).' característica(s) distinta(s) na carteira.');

        foreach ($idiomas as $idioma) {
            if ($this->paraIdioma($tradutor, $idioma, $origem, $caracteristicas) === self::FAILURE) {
                return self::FAILURE;
            }
        }

        if (! $this->option('simular')) {
            PropertyCache::flush();
        }

        return self::SUCCESS;
    }

    /**
     * Todas as características que aparecem em alguma ficha, sem repetições.
     *
     * @return array<string, string> chave de dicionário => texto português
     */
    private function daCarteira(): array
    {
        $saida = [];

        foreach (Property::query()->pluck('features') as $lista) {
            foreach ((array) $lista as $feature) {
                $feature = trim((string) $feature);

                if ($feature !== '') {
                    $saida[Features::key($feature)] = $feature;
                }
            }
        }

        ksort($saida);

        return $saida;
    }

    /** @param  array<string, string>  $caracteristicas */
    private function paraIdioma(Translator $tradutor, string $idioma, string $origem, array $caracteristicas): int
    {
        $ficheiro = lang_path("{$idioma}/features.php");
        $actual = File::exists($ficheiro) ? (array) require $ficheiro : [];

        $emFalta = $this->option('forcar')
            ? $caracteristicas
            : array_diff_key($caracteristicas, $actual);

        // Uma característica que desapareceu da carteira sai do dicionário.
        $sobram = array_diff_key($actual, $caracteristicas);

        if ($emFalta === [] && $sobram === []) {
            $this->info("{$idioma}: já estava tudo traduzido (".count($actual).' entradas).');

            return self::SUCCESS;
        }

        $this->line("{$idioma}: ".count($emFalta).' por traduzir'.($sobram !== [] ? ', '.count($sobram).' já sem uso' : '').'.');

        if ($this->option('simular')) {
            foreach (array_slice($emFalta, 0, 10) as $pt) {
                $this->line("   {$pt}");
            }

            if (count($emFalta) > 10) {
                $this->line('   … e mais '.(count($emFalta) - 10).'.');
            }

            return self::SUCCESS;
        }

        $traduzidas = [];

        if ($emFalta !== []) {
            try {
                $alvo = $idioma === 'en' ? (string) config('deepl.target') : mb_strtoupper($idioma);
                $traduzidas = $tradutor->translateMany($emFalta, $alvo, mb_strtoupper($origem));
            } catch (Throwable $e) {
                $this->error("{$idioma}: {$e->getMessage()}");

                return self::FAILURE;
            }
        }

        // O que já lá estava manda: só se acrescenta o que faltava.
        $novo = array_intersect_key($actual, $caracteristicas) + $traduzidas;

        if ($this->option('forcar')) {
            $novo = $traduzidas + $novo;
        }

        ksort($novo);

        File::ensureDirectoryExists(dirname($ficheiro));
        File::put($ficheiro, $this->comoFicheiro($novo, $caracteristicas, $idioma));

        $this->info("{$idioma}: ".count($novo).' entradas escritas em lang/'.$idioma.'/features.php.');

        if ($uso = $tradutor->usage()) {
            $this->line("Consumo do plano: {$uso['count']} de {$uso['limit']} caracteres.");
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $traducoes
     * @param  array<string, string>  $originais
     */
    private function comoFicheiro(array $traducoes, array $originais, string $idioma): string
    {
        $linhas = [
            '<?php',
            '',
            '/*',
            '|--------------------------------------------------------------------------',
            '| Características dos imóveis',
            '|--------------------------------------------------------------------------',
            '|',
            '| Gerado por `php artisan caracteristicas:traduzir` a partir das',
            '| características que existem mesmo na carteira. O que estiver escrito aqui',
            '| nunca é substituído por uma nova passagem do comando: corrija à vontade.',
            '|',
            '| A chave é a característica portuguesa em forma de endereço; o comentário',
            '| ao lado é o texto original, para se saber o que se está a traduzir.',
            '|',
            '*/',
            '',
            'return [',
            '',
        ];

        foreach ($traducoes as $chave => $texto) {
            $original = $originais[$chave] ?? null;
            $comentario = $original !== null && mb_strtolower($original) !== mb_strtolower($texto)
                ? '  // '.$original
                : '';

            $linhas[] = sprintf(
                "    '%s' => '%s',%s",
                $chave,
                str_replace(['\\', "'"], ['\\\\', "\\'"], $texto),
                $comentario
            );
        }

        $linhas[] = '';
        $linhas[] = '];';
        $linhas[] = '';

        return implode("\n", $linhas);
    }
}
