{{--
    A agência.

    O texto é o mesmo de lang/{idioma}/legal.php — não se mexe uma palavra. O
    que muda é a apresentação: em vez do molde das páginas legais (índice ao
    lado e uma coluna de texto corrido, que fazia isto parecer um documento),
    cada secção diz como quer ser mostrada, pela chave 'layout':

      prose      rótulo à esquerda, texto à direita, com a primeira frase em
                 corpo maior — a entrada da página
      steps      faixa clara, cada parágrafo numerado como as razões da página
                 inicial: cinco ideias distintas deixam de ser um bloco só
      statement  faixa escura, o texto grande que fecha a apresentação
      contacts   morada e contactos desenhados, com ligações para telefonar e
                 escrever, em vez das linhas de texto que as páginas legais usam

    Uma secção sem 'layout' cai em 'prose', para nada desaparecer se um dia se
    acrescentar outra ao ficheiro de idioma.
--}}
@php
    $doc = __("legal.{$key}");
    $t = fn (string $s) => trans_replace($s, $replacements);
    $telefone = (string) config('agency.phone');
    $email = (string) config('agency.email');
    $morada = (string) config('agency.address');
@endphp
<x-layouts.app :title="$doc['title']" :description="$t($doc['lead'])" :canonical="url()->current()">

    {{-- Abertura: o título ocupa a página e a frase de propósito fica ao lado, à altura da base. --}}
    <section class="container-site grid gap-8 pt-20 pb-16 sm:pt-28 lg:grid-cols-[3fr_2fr] lg:items-end lg:gap-16">
        <x-site.reveal>
            <p class="eyebrow">{{ config('agency.name') }}</p>
            <h1 class="display mt-3">{{ $doc['title'] }}</h1>
        </x-site.reveal>
        <x-site.reveal atraso="150" class="lg:pb-3">
            <p class="font-serif text-xl leading-snug text-balance sm:text-2xl">{{ $t($doc['lead']) }}</p>
        </x-site.reveal>
    </section>

    @foreach ($doc['sections'] as $section)
        @php $layout = $section['layout'] ?? 'prose'; @endphp

        @if ($layout === 'steps')
            <section class="band band-sand">
                <div class="container-site">
                    <x-site.reveal>
                        <p class="eyebrow">{{ config('agency.name') }}</p>
                        <h2 class="display-sm mt-3">{{ $section['title'] }}</h2>
                    </x-site.reveal>

                    {{-- Duas colunas em ecrã largo: cinco parágrafos numa só ficavam uma escada longa. --}}
                    <ol class="mt-14 grid gap-x-16 gap-y-12 lg:grid-cols-2" data-reveal-stagger="120">
                        @foreach ($section['paragraphs'] as $i => $paragraph)
                            <x-site.reveal as="li">
                                <span class="flex items-center gap-3" aria-hidden="true">
                                    <span class="font-serif text-sm italic text-olive-700">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                    <span class="h-px w-12 bg-olive-600/50"></span>
                                </span>
                                <p class="mt-4 leading-relaxed text-ink/90">{{ $t($paragraph) }}</p>
                            </x-site.reveal>
                        @endforeach
                    </ol>
                </div>
            </section>

        @elseif ($layout === 'statement')
            <section class="band band-dark">
                <div class="container-site">
                    <x-site.reveal>
                        <p class="eyebrow">{{ $section['title'] }}</p>
                    </x-site.reveal>
                    @foreach ($section['paragraphs'] as $paragraph)
                        <x-site.reveal atraso="{{ 120 + $loop->index * 120 }}">
                            {{-- O primeiro parágrafo é a declaração; o que vem a seguir fecha-a, em voz mais baixa. --}}
                            <p class="{{ $loop->first ? 'editorial mt-6 max-w-4xl' : 'mt-8 max-w-2xl leading-relaxed text-sand-200' }}">{{ $t($paragraph) }}</p>
                        </x-site.reveal>
                    @endforeach
                </div>
            </section>

        @elseif ($layout === 'contacts')
            <section class="container-site py-20 sm:py-24">
                <div class="grid gap-8 border-t border-sand-200 pt-12 lg:grid-cols-[minmax(0,14rem)_minmax(0,1fr)] lg:gap-16">
                    <h2 class="label">{{ $section['title'] }}</h2>
                    <x-site.reveal class="max-w-3xl">
                        @if ($morada !== '')
                            <p class="font-serif text-xl leading-relaxed sm:text-2xl">{{ $morada }}</p>
                        @endif
                        <div class="mt-6 flex flex-wrap gap-x-8 gap-y-2 text-lg">
                            @if ($telefone !== '')
                                <a class="link" href="tel:{{ preg_replace('/\s+/', '', $telefone) }}">{{ $telefone }}</a>
                            @endif
                            @if ($email !== '')
                                <a class="link" href="mailto:{{ $email }}">{{ $email }}</a>
                            @endif
                        </div>
                        <div class="mt-10 flex flex-wrap gap-4">
                            <a class="btn-primary" href="{{ route('contact') }}">{{ __('ui.nav.contact') }}</a>
                            <a class="btn-secondary" href="{{ route('buy') }}">{{ __('ui.home_sections.featured_all') }}</a>
                        </div>
                    </x-site.reveal>
                </div>
            </section>

        @else
            <section class="container-site pb-20 sm:pb-24">
                <div class="grid gap-8 border-t border-sand-200 pt-12 lg:grid-cols-[minmax(0,14rem)_minmax(0,1fr)] lg:gap-16">
                    <h2 class="label lg:sticky lg:top-8 lg:self-start">{{ $section['title'] }}</h2>
                    <x-site.reveal class="max-w-3xl">
                        @foreach ($section['paragraphs'] as $paragraph)
                            @continue (trim($t($paragraph)) === '')
                            {{-- A primeira frase entra em corpo maior: é ela que apresenta a agência. --}}
                            <p class="{{ $loop->first ? 'font-serif text-xl leading-relaxed sm:text-2xl' : 'mt-6 leading-relaxed text-ink/90' }}">{{ $t($paragraph) }}</p>
                        @endforeach
                    </x-site.reveal>
                </div>
            </section>
        @endif
    @endforeach

</x-layouts.app>
