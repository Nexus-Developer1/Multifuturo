{{--
    A minha conta: nome, email e palavra-passe da própria pessoa.
    Vive no portal — mudar a palavra-passe é da conta, não de um módulo.
--}}
<x-layouts.portal title="Perfil e palavra-passe">
    <div class="p-cabecalho">
        <div>
            <p class="p-eyebrow">A minha conta</p>
            <h1 class="p-titulo">Perfil e palavra-passe</h1>
            <p class="p-subtitulo">{{ $pessoa->email }}</p>
        </div>
    </div>

    @if (session('status'))
        <div class="p-alerta p-alerta--ok" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="p-alerta p-alerta--erro" role="alert">
            <div><strong>Não foi possível guardar.</strong>
                <ul class="p-alerta__lista">@foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul>
            </div>
        </div>
    @endif

    <form method="post" action="{{ route('profile.update') }}" class="p-formulario" novalidate>
        @csrf
        @method('PUT')

        <section class="p-seccao">
            <h2 class="p-seccao__titulo">Quem sou</h2>
            <div class="p-grelha-2">
                <div class="p-campo">
                    <label for="name">Nome</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $pessoa->name) }}" required maxlength="191" autocomplete="name" @error('name') aria-invalid="true" @enderror>
                </div>
                <div class="p-campo">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $pessoa->email) }}" required maxlength="191" autocomplete="email" @error('email') aria-invalid="true" @enderror>
                    <p class="p-ajuda-campo">É com este endereço que entra no portal.</p>
                </div>
            </div>
        </section>

        <section class="p-seccao">
            <h2 class="p-seccao__titulo">Palavra-passe</h2>
            <div class="p-grelha-2">
                <div class="p-campo">
                    <label for="password">Nova palavra-passe <span class="p-opcional">(deixe em branco para manter)</span></label>
                    <x-portal.password id="password" minlength="8" autocomplete="new-password" :aria-invalid="$errors->has('password') ? 'true' : null" />
                    <p class="p-ajuda-campo">Mínimo 8 caracteres.</p>
                </div>
                <div class="p-campo">
                    <label for="password_confirmation">Repetir a nova palavra-passe</label>
                    <x-portal.password id="password_confirmation" minlength="8" autocomplete="new-password" />
                </div>
            </div>
        </section>

        <div class="p-acoes">
            <button type="submit" class="p-btn p-btn--primario p-btn--auto">Guardar alterações</button>
            <a href="{{ route('portal') }}" class="p-btn p-btn--neutro p-btn--auto">Cancelar</a>
        </div>
    </form>
</x-layouts.portal>
