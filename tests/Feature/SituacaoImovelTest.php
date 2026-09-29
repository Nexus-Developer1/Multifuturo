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

    // E na listagem, no cartão.
    $this->get(route('buy'))->assertOk()
        ->assertSee('Reservado')
        ->assertSee('Preço sob consulta');
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

it('o aviso de "sob consulta" só aparece quando há situação', function () {
    expect(PropertyResource::avisoDaSituacao(null))->toBeNull();

    $aviso = PropertyResource::avisoDaSituacao('Reservado');

    expect($aviso?->getTitle())->toBe('Este imóvel vai aparecer sob consulta')
        ->and($aviso?->getBody())->toContain('Preço sob consulta');
});
