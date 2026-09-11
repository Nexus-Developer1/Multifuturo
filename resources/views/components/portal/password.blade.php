{{--
    Campo de palavra-passe do portal, com o "olho" para a mostrar.

    O botão troca o tipo do campo entre password e text; o script está no
    layout do portal (o portal não carrega o Alpine). O estado vive no
    aria-pressed do botão e o CSS escolhe o desenho — olho aberto ou riscado.

    Os atributos passam para o campo: <x-portal.password id="password" required
    autocomplete="current-password" />. O name, se não vier, é o id.
--}}
@props(['id'])
<div class="p-senha">
    <input {{ $attributes->merge(['id' => $id, 'name' => $id]) }} type="password">
    <button type="button" class="p-senha__ver" data-ver-senha aria-controls="{{ $id }}" aria-pressed="false"
            aria-label="Mostrar palavra-passe" title="Mostrar palavra-passe">
        <svg class="p-senha__olho" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
        <svg class="p-senha__olho-riscado" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>
    </button>
</div>
