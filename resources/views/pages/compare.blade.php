<x-layouts.app :title="__('ui.compare.title')" robots="noindex,follow">
    {{--
        Comparador. Sem ?slugs= no URL, o Alpine lê o localStorage e recarrega a
        página com os slugs escolhidos; sem JavaScript mostra-se o estado vazio,
        porque a escolha vive no browser por definição.

        Com ?slugs=, o servidor devolve só os imóveis publicados — e a página poda
        da lista os que ficaram pelo caminho (vendidos, retirados, apagados).
    --}}
    <section class="container-site pb-24 pt-20 sm:pt-28"
             x-data="{ init() {
                 @if ($requested)
                     $store.compare.prune(@js($properties->pluck('slug')->all()));
                 @else
                     const s = $store.compare.slugs;
                     if (s.length) { window.location.replace(@js(route('compare')) + '?slugs=' + encodeURIComponent(s.join(','))); }
                 @endif
             } }">
        <x-site.reveal>
            <p class="eyebrow">{{ config('agency.name') }}</p>
            <h1 class="display-sm mt-3">{{ __('ui.compare.title') }}</h1>
        </x-site.reveal>
        <p class="mt-4 max-w-xl text-ink-muted">{{ __('ui.compare.lead') }}</p>

        @if ($properties->count() < 2)
            <div class="mt-12 rounded-xl border border-sand-200 bg-sand-100 px-6 py-16 text-center">
                <p class="text-lg">{{ $properties->isEmpty() ? __('ui.compare.empty') : __('ui.compare.need_two') }}</p>
                <a href="{{ route('buy') }}" class="btn-primary mt-6">{{ __('ui.nav.buy') }}</a>
            </div>
        @else
            {{--
                Uma grelha em vez de uma tabela larga. No telemóvel, os imóveis ficam
                lado a lado em colunas iguais, sem deslizar para o lado; cada linha
                comparada leva o rótulo por cima, a toda a largura, e os valores por
                baixo, cada um alinhado com a coluna do seu imóvel. A partir de ecrã
                largo, o rótulo passa para uma coluna à esquerda, como numa tabela.

                Antes era uma tabela com 704 px de largura mínima e a coluna dos
                rótulos presa à esquerda: no telemóvel ela comia quase metade do ecrã
                e cada imóvel aparecia cortado.

                As classes da grelha estão escritas por inteiro para o Tailwind as
                encontrar. Os papéis ARIA mantêm a leitura de tabela nos leitores de
                ecrã.
            --}}
            @php
                $grelha = $properties->count() === 2
                    ? 'grid-cols-2 lg:grid-cols-[12rem_repeat(2,minmax(0,1fr))]'
                    : 'grid-cols-3 lg:grid-cols-[12rem_repeat(3,minmax(0,1fr))]';
            @endphp
            <div class="mt-12" role="table" aria-label="{{ __('ui.compare.title') }}" data-comparador>
                <div role="row" class="grid {{ $grelha }} gap-x-3 sm:gap-x-6 lg:gap-x-8">
                    <div role="columnheader" class="col-span-full pb-4 lg:col-span-1 lg:self-end lg:pb-6">
                        <span class="label">{{ trans_choice('ui.compare.count', $properties->count(), ['count' => $properties->count()]) }}</span>
                    </div>
                    @foreach ($properties as $p)
                        @php $titulo = $p->title ?: trim(($p->property_type ?? '').' '.(\App\Support\Format::typology($p->bedrooms) ?? '')); @endphp
                        <div role="columnheader" class="min-w-0 pb-6" data-coluna-imovel>
                            <a href="{{ route('property.show', $p) }}" class="block">
                                <x-property.image :src="$p->cover_photo['url'] ?? null" :alt="$titulo" ratio="4/3" class="rounded-xl" sizes="(min-width: 1024px) 28vw, 45vw" />
                                <span class="label mt-3 block">{{ __('ui.property.reference') }} {{ $p->reference ?? $p->internal_id }}</span>
                                {{-- Três linhas no máximo: os títulos da agência são compridos e as colunas no telemóvel são estreitas. --}}
                                <span class="mt-1 line-clamp-3 text-sm leading-snug hover:underline sm:text-base">{{ $titulo }}</span>
                            </a>
                            <button type="button" x-cloak x-data class="link mt-2 text-left text-xs"
                                    @click="$store.compare.toggle(@js($p->slug)); window.location.href = @js(route('compare'))">
                                {{ __('ui.compare.remove') }}
                            </button>
                        </div>
                    @endforeach
                </div>

                @foreach ($rows as $label => $valores)
                    <div role="row" class="grid {{ $grelha }} gap-x-3 border-t border-sand-200 py-3 sm:gap-x-6 lg:gap-x-8">
                        <div role="rowheader" class="label col-span-full pb-1.5 lg:col-span-1 lg:pb-0 lg:text-sm lg:normal-case lg:tracking-normal">{{ $label }}</div>
                        @foreach ($valores as $valor)
                            {{-- As comodidades chegam uma por linha; cada linha começa por
                                 maiúscula, como na ficha do imóvel. --}}
                            <div role="cell" class="min-w-0 wrap-break-word text-sm {{ $valor === null ? 'text-ink-muted' : '' }}">
                                @foreach (explode("\n", $valor ?? '—') as $linha)
                                    <span class="block first-letter:uppercase">{{ $linha }}</span>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>

            <div class="mt-10 flex flex-wrap gap-3">
                <a href="{{ route('buy') }}" class="btn-secondary">{{ __('ui.compare.add_more') }}</a>
                <button type="button" x-cloak x-data class="btn-secondary"
                        @click="$store.compare.clear(); window.location.href = @js(route('compare'))">
                    {{ __('ui.compare.clear') }}
                </button>
            </div>
        @endif
    </section>
</x-layouts.app>
