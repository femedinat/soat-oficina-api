<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Domain;

enum CancellationReason: string
{
    case BUDGET_REJECTED = 'ORCAMENTO_REJEITADO';
    case APPROVAL_TIMEOUT = 'SEM_RESPOSTA_NO_PRAZO';
    case WORKSHOP_DECISION = 'CANCELADA_PELA_OFICINA';

    public function label(): string
    {
        return match ($this) {
            self::BUDGET_REJECTED => 'Orçamento rejeitado pelo cliente',
            self::APPROVAL_TIMEOUT => 'Orçamento sem resposta no prazo de aprovação',
            self::WORKSHOP_DECISION => 'Cancelada pela oficina',
        };
    }
}
