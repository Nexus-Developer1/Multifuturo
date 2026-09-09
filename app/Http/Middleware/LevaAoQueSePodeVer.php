<?php

namespace App\Http\Middleware;

use App\Filament\Resources\Properties\PropertyResource;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * O painel de controlo é só para administradores (PainelDeControlo), mas vive
 * na raiz do backoffice: quem entra pelo portal cai lá primeiro. Sem isto, um
 * consultor batia num 403 à entrada, o que parece uma avaria e não uma regra.
 *
 * Aqui é levado à lista de imóveis, que é o trabalho dele. Os administradores
 * seguem para o painel como sempre.
 */
class LevaAoQueSePodeVer
{
    public function handle(Request $request, Closure $next): Response
    {
        $ePainel = $request->routeIs('filament.admin.pages.painel-de-controlo');

        if ($ePainel && ! $request->user()?->isAdmin()) {
            return redirect(PropertyResource::getUrl('index'));
        }

        return $next($request);
    }
}
