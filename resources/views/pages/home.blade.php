{{--
    Página inicial — linguagem editorial: fotografia grande com movimento lento,
    tipografia serifada em caixa alta, faixas de cor alternadas e composições
    assimétricas. Tudo aparece à medida que se desce (data-reveal); sem
    JavaScript, ou com "reduzir movimento", nasce visível e quieto.
--}}
@php use App\Support\Format; @endphp
<x-layouts.app :title="__('ui.home.title')" :description="__('ui.home_sections.hero_lead')" :canonical="route('home')" :image="$heroImage">
    {{-- 1. Abertura: fotografia a toda a largura, texto encostado em baixo à esquerda --}}
    @php $variasFotos = $heroImages && count($heroImages) > 1; @endphp
    <section @class(['relative isolate flex items-end overflow-hidden', 'bg-olive-900 text-sand-50' => $heroImage, 'bg-sand-100 text-ink' => ! $heroImage, 'touch-pan-y' => $variasFotos])
             style="min-height: min(92svh, 900px)"
             @if ($heroImages) x-data="slideshow({{ count($heroImages) }}, 5000)" @endif
             @if ($variasFotos) x-on:pointerdown="agarrar($event)" x-on:pointerup="largar($event)" x-on:pointercancel="inicioX = null" @endif>
        @if ($heroImages)
            {{--
                As fotografias da carteira, a alternar de 5 em 5 segundos com um
                esbatimento lento (slideshow, em resources/js/app.js). A primeira
                carrega com prioridade; as outras chegam a seguir. Todas se movem
                em conjunto dentro da moldura (parallax).
            --}}
            <div class="parallax-frame absolute inset-0 -z-20" aria-hidden="true">
                <div data-parallax="0.16" class="absolute inset-x-0">
                    @foreach ($heroImages as $i => $imagem)
                        {{-- A opacidade vai no estilo, não numa classe: o :class do Alpine
                             acrescenta classes sem tirar as que já lá estão, e as duas
                             ficavam a discutir qual mandava. --}}
                        <img src="{{ $imagem }}" alt="" width="1920" height="1080" decoding="async" data-hero-photo
                             @if ($i === 0) fetchpriority="high" @else loading="lazy" @endif
                             class="absolute inset-0 h-full w-full object-cover transition-opacity duration-[1500ms] ease-out"
                             style="opacity: {{ $i === 0 ? '1' : '0' }}"
                             :style="'opacity: ' + (atual === {{ $i }} ? 1 : 0)"
                             data-fallback="{{ asset('images/placeholder-property.jpg') }}"
                             onerror="this.onerror=null;this.src=this.dataset.fallback">
                    @endforeach
                </div>
            </div>
            <div class="absolute inset-0 -z-10 bg-linear-to-t from-ink/85 via-ink/60 to-ink/25" aria-hidden="true"></div>

            @if (count($heroImages) > 1)
                {{--
                    Passar as fotografias à mão: deslizar o dedo (ou arrastar o rato)
                    em qualquer ponto da abertura — o gesto está na própria secção,
                    também por cima do texto. Esta camada só dá o cursor de mão no
                    computador. Mudar de fotografia à mão pára a rotação automática:
                    quem está a escolher não quer a fotografia a fugir-lhe.
                --}}
                <div x-cloak class="absolute inset-0 z-0 cursor-grab active:cursor-grabbing" aria-hidden="true"></div>

            @endif

            {{--
                Comandos: os pontos, que dizem quantas fotografias há, e duas setas
                só em ecrã largo — no telemóvel passa-se com o dedo, e as setas
                saíram a pedido da agência. Qualquer comando pára a rotação
                automática. Assentam por cima do aviso de cookies enquanto ele
                estiver no ecrã. Ficam em z-20, acima do bloco do texto (z-10): com a
                mesma camada, o texto vinha depois na página e a margem de baixo dele
                tapava-os — nem os pontos nem as setas chegavam a receber o toque.
            --}}
            @if (count($heroImages) > 1)
                <div x-cloak class="absolute bottom-6 right-5 z-20 flex items-center gap-1 sm:right-8 lg:right-12 2xl:right-20"
                     x-data="consentOffset(16)"
                     :style="{ bottom: 'calc(1.5rem + ' + offset + 'px)' }">
                    <button type="button" @click="anterior()"
                            class="hidden h-11 w-9 place-items-center text-sand-50/70 transition-colors duration-300 hover:text-sand-50 focus-visible:text-sand-50 lg:grid">
                        <span class="sr-only">{{ __('ui.home.photo_prev') }}</span>
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 6l-6 6 6 6"/>
                        </svg>
                    </button>

                    <div class="flex gap-2.5 px-1">
                        @foreach ($heroImages as $i => $imagem)
                            <button type="button" @click="ir({{ $i }})"
                                    class="grid h-11 w-6 place-items-center"
                                    :aria-current="atual === {{ $i }} ? 'true' : 'false'"
                                    aria-label="{{ __('ui.home.photo_n', ['n' => $i + 1, 'total' => count($heroImages)]) }}">
                                <span class="block h-1.5 rounded-full bg-sand-50 transition-all duration-500"
                                      :class="atual === {{ $i }} ? 'w-6 opacity-100' : 'w-1.5 opacity-50'"></span>
                            </button>
                        @endforeach
                    </div>

                    <button type="button" @click="seguinte()"
                            class="hidden h-11 w-9 place-items-center text-sand-50/70 transition-colors duration-300 hover:text-sand-50 focus-visible:text-sand-50 lg:grid">
                        <span class="sr-only">{{ __('ui.home.photo_next') }}</span>
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m10 6 6 6-6 6"/>
                        </svg>
                    </button>
                </div>
            @endif
        @endif

        {{-- Acima da camada de arrastar, para o texto continuar a poder seleccionar-se. --}}
        <div class="container-site relative z-10 pb-16 pt-32 sm:pb-24" data-texto-abertura>
            <x-site.reveal tipo="fade">
                <p @class(['eyebrow', 'text-sand-200' => $heroImage])>{{ __('ui.home.eyebrow') }}</p>
            </x-site.reveal>
            <x-site.reveal atraso="120">
                <h1 class="display mt-3 max-w-[16ch]">{{ __('ui.home_sections.hero_title') }}</h1>
            </x-site.reveal>
            <x-site.reveal atraso="260">
                <p @class(['mt-8 max-w-md text-base leading-relaxed', 'text-sand-100' => $heroImage, 'text-ink-muted' => ! $heroImage])>{{ __('ui.home_sections.hero_lead') }}</p>
            </x-site.reveal>
        </div>
    </section>

    {{-- 2. Declaração: o parágrafo grande que diz ao que vimos --}}
    <section class="container-site py-24 sm:py-32">
        <x-site.reveal>
            <p class="eyebrow">{{ __('ui.home_sections.statement_eyebrow') }}</p>
        </x-site.reveal>
        <x-site.reveal atraso="120">
            {{-- O <em> vem do ficheiro de idioma (nosso), para as palavras que interessam ficarem em itálico. --}}
            <p class="editorial mt-6 max-w-4xl">{!! __('ui.home_sections.statement') !!}</p>
        </x-site.reveal>
    </section>

    {{--
        3. Composição assimétrica: duas fotografias desencontradas, com movimento
        lento. Vêm de config/agency.php (arquivo, decorativas); sem lista, usam-se
        as capas dos destaques. Ocupam a grelha da página — a grande encostada à
        margem esquerda, a pequena à direita, com uma coluna de folga entre elas.
        Numa medida própria, mais estreita, ficavam a boiar no meio do ecrã,
        desalinhadas do texto que vem por cima.
    --}}
    @php
        $composicao = collect(config('agency.story_images', []))->filter()->values();
        if ($composicao->count() < 2) {
            $composicao = $featured->take(2)->pluck('cover_photo.url')->filter()->values();
        }
    @endphp
    @if ($composicao->count() === 2)
        <section class="container-site pb-24 sm:pb-32">
            {{--
                Em telemóvel, tocar numa das fotografias troca-lhes o tamanho: a
                pequena passa a grande e a grande a pequena, com uma transição
                lenta (a grelha passa de 2fr 1fr a 1fr 2fr — ver .composicao em
                app.css). Em ecrã largo a composição é fixa e o toque não faz nada.
                x-on:/x-bind: em vez de @/: porque nos componentes Blade esses
                prefixos são lidos pelo próprio Blade.
            --}}
            <div class="composicao grid items-end gap-6 lg:grid-cols-12 lg:gap-8"
                 x-data="{ trocadas: false, trocar() { if (window.matchMedia('(min-width: 64rem)').matches) return; this.trocadas = ! this.trocadas } }"
                 x-bind:class="trocadas && 'is-trocada'">
                <x-site.reveal tipo="wipe" class="cursor-pointer lg:col-span-7 lg:cursor-auto" x-on:click="trocar()">
                    <div class="parallax-frame relative aspect-4/5 sm:aspect-3/2">
                        <img src="{{ $composicao[0] }}" alt="" width="1600" height="1067" loading="lazy" decoding="async"
                             data-parallax="0.1" class="absolute inset-x-0 w-full object-cover">
                    </div>
                </x-site.reveal>
                {{-- A pequena entra um pouco depois da grande, para não abrirem em bloco. --}}
                <x-site.reveal tipo="wipe" atraso="200" class="cursor-pointer lg:col-start-9 lg:col-span-4 lg:-mb-16 lg:cursor-auto" x-on:click="trocar()">
                    <div class="parallax-frame relative aspect-square">
                        <img src="{{ $composicao[1] }}" alt="" width="900" height="900" loading="lazy" decoding="async"
                             data-parallax="0.22" class="absolute inset-x-0 w-full object-cover">
                    </div>
                </x-site.reveal>
            </div>
        </section>
    @endif

    {{-- 4. Destaques --}}
    @if ($featured->isNotEmpty())
        <section class="container-site pb-24 sm:pb-32">
            <div class="flex flex-wrap items-end justify-between gap-6 border-b border-sand-200 pb-6">
                <x-site.reveal>
                    <p class="eyebrow">{{ config('agency.name') }}</p>
                    <h2 class="display-sm mt-2">{{ __('ui.home_sections.featured') }}</h2>
                </x-site.reveal>
                <a href="{{ route('buy') }}" class="link text-sm">{{ __('ui.home_sections.featured_all') }}</a>
            </div>
            <div class="mt-12 grid gap-x-6 gap-y-12 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4" data-reveal-stagger="150">
                @foreach ($featured as $i => $property)
                    <x-site.reveal>
                        <x-property.card :property="$property" :eager="$i < 3" />
                    </x-site.reveal>
                @endforeach
            </div>
        </section>
    @endif

    {{-- 5. Faixa de cor: porquê a Multifuturo, com os números reais da carteira --}}
    <section class="band band-tan">
        <div class="container-site">
            <x-site.reveal>
                <p class="eyebrow">{{ __('ui.home_sections.why') }}</p>
                <h2 class="editorial mt-6 max-w-4xl">{!! __('ui.home_sections.why_statement') !!}</h2>
            </x-site.reveal>

            <div class="mt-20 grid gap-12 sm:grid-cols-3" data-reveal-stagger="180">
                @foreach ([1, 2, 3] as $n)
                    <x-site.reveal>
                        <span class="flex items-center gap-3" aria-hidden="true">
                            <span class="font-serif text-sm italic text-olive-700">0{{ $n }}</span>
                            <span class="h-px w-12 bg-olive-600/50"></span>
                        </span>
                        <h3 class="mt-5 font-serif text-xl">{{ __("ui.home_sections.why_{$n}_title") }}</h3>
                        <p class="mt-3 text-sm leading-relaxed text-ink-muted">{{ __("ui.home_sections.why_{$n}_text") }}</p>
                    </x-site.reveal>
                @endforeach
            </div>

            {{-- Números que contam ao entrar no ecrã. São a carteira real, não promessas. --}}
            <div class="mt-20 grid gap-10 border-t border-ink/10 pt-12 sm:grid-cols-3" data-reveal-stagger="160">
                @foreach ($stats as $chave => $valor)
                    <x-site.reveal class="text-center">
                        <p class="stat"><span data-count="{{ $valor }}">0</span></p>
                        <p class="mt-2 text-sm text-ink-muted">{{ trans_choice("ui.home_sections.stat_{$chave}", $valor) }}</p>
                    </x-site.reveal>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 6. Zonas --}}
    @if ($cities->isNotEmpty())
        <section class="container-site py-24 sm:py-32">
            <div class="flex flex-wrap items-end justify-between gap-6 border-b border-sand-200 pb-6">
                <x-site.reveal>
                    <h2 class="display-sm">{{ __('ui.home_sections.zones') }}</h2>
                </x-site.reveal>
                <a href="{{ route('zones.index') }}" class="link text-sm">{{ __('ui.home_sections.zones_all') }}</a>
            </div>
            <ul class="mt-4" data-reveal-stagger="100">
                @foreach ($cities->take(8) as $c)
                    <x-site.reveal as="li" class="border-b border-sand-200">
                        <a href="{{ route('zones.city', $c['slug']) }}" class="group flex items-baseline justify-between gap-6 py-5 transition-colors hover:text-olive-700">
                            <span class="font-serif text-2xl uppercase tracking-tight sm:text-4xl">{{ $c['name'] }}</span>
                            <span class="flex items-center gap-4 text-xs text-ink-muted">
                                {{ $c['count'] }}
                                <svg class="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 12h16m0 0-6-6m6 6-6 6"/></svg>
                            </span>
                        </a>
                    </x-site.reveal>
                @endforeach
            </ul>
        </section>
    @endif

</x-layouts.app>
