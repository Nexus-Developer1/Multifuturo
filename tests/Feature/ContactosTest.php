<?php

/*
 * Página de contactos: as formas de falar com a agência e o mapa do escritório.
 *
 * Tudo vem de config('agency'). O que importa aqui é que cada linha aparece
 * quando há valor, desaparece quando não há, e que os endereços de telefone e
 * de WhatsApp saem em forma de serem clicados.
 */

beforeEach(function () {
    config([
        'agency.address' => 'Rua de Brito Capelo 598 2º andar sala 2.4, Matosinhos, Portugal, 4450-067',
        'agency.phone' => '912 178 876',
        'agency.whatsapp' => '+351 912 178 876',
        'agency.email' => 'geral@multifuturo.pt',
        'agency.lat' => '41.1824813',
        'agency.lon' => '-8.6900963',
    ]);
});

it('mostra a morada, o telemóvel, o WhatsApp e o email', function () {
    $this->get(route('contact'))
        ->assertOk()
        ->assertSee('Rua de Brito Capelo 598 2º andar sala 2.4, Matosinhos, Portugal, 4450-067')
        ->assertSee('912 178 876')
        ->assertSee('+351 912 178 876')
        ->assertSee('geral@multifuturo.pt')
        ->assertSee('Morada')
        ->assertSee('Telemóvel')
        ->assertSee('WhatsApp');
});

it('o telefone e o WhatsApp ficam clicáveis, só com dígitos', function () {
    $html = $this->get(route('contact'))->assertOk()->getContent();

    expect($html)->toContain('href="tel:912178876"')
        ->and($html)->toContain('href="https://wa.me/351912178876"')
        ->and($html)->toContain('href="mailto:geral@multifuturo.pt"');
});

it('desenha o mapa quando há coordenadas', function () {
    $html = $this->get(route('contact'))->assertOk()->getContent();

    expect($html)->toContain('propertyMap(')
        ->and($html)->toContain('41.1824813')
        // O caminho vai dentro de um @js(), com as barras escapadas.
        ->and($html)->toContain('leaflet.js')
        // Sem JavaScript fica a ligação para o mapa aberto.
        ->and($html)->toContain('openstreetmap.org');
});

it('sem coordenadas a página continua de pé, só sem mapa', function () {
    config(['agency.lat' => null, 'agency.lon' => null]);

    $html = $this->get(route('contact'))->assertOk()->getContent();

    expect($html)->not->toContain('propertyMap(')
        ->and($html)->toContain('Rua de Brito Capelo');
});

it('uma linha sem valor não deixa buraco', function () {
    config(['agency.whatsapp' => '', 'agency.phone' => '']);

    $html = $this->get(route('contact'))->assertOk()->getContent();

    expect($html)->not->toContain('wa.me')
        ->and($html)->not->toContain('href="tel:')
        ->and($html)->toContain('geral@multifuturo.pt');
});

it('em inglês as etiquetas mudam e os contactos ficam iguais', function () {
    $this->get(route('contact', ['locale' => 'en']))
        ->assertOk()
        ->assertSee('Contact details')
        ->assertSee('Mobile')
        ->assertSee('Where we are')
        ->assertSee('geral@multifuturo.pt')
        ->assertDontSee('Telemóvel');
});
