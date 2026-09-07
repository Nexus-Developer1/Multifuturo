@php
    use App\Support\Format;
    $p = $property;
    $title = $p->title ?: trim(($p->property_type ?? '').' '.(Format::typology($p->bedrooms) ?? ''));
    $tipo = $p->property_type ?: $title;
    $location = Format::location($p->locality, $p->city, $p->district);
    $metaTitle = $title.($location ? " — {$location}" : '').' · '.($p->reference ?? $p->internal_id);
    // Meta description: a Descrição SEO escrita à mão → descrição curta → início da descrição → dados da ficha.
    $texto = $p->description ?: strip_tags((string) $p->website_html);
    $metaDescription = $p->seo_description
        ?: ($p->short_description
        ?: ($texto ? mb_substr(preg_replace('/\s+/', ' ', strip_tags($texto)), 0, 155) : ($title.', '.$location.'. '.Format::price($p->price, $p->currency, $p->business_type, $p->price_visible))));

    // Cartão lateral, pela ordem da referência: Tipologia, Quarto(s), WCs, áreas, ano, certificado.
    $tipologia = ($p->typology && $p->typology !== 'Não aplicável') ? $p->typology : Format::typology($p->bedrooms);
    $ficha = array_filter([
        __('ui.property.typology') => $tipologia,
        __('ui.property.bedrooms_count') => $p->bedrooms,
        __('ui.property.wc') => $p->bathrooms,
        __('ui.property.house_area') => Format::area($p->house_area),
        __('ui.property.gross_area') => Format::area($p->gross_area),
        __('ui.property.plot_area') => Format::area($p->plot_area),
        __('ui.property.floor') => $p->floor_number,
        __('ui.property.build_year') => $p->build_year,
        __('ui.property.condition') => $p->property_condition,
    ], fn ($v) => $v !== null && $v !== '');

    $ami = config('agency.ami');
    $coords = $p->coordinates;
    $leaflet = [
        'css' => asset('vendor/leaflet/leaflet.css'),
        'js' => asset('vendor/leaflet/leaflet.js'),
        'icon' => asset('vendor/leaflet/images/marker-icon.png'),
        'icon2x' => asset('vendor/leaflet/images/marker-icon-2x.png'),
        'shadow' => asset('vendor/leaflet/images/marker-shadow.png'),
    ];
