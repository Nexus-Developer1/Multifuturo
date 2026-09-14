<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Leva o www para o endereço do APP_URL (e o contrário, se o APP_URL for com www).
 *
 * Porquê: o login em https://www.multifuturo.pt/entrar dava "Page Expired" (419).
 * O cookie de sessão nascia para www.multifuturo.pt, mas o formulário era enviado
 * para https://multifuturo.pt/entrar — outro endereço, para onde o cookie não vai.
 * O servidor recebia o login sem sessão e recusava o token. Com um só endereço, o
 * cookie e o formulário ficam sempre juntos (e o Google deixa de ver o site a dobrar).
 *
 * Só mexe quando a diferença é o "www."; qualquer outro endereço (o de testes na
 * NXS, o IP da rede local) passa como está. Corre antes da sessão, para o www nem
 * chegar a receber cookies.
 */
class RedirectToCanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $canonico = parse_url((string) config('app.url'));
        $hostCanonico = strtolower($canonico['host'] ?? '');
        $host = strtolower($request->getHost());

        $semWww = fn (string $h): string => preg_replace('/^www\./', '', $h);

        if ($hostCanonico === '' || $host === $hostCanonico || $semWww($host) !== $semWww($hostCanonico)) {
            return $next($request);
        }

        $destino = ($canonico['scheme'] ?? $request->getScheme()).'://'.$hostCanonico
            .(isset($canonico['port']) ? ':'.$canonico['port'] : '')
            .$request->getRequestUri();

        // 301 para ler páginas; 308 para o resto, que mantém o método e o corpo.
        $estado = $request->isMethod('GET') || $request->isMethod('HEAD') ? 301 : 308;

        return redirect()->away($destino, $estado);
    }
}
