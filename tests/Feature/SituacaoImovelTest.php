<?php

/*
 * Situação comercial do imóvel (Reservado, Vendido, Arrendado — ou outra que a
 * agência escreva). Pedido do cliente em 2026-09-29, depois de fechar os
 * primeiros contratos: o imóvel continua no site, com a situação à vista, e o
 * preço passa a "sob consulta".
 */

use App\Filament\Resources\Properties\Pages\EditProperty;
use App\Filament\Resources\Properties\PropertyResource;
use App\Models\Property;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Select;
use Livewire\Livewire;

it('com situação, o site troca o preço por "sob consulta" e mostra a situação', function () {
    $p = Property::factory()->create([
        'price' => 250000,
        'price_visible' => true,
        'commercial_state' => 'Reservado',
    ]);

    $ficha = $this->get(route('property.show', $p))->assertOk();

    $ficha->assertSee('Preço sob consulta')
        ->assertSee('Reservado')
        ->assertDontSee('250'.chr(0xE2).chr(0x80).chr(0xAF).'000'); // o preço formatado, com espaço fino

    // Nem no JSON-LD, que seria contraditório com a ficha.
    expect($ficha->getContent())->not->toContain('"price":"250000.00"');

    // E na listagem, no cartão — já sem a referência, que fica só na ficha.
    $this->get(route('buy'))->assertOk()
        ->assertSee('Reservado')
        ->assertSee('Preço sob consulta')
        ->assertDontSee('Ref. '.$p->reference);
    $ficha->assertSee('Ref. '.$p->reference);
});

it('sem situação, o preço aparece como sempre', function () {
    $p = Property::factory()->create(['price' => 250000, 'price_visible' => true, 'commercial_state' => null]);

    $this->get(route('property.show', $p))->assertOk()
        ->assertSee('250'.chr(0xE2).chr(0x80).chr(0xAF).'000')
        ->assertDontSee('Preço sob consulta');
});

it('a situação não tira o imóvel do site', function () {
    $p = Property::factory()->create(['commercial_state' => 'Vendido']);

    expect($p->isPublishable())->toBeTrue()
        ->and($p->priceIsVisible())->toBeFalse();

    $this->get(route('property.show', $p))->assertOk();
});

it('a situação é texto livre: o backoffice escreve uma que não existe no código', function () {
    $this->actingAs(User::factory()->create());

    $p = Property::factory()->create(['price' => 300000, 'price_visible' => true]);

    Livewire::test(EditProperty::class, ['record' => $p->getRouteKey()])
        // Sem situação não há aviso; assim que se escreve uma, aparece a caixa.
        ->assertDontSee('Este imóvel vai aparecer sob consulta')
        ->fillForm(['commercial_state' => 'Em escritura'])
        ->assertSee('Este imóvel vai aparecer sob consulta')
        ->call('save')
        ->assertHasNoFormErrors();

    expect($p->refresh()->commercial_state)->toBe('Em escritura')
        ->and($p->priceIsVisible())->toBeFalse();

    // As três de origem continuam a ser as sugestões.
    expect(Property::COMMERCIAL_STATES)->toBe(['Reservado', 'Vendido', 'Arrendado']);
});

it('a situação escolhe-se numa lista, e o "+" cria uma nova sem código', function () {
    $this->actingAs(User::factory()->create());

    $p = Property::factory()->create();

    Livewire::test(EditProperty::class, ['record' => $p->getRouteKey()])
        // "Sem situação" no topo e as três de origem, como as opções do tipo de negócio.
        ->assertFormFieldExists('commercial_state', fn ($campo) => $campo instanceof Select
            && $campo->getOptions() === ['' => 'Sem situação', 'Reservado' => 'Reservado', 'Vendido' => 'Vendido', 'Arrendado' => 'Arrendado'])
        ->callAction(TestAction::make('createOption')->schemaComponent('commercial_state'), data: ['nome' => 'Aguarda banco'])
        ->assertFormSet(['commercial_state' => 'Aguarda banco'])
        ->assertSee('Este imóvel vai aparecer sob consulta')
        ->call('save')
        ->assertHasNoFormErrors();

    expect($p->refresh()->commercial_state)->toBe('Aguarda banco');

    // Depois de usada, a nova passa a estar na lista dos outros imóveis.
    Livewire::test(EditProperty::class, ['record' => Property::factory()->create()->getRouteKey()])
        ->assertFormFieldExists('commercial_state', fn ($campo) => array_key_exists('Aguarda banco', $campo->getOptions()));
});