@endphp
<x-layouts.app :title="$metaTitle" :description="$metaDescription" :keywords="$p->keywords()" :canonical="route('property.show', $p)" :image="$p->coverPhotoUrl()">
    <x-slot:head>
        <meta property="og:type" content="product">
        <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    </x-slot:head>

    <article class="container-site pt-8 pb-24">
        {{--
            Primeira linha da ficha: migalhas à esquerda e o regresso à lista à
            direita, debaixo dos botões do cabeçalho — é onde se procura a saída,
            não no fim da página.
        --}}
        <div class="flex flex-wrap items-center justify-between gap-4 print:hidden">
            <nav aria-label="Breadcrumb" class="text-xs text-ink-muted">
                <ol class="flex flex-wrap gap-2">
                    <li><a href="{{ route('home') }}" class="hover:text-ink">Início</a></li>
                    <li aria-hidden="true">/</li>
                    <li><a href="{{ route($p->business_type->routeName()) }}" class="hover:text-ink">{{ $p->business_type->routeName() === 'buy' ? __('ui.nav.buy') : __('ui.nav.rent') }}</a></li>
                    @if ($p->city)
                        <li aria-hidden="true">/</li>
                        <li><a href="{{ route('zones.city', \Illuminate\Support\Str::slug($p->city)) }}" class="hover:text-ink">{{ $p->city }}</a></li>
                    @endif
                    <li aria-hidden="true">/</li>
                    <li aria-current="page">{{ $p->reference ?? $p->internal_id }}</li>
                </ol>
            </nav>

            <a href="{{ route($p->business_type->routeName(), ['concelho' => $p->city]) }}"
               class="inline-flex shrink-0 items-center gap-2 whitespace-nowrap rounded-md border border-sand-300 px-4 py-2 text-[0.8rem] tracking-wide text-ink transition-colors hover:border-ink hover:bg-ink hover:text-sand-50">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 6l-6 6 6 6"/>
                </svg>
                {{ __('ui.property.back_to_list') }}
            </a>
        </div>

        <div class="mt-6">
            <x-property.gallery :photos="$p->photos ?? []" :title="$title" />
        </div>

        {{--
            Cabeçalho da ficha, como na referência: tipo e concelho à esquerda,
            o tipo de negócio e a freguesia; preço e referência à direita; linha a fechar.
        --}}
        <header class="mt-10 flex flex-wrap items-start justify-between gap-x-10 gap-y-6 border-b border-sand-200 pb-6">
            <div>
                <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1">
                    <h1 class="display-sm">{{ $tipo }}</h1>
                    @if ($p->city)
                        <span class="text-lg font-medium">{{ $p->city }}</span>
                    @endif
                </div>
                <p class="label mt-2">
                    {{ $p->business_type->label() }}
                    @if ($location && $location !== $p->city) · {{ $location }} @endif
                    @if ($p->is_exclusive) · <span class="text-olive-700">{{ __('ui.property.exclusive') }}</span> @endif
                </p>
            </div>
            <div class="text-right">
                <p class="price text-3xl sm:text-4xl">{{ Format::price($p->price, $p->currency, $p->business_type, $p->price_visible) }}</p>
                <p class="mt-1 text-sm text-ink-muted">{{ __('ui.property.reference') }} {{ $p->reference ?? $p->internal_id }}</p>
            </div>
        </header>

        {{--
            Em ecrã largo: título e atalhos à esquerda, cartão de dados à direita. Em
            telemóvel o cartão vem primeiro, para os dados essenciais ficarem logo a
            seguir ao cabeçalho. Tudo o resto — características, texto, mapa e pedido
            de informação — segue em bandas à largura da página.
        --}}
        <div class="mt-10 grid gap-x-12 gap-y-8 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="order-2 lg:order-none lg:col-start-1 lg:row-start-1">
                <h2 class="font-serif text-2xl leading-snug sm:text-3xl">{{ $title }}</h2>

                @if ($p->short_description)
                    <p class="mt-5 max-w-2xl text-[15px] leading-relaxed text-ink/90">{{ $p->short_description }}</p>
                @endif

                {{-- Só as visitas ao imóvel: guardar e partilhar saíram da ficha. --}}
                @if ($p->virtual_tour_url || $p->video_url || $p->floorplan_url)
                <div class="mt-6 flex flex-wrap gap-3 print:hidden">
                    @if ($p->virtual_tour_url)
                        <a href="{{ $p->virtual_tour_url }}" rel="noopener nofollow" target="_blank" class="btn-secondary py-2 text-xs">{{ __('ui.property.virtual_tour') }}</a>
                    @endif
                    @if ($p->video_url)
                        <a href="{{ $p->video_url }}" rel="noopener nofollow" target="_blank" class="btn-secondary py-2 text-xs">{{ __('ui.property.video') }}</a>
                    @endif
                    @if ($p->floorplan_url)
                        <a href="{{ $p->floorplan_url }}" rel="noopener nofollow" target="_blank" class="btn-secondary py-2 text-xs">{{ __('ui.property.floorplan') }}</a>
                    @endif
                </div>
                @endif
            </div>

            {{-- Cartão de dados, como na referência: rótulo a negrito e valor ao lado. --}}
            <div class="order-1 lg:order-none lg:col-start-2 lg:row-start-1">
                @if ($ficha || $p->energy_rating)
                    <dl class="rounded-xl border border-sand-200 bg-white px-8 py-8 text-[15px] leading-6 sm:px-10" data-testid="ficha">
                        @foreach ($ficha as $label => $value)
                            <div class="flex items-baseline gap-2.5 py-2">
                                <dt class="font-semibold">{{ $label }}:</dt>
                                <dd class="text-ink/90">{{ $value }}</dd>
                            </div>
                        @endforeach
                        @if ($p->energy_rating)
                            <div class="flex items-center gap-2.5 py-1.5">
                                <dt class="font-semibold">{{ __('ui.property.energy_certificate') }}:</dt>
                                <dd><x-property.energy-badge :rating="$p->energy_rating" /></dd>
                            </div>
                        @endif
                        @if (filled($ami))
                            <div class="pt-2 text-xs text-ink">{{ __('ui.property.ami') }}: {{ $ami }}</div>
                        @endif
                    </dl>
                @endif
            </div>

        </div>

        {{--
            Características e informações adicionais lado a lado, à largura da
            página: à esquerda a lista arrumada por grupos (o CRM manda-a corrida,
            trinta itens seguidos não se leem), à direita o texto do imóvel.
        --}}
        @if ($p->features || $p->website_html || $p->description)
            <section class="mt-16 grid gap-x-16 gap-y-12 border-t border-sand-200 pt-12 lg:grid-cols-2">
                @if ($p->features)
                    <div>
                        <h2 class="display-sm text-2xl!">{{ __('ui.property.characteristics') }}</h2>
                        {{--
                            Colunas de texto, não grelha: os grupos têm alturas muito
                            diferentes e numa grelha as linhas alinhavam-se pela mais
                            alta, abrindo buracos entre elas.
                        --}}
                        <div class="mt-6 gap-x-10 sm:columns-2">
                            @foreach (\App\Support\Features::grouped($p->features) as $grupo => $itens)
                                <div class="mb-9 break-inside-avoid">
                                    <h3 class="label">{{ __('ui.property.feature_groups.'.$grupo) }}</h3>
                                    <ul class="mt-3 space-y-2 text-sm">
                                        @foreach ($itens as $feature)
                                            <li class="flex items-start gap-2.5">
                                                <svg class="mt-1 h-3.5 w-3.5 shrink-0 text-olive-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4 12.5 5 5L20 6.5"/></svg>
                                                <span class="first-letter:uppercase">{{ $feature }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($p->website_html || $p->description)
                    <div>
                        <h2 class="display-sm text-2xl!">{{ __('ui.property.additional_info') }}</h2>
                        <div class="prose-multifuturo mt-6 max-w-2xl text-[15px] leading-relaxed text-ink/90">
                            {{-- O texto "Website (HTML)" manda quando existe; passa pelo limpador — só formatação de texto. --}}
                            @if ($p->website_html)
                                {!! \App\Support\Html::clean($p->website_html) !!}
                            @else
                                {!! \App\Support\Html::paragraphs($p->description) !!}
                            @endif
                        </div>
                    </div>
                @endif
            </section>
        @endif

        {{--
            Mapa: só se gmap_visible (compromisso contratual). Aparece logo ao abrir
            a ficha: o Leaflet vem do nosso storage e só os quadrados do mapa vêm
            do openstreetmap.org. Rodapé reduzido à linha que a licença exige.
        --}}
        <section id="mapa" class="mt-16 scroll-mt-8 print:hidden">
            <h2 class="display-sm text-2xl!">{{ __('ui.property.map') }}</h2>
            @if ($coords)
                @php $lat = (float) $coords['lat']; $lon = (float) $coords['lon']; @endphp
                <div x-data="propertyMap(@js($leaflet), {{ $lat }}, {{ $lon }})"
                    class="mt-5 overflow-hidden rounded-xl border border-sand-200 bg-sand-100">
                    <div x-ref="map" class="h-96 w-full" role="img" aria-label="{{ __('ui.property.map') }}" data-map></div>
                    <noscript><p class="p-4 text-sm"><a class="link" href="https://www.openstreetmap.org/?mlat={{ $lat }}&mlon={{ $lon }}#map=16/{{ $lat }}/{{ $lon }}" rel="noopener" target="_blank">OpenStreetMap</a></p></noscript>
                </div>
            @else
                <p class="mt-4 text-sm text-ink-muted">{{ __('ui.property.map_hidden') }}</p>
            @endif
        </section>

        {{-- Pedido de informação, à largura da página. --}}
        <section class="mt-16 print:hidden">
            <x-lead-form source="property" :property="$p" wide>
                @if ($p->broker && ($p->broker['name'] ?? null))
                    <x-slot:aside>
                        <div class="flex max-w-sm items-center gap-4 rounded-xl border border-sand-200 bg-white px-5 py-4">
                            @if ($p->broker['photo'] ?? null)
                                <img src="{{ $p->broker['photo'] }}" alt="" width="56" height="56" class="h-14 w-14 rounded-full object-cover" loading="lazy" onerror="this.style.display='none'">
                            @endif
                            <div>
                                <p class="label">{{ __('ui.property.contact_broker') }}</p>
                                <p class="mt-1 font-medium">{{ $p->broker['name'] }}</p>
                            </div>
                        </div>
                    </x-slot:aside>
                @endif
            </x-lead-form>
        </section>

        @if ($similar->isNotEmpty())
            <section class="mt-24 print:hidden">
                <h2 class="display-sm">{{ __('ui.property.similar') }}</h2>
                <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($similar as $s)
                        <x-property.card :property="$s" />
                    @endforeach
                </div>
            </section>
        @endif

    </article>
</x-layouts.app>
