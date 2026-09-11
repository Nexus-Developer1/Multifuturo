{{--
    Layout do portal da equipa. A marca e a paleta são as da Multifuturo, as
    mesmas do site (areia, azeitona, tinta, Bodoni Moda nos títulos); o visual
    vem de resources/css/portal.css. O layout é que é de aplicação — barra
    lateral, tabelas, formulários — e não de página editorial.

    Props:
      title   — título da página
      entrada — true nas páginas de login/verificação (painel de marca à
                esquerda, formulário à direita); false na página de escolha
                (barra superior escura, conteúdo claro).
--}}
@props(['title' => 'Portal', 'entrada' => false])
@php $portal = config('portal.name'); @endphp
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#282C1E">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · {{ $portal }}</title>
    {{-- O mesmo ícone do site: é a mesma casa. --}}
    <link rel="icon" href="{{ asset('images/marca/favicon.png') }}">
    @vite(['resources/css/portal.css'])
</head>
<body>
@php
    // A marca da agência, com o mesmo desenho do cabeçalho do site: símbolo à
    // esquerda, nome em serifada maiúscula ao lado.
    $marca = '<img src="'.asset('images/marca/simbolo.png').'" alt="" width="384" height="317" class="p-marca__simbolo">'
        .'<span class="p-marca__texto">Multifuturo<span class="p-marca__sub">Propriedades</span></span>';
@endphp
@if ($entrada)
    <div class="p-entrada">
        {{-- Painel da marca (esquerda). Decorativo: desaparece em ecrãs estreitos. --}}
        <aside class="p-entrada__marca-painel" aria-hidden="true">
            <span class="p-brilho p-brilho--cima"></span>
            <span class="p-brilho p-brilho--baixo"></span>

            <div class="p-marca">{!! $marca !!}</div>

            {{-- Só uma ponte: uma frase, sem discurso comercial. --}}
            <div class="p-discurso">
                <h2>Uma só entrada.<br>Todos os módulos.</h2>
                <p>Entre uma vez e escolha onde quer trabalhar.</p>
            </div>
        </aside>

        <main class="p-entrada__painel">
            <div class="p-entrada__caixa">
                <div class="p-marca p-entrada__marca-estreita">{!! $marca !!}</div>

                {{ $slot }}
            </div>
        </main>
    </div>
@else
    @php
        $pessoa = auth()->user();
        $iniciais = $pessoa ? (collect(explode(' ', trim($pessoa->name)))->filter()->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->take(2)->implode('') ?: '–') : '–';
    @endphp
    <div class="p-app" data-app>
        <button class="p-veu" data-fechar-lateral hidden aria-label="Fechar menu"></button>

        {{-- Barra lateral: marca, navegação, e a pessoa com o único "terminar sessão". --}}
        <aside class="p-lateral" data-lateral>
            <div class="p-lateral__topo">
                <a href="{{ route('portal') }}" class="p-marca">{!! $marca !!}</a>
                <button class="p-lateral__fechar" data-fechar-lateral aria-label="Fechar menu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <nav class="p-lateral__nav" aria-label="Navegação do portal">
                <a class="p-nav {{ request()->routeIs('portal') ? 'p-nav--ativo' : '' }}" href="{{ route('portal') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
                    <span>Início</span>
                </a>

                <p class="p-lateral__grupo">A minha conta</p>
                <a class="p-nav {{ request()->routeIs('profile.*') ? 'p-nav--ativo' : '' }}" href="{{ route('profile.edit') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                    <span>Perfil e palavra-passe</span>
                </a>

                @if ($pessoa?->isAdmin())
                    <p class="p-lateral__grupo">Gestão</p>
                    <a class="p-nav {{ request()->routeIs('team.*') ? 'p-nav--ativo' : '' }}" href="{{ route('team.index') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        <span>Equipa e acessos</span>
                    </a>
                @endif
            </nav>

            @auth
                <div class="p-lateral__pessoa">
                    <span class="p-avatar" aria-hidden="true">{{ $iniciais }}</span>
                    <span class="p-lateral__quem">
                        <span class="p-lateral__nome">{{ $pessoa->name }}</span>
                        <span class="p-lateral__email">{{ $pessoa->email }}</span>
                    </span>
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="p-lateral__sair" title="Terminar sessão" aria-label="Terminar sessão">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        </button>
                    </form>
                </div>
            @endauth
        </aside>

        <div class="p-trabalho">
            <header class="p-topo-movel">
                <button class="p-topo-movel__menu" data-abrir-lateral aria-label="Abrir menu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <span class="p-topo-movel__nome">{{ $portal }}</span>
            </header>

            <main class="p-principal">
                <div class="p-largura">
                    {{ $slot }}
                </div>
            </main>

            <footer class="p-rodape">
                <span>{{ $portal }} · acesso reservado</span>
                {{-- Assinatura de quem construiu: discreta, ganha contraste ao passar o rato. --}}
                <span class="p-assinatura">By Nexus IT Solutions</span>
            </footer>
        </div>
    </div>

    <script>
        // Barra lateral no telemóvel: abre com o botão de menu, fecha com o véu, o X ou Escape.
        (function () {
            var lateral = document.querySelector('[data-lateral]'), veu = document.querySelector('.p-veu');
            if (!lateral) return;
            function abrir() { lateral.classList.add('p-lateral--aberta'); veu.hidden = false; }
            function fechar() { lateral.classList.remove('p-lateral--aberta'); veu.hidden = true; }
            document.querySelectorAll('[data-abrir-lateral]').forEach(function (b) { b.addEventListener('click', abrir); });
            document.querySelectorAll('[data-fechar-lateral]').forEach(function (b) { b.addEventListener('click', fechar); });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') fechar(); });
        })();
    </script>
