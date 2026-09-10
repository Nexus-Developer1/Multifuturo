<?php

/*
 * Tradução automática dos textos dos imóveis (DeepL).
 *
 * Nenhum destes testes fala com o serviço: os pedidos são simulados. O que se
 * verifica é o que sai daqui — o endereço certo, os parâmetros no formato que
 * o DeepL aceita, e o botão do backoffice a preencher só o que está vazio.
 */

use App\Filament\Resources\Properties\Pages\EditProperty;
use App\Models\Property;
use App\Models\User;
use App\Services\Translator;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/** Resposta do DeepL com um texto traduzido por cada texto enviado. */
function respostaDeepl(array $textos): array
{
    return ['translations' => array_map(
        fn (string $t) => ['text' => '[EN] '.$t, 'detected_source_language' => 'PT'],
        $textos
    )];
}

beforeEach(function () {
    config()->set('deepl.key', 'chave-de-teste:fx');
    config()->set('deepl.target', 'EN-GB');
    config()->set('deepl.glossary.name', '');
    cache()->flush();
});

it('sem chave configurada diz que não está disponível', function () {
    config()->set('deepl.key', null);

    expect(app(Translator::class)->available())->toBeFalse();
});

it('as chaves gratuitas falam com o endereço gratuito', function () {
    Http::fake(['api-free.deepl.com/*' => Http::response(respostaDeepl(['Olá']))]);

    app(Translator::class)->translate('Olá');

    Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://api-free.deepl.com/'));
});

it('as chaves pagas falam com o endereço normal', function () {
    config()->set('deepl.key', 'chave-de-teste');
    Http::fake(['api.deepl.com/*' => Http::response(respostaDeepl(['Olá']))]);

    app(Translator::class)->translate('Olá');

    Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://api.deepl.com/'));
});

it('envia o preserve_formatting como booleano, que é o que o DeepL aceita', function () {
    Http::fake(['*' => Http::response(respostaDeepl(['Casa com terraço.']))]);

    app(Translator::class)->translate('Casa com terraço.');

    // Enviado como a palavra "1", o DeepL responde 400 e não traduz nada.
    Http::assertSent(fn (Request $r) => $r['preserve_formatting'] === true
        && $r['source_lang'] === 'PT'
        && $r['target_lang'] === 'EN-GB');
});

it('protege as etiquetas do texto formatado', function () {
    Http::fake(['*' => Http::response(respostaDeepl(['<p>Casa</p>']))]);

    app(Translator::class)->translate('<p>Casa</p>', null, 'PT', html: true);

    Http::assertSent(fn (Request $r) => ($r['tag_handling'] ?? null) === 'html');
});

it('devolve os textos pelas chaves com que foram pedidos', function () {
    Http::fake(['*' => Http::response(respostaDeepl(['Um', 'Dois']))]);

    $saida = app(Translator::class)->translateMany(['titulo' => 'Um', 'descricao' => 'Dois']);

    expect($saida)->toBe(['titulo' => '[EN] Um', 'descricao' => '[EN] Dois']);
});

it('não gasta um pedido quando não há texto nenhum', function () {
    Http::fake();

    expect(app(Translator::class)->translateMany(['a' => '', 'b' => '   ']))->toBe([]);

    Http::assertNothingSent();
});

it('explica o que aconteceu quando acabam os caracteres do plano', function () {
    Http::fake(['*' => Http::response([], 456)]);

    expect(fn () => app(Translator::class)->translate('Olá'))
        ->toThrow(RuntimeException::class, 'Acabaram os caracteres do plano de tradução deste mês.');
});

it('explica o que aconteceu quando a chave é recusada', function () {
    Http::fake(['*' => Http::response([], 403)]);

    expect(fn () => app(Translator::class)->translate('Olá'))
        ->toThrow(RuntimeException::class, 'A chave do serviço de tradução foi recusada. Confirme a DEEPL_KEY.');
});

it('usa o glossário da agência quando ele existe na conta', function () {
    config()->set('deepl.glossary.name', 'multifuturo-imobiliario');

    Http::fake([
        '*/v2/glossaries' => Http::response(['glossaries' => [
            ['glossary_id' => 'g-123', 'name' => 'multifuturo-imobiliario'],
        ]]),
        '*/v2/translate' => Http::response(respostaDeepl(['Casa com terraço.'])),
    ]);

    app(Translator::class)->translate('Casa com terraço.');

    Http::assertSent(fn (Request $r) => ! str_contains($r->url(), '/translate') || $r['glossary_id'] === 'g-123');
});

it('traduz à mesma quando ainda não há glossário carregado', function () {
    config()->set('deepl.glossary.name', 'multifuturo-imobiliario');

    Http::fake([
        '*/v2/glossaries' => Http::response(['glossaries' => []]),
        '*/v2/translate' => Http::response(respostaDeepl(['Casa.'])),
    ]);

    expect(app(Translator::class)->translate('Casa.'))->toBe('[EN] Casa.');

    Http::assertSent(fn (Request $r) => ! str_contains($r->url(), '/translate') || ! isset($r['glossary_id']));
});

