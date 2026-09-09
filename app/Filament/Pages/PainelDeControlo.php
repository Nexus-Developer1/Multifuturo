<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard;

/**
 * O painel de controlo do backoffice — só para administradores.
 *
 * É a vista de gestão da agência: carteira à venda, pedidos por responder,
 * leads de angariação e de compradores. Um consultor que entre no backoffice
 * trabalha nas fichas e nos pedidos que lhe dizem respeito; estes números são
 * da direção.
 *
 * Herda tudo do painel do Filament (rota "/" do painel, título, widgets); o
 * que acrescenta é a porta fechada. Quem não é administrador nem sequer vê a
 * entrada na barra lateral — o Filament esconde o que não se pode abrir — e,
 * ao entrar no backoffice, aterra na primeira página a que tem acesso.
 */
class PainelDeControlo extends Dashboard
{
    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}
