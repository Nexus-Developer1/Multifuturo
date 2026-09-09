<?php

/*
|--------------------------------------------------------------------------
| Portal — entrada única e escolha de módulo
|--------------------------------------------------------------------------
|
| A equipa entra uma vez (/entrar) e aterra numa página de cartões (/portal)
| com os módulos a que tem acesso. O backoffice de imóveis (/admin) é o
| primeiro módulo; os próximos são novos painéis do Filament (ou outra
| aplicação) registados em config/modules.php.
|
| Verificação em duas etapas por email: depois da palavra-passe, um código de
| seis algarismos enviado para o email da conta (validade, tentativas e
| intervalo de reenvio nas chaves abaixo).
|
*/

return [

    // Nome da plataforma. É o da agência: o portal é a área de trabalho da
    // Multifuturo, com a mesma marca do site — o módulo de imóveis é só um
    // dos cartões, mas a casa é a mesma.
    'name' => env('PORTAL_NAME', 'Multifuturo Propriedades'),

    /*
     | Segunda etapa por email (código de seis algarismos). DESLIGADA a pedido
     | do cliente em 2026-09-03: entra-se só com email e palavra-passe.
     |
     | O mecanismo continua todo aqui — para o voltar a ligar basta pôr
     | PORTAL_MFA=true no .env. Vale a pena ligá-lo em produção: o backoffice
     | dá acesso a dados de clientes, e uma palavra-passe sozinha é tudo o que
     | separa quem quer que a descubra desses dados.
     */
    'mfa' => (bool) env('PORTAL_MFA', false),

    // Minutos de validade de um código.
    'code_minutes' => 10,

    // Tentativas erradas antes de o código ficar queimado.
    'max_attempts' => 5,

    // Segundos entre pedidos de novo código.
    'resend_seconds' => 60,

    // Tentativas de login falhadas por email+IP antes de esperar um minuto.
    'login_attempts' => 5,

];
