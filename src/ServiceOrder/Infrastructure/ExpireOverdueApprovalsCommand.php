<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Infrastructure;

use Illuminate\Console\Command;
use Oficina\ServiceOrder\Application\ExpireOverdueApprovals;

/** Job da política "Se 48 h sem resposta", agendado em routes/console.php. */
final class ExpireOverdueApprovalsCommand extends Command
{
    protected $signature = 'service-orders:expire-approvals';

    protected $description = 'Cancela OS com orçamento sem resposta no prazo e recusa reparos adicionais vencidos.';

    public function handle(ExpireOverdueApprovals $expire): int
    {
        $count = $expire->execute();
        $this->info(sprintf('%d OS com prazo de aprovação vencido tratada(s).', $count));

        return self::SUCCESS;
    }
}