/*
|--------------------------------------------------------------------------
| O botão do backoffice
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    config()->set('locales.enabled', ['pt', 'en']);
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('o botão preenche o inglês a partir do português', function () {
    Http::fake(['*' => Http::response(respostaDeepl(['Casa com vista', 'vista mar', 'Uma casa bonita.']))]);

    $imovel = Property::factory()->create([
        'translations' => ['pt' => [
            'title' => 'Casa com vista',
            'keywords' => ['vista mar'],
            'description' => 'Uma casa bonita.',
        ]],
    ]);

    Livewire::test(EditProperty::class, ['record' => $imovel->getRouteKey()])
        ->callAction(TestAction::make('traduzir_en')->schemaComponent('idioma-en-title'))
        ->assertFormSet([
            'translations.en.title' => '[EN] Casa com vista',
            'translations.en.description' => '[EN] Uma casa bonita.',
        ]);
});

it('o botão nunca escreve por cima do que já está traduzido', function () {
    Http::fake(['*' => Http::response(respostaDeepl(['Uma casa bonita.']))]);

    $imovel = Property::factory()->create([
        'translations' => [
            'pt' => ['title' => 'Casa com vista', 'description' => 'Uma casa bonita.'],
            'en' => ['title' => 'A title written by hand'],
        ],
    ]);

    Livewire::test(EditProperty::class, ['record' => $imovel->getRouteKey()])
        ->callAction(TestAction::make('traduzir_en')->schemaComponent('idioma-en-title'))
        ->assertFormSet([
            'translations.en.title' => 'A title written by hand',
            'translations.en.description' => '[EN] Uma casa bonita.',
        ]);

    // Só o campo vazio foi pedido ao serviço.
    Http::assertSent(fn (Request $r) => $r['text'] === ['Uma casa bonita.']);
});

/*
|--------------------------------------------------------------------------
| O comando para a carteira inteira
|--------------------------------------------------------------------------
*/

it('o comando preenche os imóveis que estão sem tradução', function () {
    Http::fake(['*' => Http::response(respostaDeepl(['Casa boa', 'Descrição.']))]);

    $imovel = Property::factory()->create([
        'reference' => 'MF-TESTE-TRAD',
        'translations' => ['pt' => ['title' => 'Casa boa', 'description' => 'Descrição.']],
    ]);

    $this->artisan('imoveis:traduzir', ['--referencia' => 'MF-TESTE-TRAD'])
        ->assertSuccessful();

    $traducoes = $imovel->fresh()->translations;

    expect($traducoes['en']['title'])->toBe('[EN] Casa boa')
        ->and($traducoes['en']['description'])->toBe('[EN] Descrição.')
        ->and($traducoes['pt']['title'])->toBe('Casa boa');
});

it('a simulação não grava nada nem gasta tradução', function () {
    Http::fake();

    $imovel = Property::factory()->create([
        'reference' => 'MF-TESTE-SIM',
        'translations' => ['pt' => ['title' => 'Casa boa']],
    ]);

    $this->artisan('imoveis:traduzir', ['--referencia' => 'MF-TESTE-SIM', '--simular' => true])
        ->assertSuccessful();

    expect($imovel->fresh()->translations)->not->toHaveKey('en');

    Http::assertNothingSent();
});

it('o comando também respeita o que já está traduzido', function () {
    Http::fake(['*' => Http::response(respostaDeepl(['Descrição.']))]);

    $imovel = Property::factory()->create([
        'reference' => 'MF-TESTE-RESP',
        'translations' => [
            'pt' => ['title' => 'Casa boa', 'description' => 'Descrição.'],
            'en' => ['title' => 'Written by a person'],
        ],
    ]);

    $this->artisan('imoveis:traduzir', ['--referencia' => 'MF-TESTE-RESP'])->assertSuccessful();

    expect($imovel->fresh()->translations['en']['title'])->toBe('Written by a person');

    // O comando também pergunta o consumo do plano no fim: só interessa a tradução.
    Http::assertSent(fn (Request $r) => ! str_contains($r->url(), '/translate') || $r['text'] === ['Descrição.']);
});

it('sem chave não há botão de tradução no formulário', function () {
    config()->set('deepl.key', null);

    $imovel = Property::factory()->create([
        'translations' => ['pt' => ['title' => 'Casa com vista']],
    ]);

    // Escondido não: deixa mesmo de existir no formulário.
    Livewire::test(EditProperty::class, ['record' => $imovel->getRouteKey()])
        ->assertActionDoesNotExist(TestAction::make('traduzir_en')->schemaComponent('idioma-en-title'));
});