@endif
@if (session('status'))
    {{-- Aviso de confirmação ("Sessão terminada.", "Conta atualizada."…) no
         canto da página, por cima de tudo, em vez de empurrar o conteúdo. Sai
         sozinho (script abaixo) ou no X. Os erros dos formulários continuam
         junto do formulário, onde é preciso corrigi-los. --}}
    <div class="p-aviso" role="status" data-aviso>
        <svg class="p-aviso__icone" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/></svg>
        <p>{{ session('status') }}</p>
        <button type="button" class="p-aviso__fechar" data-fechar-aviso aria-label="Fechar aviso">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
@endif
<script>
    // O aviso do canto: sai sozinho depois de dar tempo para o ler — 4 s e mais
    // um pouco por letra, até 12 s — ou no X. Com o rato em cima, espera.
    (function () {
        var aviso = document.querySelector('[data-aviso]');
        if (!aviso) return;
        var relogio, tempo = Math.min(12000, 4000 + aviso.textContent.trim().length * 60);
        function fechar() {
            clearTimeout(relogio);
            aviso.classList.add('p-aviso--a-sair');
            setTimeout(function () { aviso.remove(); }, 300);
        }
        function armar() { clearTimeout(relogio); relogio = setTimeout(fechar, tempo); }
        aviso.querySelector('[data-fechar-aviso]').addEventListener('click', fechar);
        aviso.addEventListener('mouseenter', function () { clearTimeout(relogio); });
        aviso.addEventListener('mouseleave', armar);
        armar();
    })();

    // O "olho" dos campos de palavra-passe (x-portal.password): troca o campo
    // entre password e text. Ao submeter, volta tudo a password, para o browser
    // não guardar a palavra-passe no histórico de texto dos formulários.
    (function () {
        document.addEventListener('click', function (e) {
            var botao = e.target.closest('[data-ver-senha]');
            if (!botao) return;
            var campo = document.getElementById(botao.getAttribute('aria-controls'));
            if (!campo) return;
            var ver = campo.type === 'password';
            campo.type = ver ? 'text' : 'password';
            botao.setAttribute('aria-pressed', ver ? 'true' : 'false');
            botao.title = ver ? 'Esconder palavra-passe' : 'Mostrar palavra-passe';
        });
        document.addEventListener('submit', function (e) {
            e.target.querySelectorAll('[data-ver-senha]').forEach(function (botao) {
                var campo = document.getElementById(botao.getAttribute('aria-controls'));
                if (campo) campo.type = 'password';
                botao.setAttribute('aria-pressed', 'false');
            });
        });
    })();
</script>
</body>
</html>
