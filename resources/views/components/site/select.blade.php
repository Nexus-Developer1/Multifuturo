{{--
    Campo de escolha com a lista desenhada por nós.

    O <select> real fica na página: é ele que guarda o valor, alimenta o
    Livewire e funciona sem JavaScript. Quando o Alpine arranca, esconde-se e
    aparecem o botão e a lista com o desenho do site (listbox, em
    resources/js/app.js). Se o Alpine não arrancar, fica o <select> de sempre.

    options: [valor => rótulo] ou uma lista simples (valor igual ao rótulo).
--}}
@props([
    'id',
    'name',
    'model',
    'label',
    // Sem marcador, não há opção vazia (é o caso da ordenação, que tem sempre valor).
    'placeholder' => null,
    'options' => [],
    'disabled' => false,
])
@php
    // Lista simples (0,1,2…) vale como valor = rótulo.
    $opcoes = array_is_list($options) ? array_combine($options, $options) : $options;
@endphp
{{-- x-on: em vez de @, senão o Blade lê "@livewire" como directiva sua. --}}
<div x-data="listbox" @keydown.escape.stop="fechar()" @click.outside="fechar()"
     x-on:livewire-atualizado.window="sincronizar()" class="relative">
    <label for="{{ $id }}" class="label" x-bind:class="pronto && 'pointer-events-none'">{{ $label }}</label>

    <select id="{{ $id }}" name="{{ $name }}" wire:model.live="{{ $model }}" x-ref="nativo"
            class="field-line select-chevron mt-1" :class="pronto && 'sr-only'" @disabled($disabled)>
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($opcoes as $valor => $rotulo)
            <option value="{{ $valor }}">{{ $rotulo }}</option>
        @endforeach
    </select>

    {{-- A partir daqui é só com JavaScript. --}}
    <button type="button" x-cloak x-show="pronto" x-ref="botao"
            @click="alternar()"
            @keydown.arrow-down.prevent="mover(1)"
            @keydown.arrow-up.prevent="mover(-1)"
            @keydown.enter.prevent="confirmar()"
            @keydown.space.prevent="confirmar()"
            :aria-expanded="aberto ? 'true' : 'false'"
            aria-label="{{ $label }}"
            :disabled="desactivado"
            aria-haspopup="listbox"
            class="field-line mt-1 flex items-center justify-between gap-2 text-left disabled:opacity-50">
        <span class="truncate" x-text="rotulo">{{ $placeholder ?? '' }}</span>
        <svg class="h-4 w-4 shrink-0 text-ink-muted transition-transform duration-200" :class="aberto && 'rotate-180'"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/>
        </svg>
    </button>

    <ul x-cloak x-show="aberto" x-ref="lista" role="listbox" :aria-label="@js($label)"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        @keydown.arrow-down.prevent="mover(1)"
        @keydown.arrow-up.prevent="mover(-1)"
        @keydown.home.prevent="activo = 0"
        @keydown.end.prevent="activo = opcoes.length - 1"
        class="absolute inset-x-0 top-full z-30 mt-2 max-h-72 overflow-y-auto rounded-lg border border-sand-200 bg-white py-1.5 shadow-xl shadow-ink/10">
        {{-- Chave pelo valor, não pelo índice: quando a lista muda (as freguesias
             mudam com o concelho), o Alpine tem de refazer os itens em vez de
             reaproveitar os antigos com o conteúdo trocado. --}}
        <template x-for="(opcao, i) in opcoes" :key="opcao.value">
            <li role="option" :aria-selected="indice === i ? 'true' : 'false'"
                @click="escolher(i)" @mouseenter="activo = i"
                class="flex cursor-pointer items-center justify-between gap-3 px-4 py-2.5 text-sm transition-colors"
                :class="{
                    'bg-sand-100': activo === i,
                    'text-olive-700 font-medium': indice === i,
                    'text-ink-muted': i === 0 && indice !== 0,
                }">
                <span class="truncate" x-text="opcao.text"></span>
                <svg x-show="indice === i" class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4 12.5 5 5L20 6.5"/>
                </svg>
            </li>
        </template>
    </ul>
</div>
