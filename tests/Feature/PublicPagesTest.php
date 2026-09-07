<?php

use App\Models\Property;
use App\Support\Features;

/*
 * Fumo das páginas públicas da Fase 1: respondem, têm o layout, e o rodapé mostra
 * AMI e Livro de Reclamações.
 */

it('serve a homepage com título, canonical e rodapé legal', function () {
    config(['agency.ami' => '99999']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<link rel="canonical" href="'.route('home').'">', false)
        ->assertSee(__('ui.footer.ami', ['number' => '99999']))
        ->assertSee(__('ui.footer.complaints_book'))
        ->assertSee(config('agency.complaints_book_url'), false);
});

it('avisa no rodapé quando o AMI ainda não está configurado', function () {
    config(['agency.ami' => null]);

    $this->get(route('home'))->assertOk()->assertSee(__('ui.footer.ami_missing'));
});

it('tem rotas separadas para comprar e arrendar', function () {
    $this->get(route('buy'))->assertOk()->assertSee(__('ui.listing.buy_title'));
    $this->get(route('rent'))->assertOk()->assertSee(__('ui.listing.rent_title'));
});

it('a 404 mostra a pesquisa de imóveis', function () {
    $this->get('/pagina-que-nao-existe')
        ->assertNotFound()
        ->assertSee(__('ui.errors.404_title'))
        ->assertSee('role="search"', false)
        ->assertSee(route('buy'), false);
});

it('não faz pedidos a fontes externas', function () {
    $html = $this->get(route('home'))->getContent();

    expect($html)->not->toContain('fonts.googleapis.com')
        ->and($html)->not->toContain('fonts.gstatic.com')
        ->and($html)->toContain('/fonts/bodoni-moda-latin.woff2');
});

it('as características da ficha aparecem arrumadas por grupos', function () {
    $p = Property::factory()->create([
        'features' => ['cozinha equipada', 'terraço', 'proximidade: escolas', 'elevador'],
    ]);

    $html = $this->get(route('property.show', $p))->assertOk()->getContent();

    // Cada grupo com o seu título, e o texto do imóvel ao lado.
    expect($html)->toContain(__('ui.property.feature_groups.interior'))
        ->toContain(__('ui.property.feature_groups.exterior'))
        ->toContain(__('ui.property.feature_groups.surroundings'))
        ->toContain(__('ui.property.feature_groups.general'))
        ->toContain(__('ui.property.additional_info'));
});

it('a arrumação das características não perde nenhuma', function () {
    $lista = ['cozinha equipada', 'terraço', 'proximidade: escolas', 'elevador', 'coisa nunca vista'];

    $grupos = Features::grouped($lista);

    expect(array_merge(...array_values($grupos)))->toHaveCount(5)
        ->and($grupos['interior'])->toBe(['cozinha equipada'])
        ->and($grupos['exterior'])->toBe(['terraço'])
        ->and($grupos['surroundings'])->toBe(['proximidade: escolas'])
        // O que não se reconhece cai no grupo geral em vez de desaparecer.
        ->and($grupos['general'])->toBe(['elevador', 'coisa nunca vista']);
});
