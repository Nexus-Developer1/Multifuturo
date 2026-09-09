<?php

/*
 * Backoffice (/admin, Filament): autenticação obrigatória e recursos acessíveis
 * a utilizadores autenticados.
 */

use App\Filament\Pages\PainelDeControlo;
use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\Properties\PropertyResource;
use App\Models\Contact;
use App\Models\Event;
use App\Models\Lead;
use App\Models\Property;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;

it('o login do backoffice é o do portal (/entrar); o do Filament já não existe', function () {
    $this->get('/entrar')->assertOk()->assertSee('Entrar');
    $this->get('/admin/login')->assertNotFound();
});

it('visitantes não autenticados são redirecionados para o login do portal', function (string $path) {
    $this->get($path)->assertRedirect('/entrar');
})->with(['/admin', '/admin/properties', '/admin/leads']);

it('um administrador vê o painel e as listagens', function () {
    $user = User::factory()->create(['is_admin' => true]);
    Property::factory()->create(['reference' => 'MF-901', 'city' => 'Cascais']);
    Lead::factory()->create(['name' => 'Pedido Teste']);

    $this->actingAs($user)->get('/admin')->assertOk();
    $this->actingAs($user)->get('/admin/properties')->assertOk()->assertSee('MF-901');
    $this->actingAs($user)->get('/admin/leads')->assertOk()->assertSee('Pedido Teste');
});

it('o painel de controlo é só do administrador; os outros vão aos imóveis', function () {
    $consultor = User::factory()->create(['is_admin' => false]);
    Property::factory()->create(['reference' => 'MF-902']);

    // Não bate num 403 à entrada: é levado ao trabalho dele.
    $this->actingAs($consultor)->get('/admin')
        ->assertRedirect(PropertyResource::getUrl('index'));

    // E continua a poder trabalhar no resto.
    $this->actingAs($consultor)->get('/admin/properties')->assertOk()->assertSee('MF-902');

    // A entrada do painel também não lhe aparece na barra lateral.
    expect(PainelDeControlo::canAccess())->toBeFalse();

    $this->actingAs(User::factory()->create(['is_admin' => true]));
    expect(PainelDeControlo::canAccess())->toBeTrue();
});

it('não é possível criar pedidos à mão no backoffice', function () {
    // As leads nascem dos formulários públicos: não há rota nem botão de criação.
    expect(LeadResource::canCreate())->toBeFalse()
        ->and(Route::has('filament.admin.resources.leads.create'))->toBeFalse();
});

it('o backoffice não é indexável (fora do sitemap; robots dinâmico bloqueia-o em produção via Disallow /admin?)', function () {
    // O sitemap nunca inclui /admin; o robots.txt de produção só permite o site público.
    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
    expect($xml)->not->toContain('/admin');
});

it('o backoffice está todo em português', function () {
    $this->actingAs(User::factory()->create());

    // O idioma por omissão não depende do .env: uma instalação nova arranca em pt.
    expect(config('app.locale'))->toBe('pt')
        ->and(config('app.fallback_locale'))->toBe('pt');

    $contacto = Contact::factory()->create();
    $evento = Event::factory()->create();

    $this->get(route('filament.admin.resources.contacts.index'))->assertOk()
        ->assertSee('Nome')->assertSee('Telefone')->assertSee('Responsável')
        ->assertDontSee('Email address')->assertDontSee('Created at');

    $this->get(route('filament.admin.resources.events.index'))->assertOk()
        ->assertSee('Título')->assertSee('Início')->assertSee('Concluído')
        ->assertDontSee('Starts at')->assertDontSee('Is done');

    $this->get(route('filament.admin.resources.contacts.edit', $contacto))->assertOk()
        ->assertSee('Preferências')->assertDontSee('Email address');

    $this->get(route('filament.admin.resources.events.edit', $evento))->assertOk()
        ->assertSee('Ligações')->assertDontSee('Ends at');
});

it('o ícone e o logótipo do backoffice resolvem-se a cada pedido, não no arranque', function () {
    /*
     * O painel do Filament é construído no arranque, sem pedido nenhum. Um
     * asset() avaliado aí fica com o endereço de então — no servidor, o
     * endereço interno do contentor (http://127.0.0.1:8080/…), que o browser
     * de fora não alcança: o separador ficava com o ícone genérico e o
     * logótipo não carregava. Guardados como função, são calculados a cada
     * pedido, com o domínio certo.
     *
     * O teste é à forma e não ao endereço, de propósito: aqui tudo responde
     * em http://localhost, e um endereço fixado no arranque daria o mesmo
     * resultado — passaria sem apanhar nada.
     */
    $painel = Filament::getPanel('admin');

    foreach (['favicon', 'brandLogo'] as $propriedade) {
        $p = new ReflectionProperty($painel, $propriedade);
        $p->setAccessible(true);

        expect($p->getValue($painel))->toBeInstanceOf(Closure::class);
    }

    // E, com pedido, saem mesmo no endereço do site.
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $this->get('/admin')->assertOk()
        ->assertSee(asset('images/marca/favicon-192.png'), false)
        ->assertSee(asset('images/marca/simbolo.png'), false);
});
