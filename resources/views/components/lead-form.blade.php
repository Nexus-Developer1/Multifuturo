{{--
    Formulário de lead — um componente para as duas origens:
      source="property"   ficha de imóvel (passar :property)
      source="contact"    contacto geral

    Server-rendered, funciona sem JavaScript. Anti-spam: honeypot "website"
    (escondido por CSS) + timestamp assinado "form_ts". RGPD: duas checkboxes
    separadas, ambas desmarcadas por defeito, e aviso com link para a política.

    Visual mínimo com os tokens da marca — a re-skinnar na Fase 4 com o layout final.
--}}
@props([
    // 'linha': campos só com um traço por baixo, para as faixas escuras.
    'tone' => 'cartao',
    'source' => 'contact',
    'property' => null,
    // À largura da página (ficha do imóvel): título à esquerda, campos à direita.
    // Numa coluna estreita continua tudo empilhado, como antes.
    'wide' => false,
])

@php
    $isProperty = $source === 'property' && $property;
    $defaultMessage = $isProperty ? __('ui.lead.message_property', ['reference' => $property->reference ?? $property->internal_id]) : '';
    $formId = 'lead-'.$source.($property?->id ? '-'.$property->id : '');
@endphp

@php $campo = $tone === 'linha' ? 'field-line' : 'field'; @endphp
<div {{ $attributes->merge(['class' => $tone === 'linha' ? '' : 'rounded-xl bg-sand-100 border border-sand-200 p-6 sm:p-7']) }} id="{{ $formId }}">

    <div @class(['grid items-start gap-8 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,2.4fr)] lg:gap-12' => $wide])>
    <div>
    <h2 class="text-2xl">{{ __('ui.lead.title_'.$source) }}</h2>
    <p class="mt-2 text-sm text-ink-muted">{{ __('ui.lead.lead_'.$source) }}</p>
    @if ($wide && $isProperty)
        <p class="mt-4 text-sm text-ink-muted">{{ __('ui.property.reference') }} {{ $property->reference ?? $property->internal_id }}</p>
    @endif
    {{-- Coluna da esquerda em modo largo: é onde entra o consultor do imóvel. --}}
    @isset($aside)
        <div class="mt-6">{{ $aside }}</div>
    @endisset
    </div>

    <div>
    @if (session('lead_sent'))
        <p class="border-l-2 border-olive-600 bg-sand-50 px-4 py-3 text-sm text-ink" role="status">{{ __('ui.lead.success') }}</p>
    @endif

    @if ($errors->any())
        <p class="border-l-2 border-error bg-sand-50 px-4 py-3 text-sm text-error" role="alert">{{ __('ui.lead.error') }}</p>
    @endif

    <form method="post" action="{{ route('leads.store') }}" @class(['grid gap-5 mt-6', 'lg:mt-0 gap-4!' => $wide]) novalidate>
        @csrf
        <input type="hidden" name="source" value="{{ $source }}">
        <input type="hidden" name="form_ts" value="{{ \App\Http\Requests\StoreLeadRequest::signedTimestamp() }}">
        @if ($isProperty)
            <input type="hidden" name="property_slug" value="{{ $property->slug }}">
        @endif

        {{-- Honeypot: invisível para humanos; bots preenchem. Fora do fluxo de tab. --}}
        <div class="absolute -left-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
            <label for="{{ $formId }}-website">Website</label>
            <input type="text" id="{{ $formId }}-website" name="website" tabindex="-1" autocomplete="off">
        </div>

        {{-- Em modo largo os três campos curtos cabem numa linha só. --}}
        <div @class(['grid gap-5 sm:grid-cols-2', 'lg:grid-cols-3 gap-4!' => $wide])>
            <div>
                <label for="{{ $formId }}-name" class="label">{{ __('ui.lead.name') }}</label>
                <input id="{{ $formId }}-name" name="name" type="text" required autocomplete="name" value="{{ old('name') }}" class="{{ $campo }} mt-2 @error('name') border-error @enderror">
                @error('name')<p class="mt-1 text-xs text-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="{{ $formId }}-email" class="label">{{ __('ui.lead.email') }}</label>
                <input id="{{ $formId }}-email" name="email" type="email" required autocomplete="email" value="{{ old('email') }}" class="{{ $campo }} mt-2 @error('email') border-error @enderror">
                @error('email')<p class="mt-1 text-xs text-error">{{ $message }}</p>@enderror
            </div>
            <div @class(['sm:col-span-2', 'lg:col-span-1' => $wide])>
                <label for="{{ $formId }}-phone" class="label">{{ __('ui.lead.phone') }} <span class="normal-case tracking-normal">({{ __('ui.lead.optional') }})</span></label>
                <input id="{{ $formId }}-phone" name="phone" type="tel" autocomplete="tel" value="{{ old('phone') }}" class="{{ $campo }} mt-2 @error('phone') border-error @enderror">
                @error('phone')<p class="mt-1 text-xs text-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <label for="{{ $formId }}-message" class="label">{{ __('ui.lead.message') }}</label>
            <textarea id="{{ $formId }}-message" name="message" rows="{{ $wide ? 3 : 4 }}" class="{{ $campo }} mt-2 @error('message') border-error @enderror">{{ old('message', $defaultMessage) }}</textarea>
            @error('message')<p class="mt-1 text-xs text-error">{{ $message }}</p>@enderror
        </div>

        {{-- RGPD: dois consentimentos distintos, nunca pré-marcados. --}}
        <fieldset class="grid gap-3 text-sm">
            <legend class="sr-only">Consentimentos</legend>
            <label class="flex items-start gap-3">
                <input type="checkbox" name="consent_contact" value="1" @checked(old('consent_contact')) class="mt-1 h-5 w-5 shrink-0 accent-olive-600">
                <span>{{ __('ui.lead.consent_contact') }}</span>
            </label>
            <label class="flex items-start gap-3">
                <input type="checkbox" name="consent_marketing" value="1" @checked(old('consent_marketing')) class="mt-1 h-5 w-5 shrink-0 accent-olive-600">
                <span>{{ __('ui.lead.consent_marketing') }}</span>
            </label>
        </fieldset>

        <p class="text-xs text-ink-muted">
            {!! __('ui.lead.privacy_notice', ['name' => e(config('agency.name')), 'link' => '<a href="'.route('privacy').'" class="link">'.__('ui.lead.privacy_link').'</a>']) !!}
        </p>

        <div>
            <button type="submit" class="btn-primary">{{ __('ui.lead.submit') }}</button>
        </div>
    </form>
    </div>
    </div>
</div>
