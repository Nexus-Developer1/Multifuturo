<?php

/*
 * Nos resultados do Google, o site aparecia como "multifuturo.pt" e com um globo
 * em vez do logótipo (2026-09-14). O ícone vem do favicon, que já estava certo;
 * o nome vem dos dados estruturados WebSite na página inicial, que faltavam.
 */

function jsonLdDaInicial($teste): array
{
    $html = $teste->get(route('home'))->assertOk()->getContent();

    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

    return json_decode($m[1] ?? 'null', true) ?? [];
}

it('a página inicial diz ao Google o nome do site, na raiz do domínio', function () {
    config(['app.url' => 'https://multifuturo.pt', 'agency.name' => 'Multifuturo Propriedades']);

    $grafo = collect(jsonLdDaInicial($this)['@graph'] ?? [])->keyBy('@type');

    expect($grafo->get('WebSite'))->toMatchArray([
        'name' => 'Multifuturo Propriedades',
        'alternateName' => 'Multifuturo',
        'url' => 'https://multifuturo.pt/',
        'publisher' => ['@id' => 'https://multifuturo.pt/#agencia'],
    ]);
});

it('a agência leva o logótipo quadrado, os contactos e as redes sociais', function () {
    config([
        'app.url' => 'https://multifuturo.pt',
        'agency.whatsapp' => '+351 912 178 876',
        'agency.email' => 'geral@multifuturo.pt',
        'agency.social' => ['facebook' => 'https://www.facebook.com/x', 'instagram' => 'https://www.instagram.com/y', 'linkedin' => null],
    ]);

    $agencia = collect(jsonLdDaInicial($this)['@graph'] ?? [])->keyBy('@type')->get('RealEstateAgent');

    expect($agencia['@id'])->toBe('https://multifuturo.pt/#agencia')
        ->and($agencia['logo'])->toEndWith('/images/marca/favicon-512.png')
        ->and($agencia['telephone'])->toBe('+351 912 178 876')
        ->and($agencia['email'])->toBe('geral@multifuturo.pt')
        ->and($agencia['sameAs'])->toBe(['https://www.facebook.com/x', 'https://www.instagram.com/y']);
});

it('os ícones que o Google usa existem e estão no <head>', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();

    // O Google quer um ícone quadrado com 48 px ou mais (múltiplo de 48).
    foreach (['favicon-192.png' => 192, 'favicon-512.png' => 512] as $ficheiro => $lado) {
        expect($html)->toContain('images/marca/'.$ficheiro);
        [$largura, $altura] = getimagesize(public_path('images/marca/'.$ficheiro));
        expect([$largura, $altura])->toBe([$lado, $lado]);
    }
});
