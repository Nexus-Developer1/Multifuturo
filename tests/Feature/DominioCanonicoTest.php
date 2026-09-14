<?php

/*
 * Um só endereço para o site. Em 2026-09-14 o cliente não conseguia entrar no
 * backoffice: em https://www.multifuturo.pt/entrar o login dava "Page Expired"
 * (419), porque o cookie de sessão ficava no www e o formulário ia para o
 * endereço sem www. Agora o www é reencaminhado antes de haver sessão.
 */

beforeEach(function () {
    config(['app.url' => 'https://multifuturo.pt']);
});

it('o www passa para o endereço do APP_URL, com o caminho e a query', function () {
    $this->get('https://www.multifuturo.pt/entrar')
        ->assertStatus(301)
        ->assertRedirect('https://multifuturo.pt/entrar')
        ->assertCookieMissing(config('session.cookie'));

    $this->get('https://www.multifuturo.pt/pt/comprar?tipo=moradia&pagina=2')
        ->assertStatus(301)
        ->assertRedirect('https://multifuturo.pt/pt/comprar?tipo=moradia&pagina=2');
});

it('um envio para o www mantém o método (308)', function () {
    $this->post('https://www.multifuturo.pt/entrar', ['email' => 'x@multifuturo.test', 'password' => 'x'])
        ->assertStatus(308)
        ->assertRedirect('https://multifuturo.pt/entrar');
});

it('o endereço certo e os outros endereços passam como estão', function () {
    $this->get('https://multifuturo.pt/entrar')->assertOk();

    // O de testes na NXS e o da rede local não têm nada a ver com o www.
    $this->get('https://multifuturo.nexus-solutions.pt:9443/entrar')->assertOk();
    $this->get('http://192.168.1.68/entrar')->assertOk();
});

it('se o APP_URL for com www, é o endereço sem www que passa para lá', function () {
    config(['app.url' => 'https://www.multifuturo.pt']);

    $this->get('https://multifuturo.pt/entrar')
        ->assertStatus(301)
        ->assertRedirect('https://www.multifuturo.pt/entrar');
});
