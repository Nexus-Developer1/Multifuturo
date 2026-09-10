<?php

/*
 * Características dos imóveis noutros idiomas.
 *
 * O texto guardado é sempre o português — é esse que os filtros procuram. O que
 * muda por idioma é só a forma como se lê, pelo dicionário lang/{idioma}/features.php.
 */

use App\Models\Property;
use App\Support\Features;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

afterEach(function () {
    // O comando escreve mesmo um ficheiro; o idioma "xx" existe só para o teste.
    File::deleteDirectory(lang_path('xx'));
});

it('lê a característica no idioma servido', function () {
    app()->setLocale('en');

    expect(Features::label('vista mar'))->toBe('sea view')
        ->and(Features::label('proximidade: praia'))->toBe('nearby: beach');
});

it('mostra o português quando ainda não há tradução', function () {
    app()->setLocale('en');

    expect(Features::label('característica que ninguém previu'))
        ->toBe('característica que ninguém previu');
});

it('em português devolve o texto tal como está guardado', function () {
    app()->setLocale('pt');

    expect(Features::label('vista mar'))->toBe('vista mar');
});

it('a chave de dicionário aguenta acentos e dois pontos', function () {
    expect(Features::key('Proximidade: farmácia'))->toBe('proximidade-farmacia')
        ->and(Features::key('Água da rede'))->toBe('agua-da-rede');
});

it('a ficha em inglês mostra as características traduzidas', function () {
    $imovel = Property::factory()->create([
        'features' => ['vista mar', 'garagem', 'proximidade: praia'],
        'translations' => ['pt' => ['title' => 'Casa com vista']],
    ]);

    $this->get(route('property.show', ['locale' => 'en', 'property' => $imovel]))
        ->assertOk()
        ->assertSee('sea view')
        ->assertSee('nearby: beach')
        ->assertDontSee('vista mar');
});

it('a ficha em português continua a mostrar o original', function () {
    $imovel = Property::factory()->create([
        'features' => ['vista mar', 'garagem'],
        'translations' => ['pt' => ['title' => 'Casa com vista']],
    ]);

    $this->get(route('property.show', ['locale' => 'pt', 'property' => $imovel]))
        ->assertOk()
        ->assertSee('vista mar');
});

it('o dicionário cobre todas as características da carteira', function () {
    $dicionario = require lang_path('en/features.php');

    $emFalta = [];

    foreach (Property::query()->pluck('features') as $lista) {
        foreach ((array) $lista as $feature) {
            $chave = Features::key(trim((string) $feature));

            if (trim((string) $feature) !== '' && ! isset($dicionario[$chave])) {
                $emFalta[$chave] = $feature;
            }
        }
    }

    expect($emFalta)->toBe([], 'sem tradução: '.implode(', ', $emFalta));
})->skip(fn () => Property::query()->count() === 0, 'sem imóveis na base de testes');

it('o comando escreve o dicionário do idioma', function () {
    config()->set('deepl.key', 'chave-de-teste:fx');
    config()->set('deepl.glossary.name', '');

    Http::fake(['*' => Http::response(['translations' => [
        // Pela ordem em que o comando as pede: as chaves vão ordenadas.
        ['text' => 'garage'],
        ['text' => 'sea view'],
    ]])]);

    Property::factory()->create(['features' => ['vista mar', 'garagem']]);

    $this->artisan('caracteristicas:traduzir', ['--idioma' => ['xx']])->assertSuccessful();

    $escrito = require lang_path('xx/features.php');

    expect($escrito)->toBe(['garagem' => 'garage', 'vista-mar' => 'sea view']);
});

it('o comando não mexe no que já lá estava escrito', function () {
    config()->set('deepl.key', 'chave-de-teste:fx');
    config()->set('deepl.glossary.name', '');

    File::ensureDirectoryExists(lang_path('xx'));
    File::put(lang_path('xx/features.php'), "<?php\n\nreturn ['vista-mar' => 'corrigido a mao'];\n");

    Http::fake(['*' => Http::response(['translations' => [['text' => 'garage']]])]);

    Property::factory()->create(['features' => ['vista mar', 'garagem']]);

    $this->artisan('caracteristicas:traduzir', ['--idioma' => ['xx']])->assertSuccessful();

    expect(require lang_path('xx/features.php'))
        ->toBe(['garagem' => 'garage', 'vista-mar' => 'corrigido a mao']);
});