it('"Sem situação" tira a situação e o preço volta a aparecer', function () {
    $this->actingAs(User::factory()->create());

    $p = Property::factory()->create(['price_visible' => true, 'commercial_state' => 'Reservado']);

    Livewire::test(EditProperty::class, ['record' => $p->getRouteKey()])
        ->assertSee('Este imóvel vai aparecer sob consulta')
        ->fillForm(['commercial_state' => ''])
        ->assertDontSee('Este imóvel vai aparecer sob consulta')
        ->call('save')
        ->assertHasNoFormErrors();

    // Grava-se null, não uma string vazia: é o que "sem situação" quer dizer.
    expect($p->refresh()->commercial_state)->toBeNull()
        ->and($p->priceIsVisible())->toBeTrue();
});

it('a caixa "Vendida" já não está no formulário: vende-se pela situação', function () {
    // Pedido da agência (2026-09-30): um imóvel vendido marca-se em Situação,
    // que o deixa no site com o preço sob consulta, em vez de o esconder.
    $this->actingAs(User::factory()->create());

    Livewire::test(EditProperty::class, ['record' => Property::factory()->create()->getRouteKey()])
        ->assertFormFieldDoesNotExist('is_sold')
        ->assertFormFieldExists('commercial_state');
});

/** O texto do cartão de um imóvel numa página, sem o resto da página. */
function cartaoDe(string $html, Property $p): string
{
    preg_match('#<article[^>]*data-slug="'.preg_quote($p->slug, '#').'"(.*?)</article>#s', $html, $m);

    return trim(preg_replace('/\s+/u', ' ', strip_tags($m[1] ?? '')));
}

it('com situação, o cartão diz só "Preço sob consulta", sem quartos nem área', function () {
    $p = Property::factory()->create(['bedrooms' => 3, 'house_area' => 208, 'price' => 570000, 'commercial_state' => 'Reservado']);

    $cartao = cartaoDe($this->get(route('buy'))->assertOk()->getContent(), $p);

    expect($cartao)->toContain('Preço sob consulta')
        ->and($cartao)->not->toContain('quartos')
        ->and($cartao)->not->toContain('208')
        ->and($cartao)->not->toContain('570');
});

it('sem Redis (cache na base de dados, como no cPanel), gravar a situação atualiza logo a listagem', function () {
    // Em 2026-09-30 a agência pôs um imóvel em "Reservado" e a listagem continuou
    // com o preço: sem tags, o flush() da cache não fazia nada.
    config(['cache.default' => 'database']);
    $this->actingAs(User::factory()->create());

    $p = Property::factory()->create(['bedrooms' => 3, 'price' => 570000, 'price_visible' => true, 'commercial_state' => null]);

    expect(cartaoDe($this->get(route('buy'))->getContent(), $p))->toContain('3 quartos');

    Livewire::test(EditProperty::class, ['record' => $p->getRouteKey()])
        ->fillForm(['commercial_state' => 'Reservado'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(cartaoDe($this->get(route('buy'))->getContent(), $p))->toContain('Preço sob consulta')
        ->not->toContain('3 quartos');
});

it('o aviso de "sob consulta" só aparece quando há situação', function () {
    expect(PropertyResource::avisoDaSituacao(null))->toBeNull();

    $aviso = PropertyResource::avisoDaSituacao('Reservado');

    expect($aviso?->getTitle())->toBe('Este imóvel vai aparecer sob consulta')
        ->and($aviso?->getBody())->toContain('Preço sob consulta');
});
