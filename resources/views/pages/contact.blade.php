{{--
    Contactos: as formas de falar com a agência e o mapa do escritório. Sem
    formulário e sem promessa de prazo de resposta — as duas coisas saíram a
    pedido da agência.

    Tudo o que aparece vem de config('agency') — nada está escrito aqui. Uma
    linha sem valor no .env não deixa buraco nenhum: simplesmente não aparece.
--}}
@php
    $morada = (string) config('agency.address');
    $telefone = (string) config('agency.phone');
    $whatsapp = (string) config('agency.whatsapp');
    $email = (string) config('agency.email');

    // "+351 912 178 876" → "351912178876", que é o que os endereços tel: e
    // wa.me querem: só dígitos, sem espaços nem sinais.
    $digitos = fn (string $n) => preg_replace('/\D+/', '', $n);

    $lat = config('agency.lat');
    $lon = config('agency.lon');
    $temMapa = is_numeric($lat) && is_numeric($lon);

    // O mesmo Leaflet da ficha do imóvel: servido do nosso storage, só os
    // quadrados do mapa é que vêm do openstreetmap.org.
    $leaflet = [
        'css' => asset('vendor/leaflet/leaflet.css'),
        'js' => asset('vendor/leaflet/leaflet.js'),
        'icon' => asset('vendor/leaflet/images/marker-icon.png'),
        'icon2x' => asset('vendor/leaflet/images/marker-icon-2x.png'),
        'shadow' => asset('vendor/leaflet/images/marker-shadow.png'),
    ];

    $linhas = array_values(array_filter([
        $morada === '' ? null : [
            'rotulo' => __('ui.contact.address'),
            'texto' => $morada,
            'url' => $temMapa
                ? "https://www.openstreetmap.org/?mlat={$lat}&mlon={$lon}#map=17/{$lat}/{$lon}"
                : null,
            'externo' => true,
            'icone' => 'casa',
        ],
        $telefone === '' ? null : [
            'rotulo' => __('ui.contact.phone'),
            'texto' => $telefone,
            'url' => 'tel:'.$digitos($telefone),
            'externo' => false,
            'icone' => 'telefone',
        ],
        $whatsapp === '' ? null : [
            'rotulo' => __('ui.contact.whatsapp'),
            'texto' => $whatsapp,
            'url' => 'https://wa.me/'.$digitos($whatsapp),
            'externo' => true,
            'icone' => 'whatsapp',
        ],
        $email === '' ? null : [
            'rotulo' => __('ui.contact.email'),
            'texto' => $email,
            'url' => 'mailto:'.$email,
            'externo' => false,
            'icone' => 'email',
        ],
    ]));
@endphp
<x-layouts.app :title="__('ui.nav.contact')" :canonical="route('contact')">

    {{-- Abertura: só o título. A promessa de resposta saiu a pedido da agência. --}}
    <section class="container-site pt-14 pb-10 sm:pt-20">
        <x-site.reveal>
            <p class="eyebrow">{{ config('agency.name') }}</p>
            <h1 class="display mt-3">{{ __('ui.nav.contact') }}</h1>
        </x-site.reveal>
    </section>

    {{--
        Contactos à esquerda, mapa à direita. Em ecrã estreito o mapa vai para
        baixo: quem chega ao telemóvel quer primeiro o número, não o mapa.
    --}}
    <section class="container-site pb-14 sm:pb-16">
        <div class="grid gap-10 border-t border-sand-200 pt-10 lg:grid-cols-2 lg:gap-16">
            <x-site.reveal>
                <h2 class="label">{{ __('ui.contact.info') }}</h2>

                <ul class="mt-8 space-y-7">
                    @foreach ($linhas as $linha)
                        <li class="flex items-start gap-4">
                            <span class="mt-0.5 shrink-0 text-olive-600" aria-hidden="true">
                                @switch($linha['icone'])
                                    @case('casa')
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5Z"/></svg>
                                        @break
                                    @case('telefone')
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 5.5c0-.8.7-1.5 1.5-1.5h2.2c.6 0 1.2.4 1.4 1l.9 2.7c.2.5 0 1.1-.4 1.5l-1.3 1a13 13 0 0 0 5 5l1-1.3c.4-.4 1-.6 1.5-.4l2.7.9c.6.2 1 .8 1 1.4V18c0 .8-.7 1.5-1.5 1.5h-.5C10.5 19.5 4.5 13.5 4.5 6v-.5Z"/></svg>
                                        @break
                                    @case('whatsapp')
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2Zm0 1.8a8.2 8.2 0 1 1-4.2 15.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 0 1 12 3.8Zm-3.1 4c-.2 0-.5 0-.7.4-.3.3-.9.9-.9 2.1s1 2.4 1.1 2.6c.1.2 1.8 2.9 4.5 3.9 2.2.9 2.7.7 3.2.6.5 0 1.5-.6 1.7-1.2.2-.6.2-1.1.1-1.2 0-.1-.2-.2-.5-.3l-1.7-.9c-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.6.1a6.7 6.7 0 0 1-3.3-2.9c-.2-.3 0-.5.1-.6l.5-.5.3-.6c.1-.2 0-.4 0-.5l-.8-1.8c-.2-.5-.4-.4-.6-.4Z"/></svg>
                                        @break
                                    @default
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6.5h18v11H3v-11Zm0 .5 9 6 9-6"/></svg>
                                @endswitch
                            </span>
                            <span class="min-w-0">
                                @if ($linha['url'])
                                    <a class="link wrap-break-word text-lg leading-relaxed"
                                        href="{{ $linha['url'] }}"
                                        @if ($linha['externo']) target="_blank" rel="noopener" @endif
                                    >{{ $linha['texto'] }}</a>
                                @else
                                    <span class="wrap-break-word text-lg leading-relaxed">{{ $linha['texto'] }}</span>
                                @endif
                                <span class="mt-1 block text-sm text-ink-muted">{{ $linha['rotulo'] }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </x-site.reveal>

            @if ($temMapa)
                <x-site.reveal atraso="120">
                    <h2 class="label">{{ __('ui.contact.map') }}</h2>
                    <div x-data="propertyMap(@js($leaflet), {{ (float) $lat }}, {{ (float) $lon }})"
                        class="mt-8 overflow-hidden rounded-xl border border-sand-200 bg-sand-100">
                        <div x-ref="map" class="h-80 w-full sm:h-104" role="img" aria-label="{{ __('ui.contact.map') }}" data-map></div>
                        {{-- Sem JavaScript fica a ligação, que é o que interessa a quem quer chegar cá. --}}
                        <noscript>
                            <p class="p-4 text-sm">
                                <a class="link" href="https://www.openstreetmap.org/?mlat={{ $lat }}&mlon={{ $lon }}#map=17/{{ $lat }}/{{ $lon }}" rel="noopener" target="_blank">OpenStreetMap</a>
                            </p>
                        </noscript>
                    </div>
                    <p class="mt-4">
                        <a class="link text-sm" href="https://www.google.com/maps/dir/?api=1&destination={{ $lat }},{{ $lon }}" target="_blank" rel="noopener">{{ __('ui.contact.directions') }}</a>
                    </p>
                </x-site.reveal>
            @endif
        </div>
    </section>

    {{--
        Não há formulário nesta página, por decisão da agência: quem chega aqui
        tem o telemóvel, o WhatsApp e o email à vista, e é por aí que se fala
        connosco. O formulário continua na ficha de cada imóvel, que é onde a
        pergunta costuma nascer.
    --}}
</x-layouts.app>
