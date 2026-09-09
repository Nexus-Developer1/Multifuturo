<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * A minha conta — nome, email e palavra-passe da própria pessoa.
 *
 * Vive no portal, não no backoffice: mudar a palavra-passe é da conta, não do
 * módulo de imóveis. Quem só tem acesso ao Site tem de o conseguir fazer na
 * mesma — antes isto levava ao perfil do Filament e essa pessoa apanhava um 403.
 *
 * Ninguém mexe na conta de outra pessoa por aqui: só a sua. Os módulos, o
 * estado e a qualidade de administrador ficam na Equipa, com o administrador.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('portal.profile', ['pessoa' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $pessoa = $request->user();

        $dados = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($pessoa)],
            // Em branco = fica a que está. Quem a muda tem de a escrever duas vezes.
            'password' => ['nullable', 'string', 'min:8', 'max:255', 'confirmed'],
        ], [], [
            'name' => 'nome',
            'email' => 'email',
            'password' => 'palavra-passe',
        ]);

        $pessoa->name = $dados['name'];
        $pessoa->email = $dados['email'];

        if (filled($dados['password'] ?? null)) {
            $pessoa->password = Hash::make($dados['password']);
        }

        $pessoa->save();

        // A sessão guarda o hash da palavra-passe: sem isto, mudá-la deitava a
        // própria pessoa fora a seguir a gravar.
        $request->session()->put('password_hash_web', $pessoa->getAuthPassword());

        return redirect()->route('profile.edit')->with('status', 'Conta atualizada.');
    }
}
