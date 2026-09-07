<?php

/*
|--------------------------------------------------------------------------
| Agendamento
|--------------------------------------------------------------------------
|
| A carteira é gerida no backoffice (/admin) e escrita diretamente na base de
| dados: não há sincronização com nenhum CRM. Três tarefas agendadas: a cópia
| de segurança diária, a importação mensal dos valores por m² do INE e a
| limpeza diária dos registos de consentimento com mais de 24 meses
| (model:prune).
|
*/

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

/*
| Cópia de segurança diária, sempre à mesma hora (config/backup.php).
|
| withoutOverlapping: se uma cópia demorar mais do que o esperado, a seguinte
| espera em vez de correr por cima. runInBackground: não prende o agendador.
*/
Schedule::command('backup:run')
    ->dailyAt((string) config('backup.at'))
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(fn () => Log::error('A cópia de segurança diária falhou.'));

/*
| Registos de consentimento de cookies: a prova guarda-se 24 meses
| (ConsentLog::prunable) e depois apaga-se — dados a mais são risco a mais.
*/
Schedule::command('model:prune')
    ->dailyAt('04:10')
    ->withoutOverlapping()
    ->onFailure(fn () => Log::error('A limpeza dos registos de consentimento falhou.'));
