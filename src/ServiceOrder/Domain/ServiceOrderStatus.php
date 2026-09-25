<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Domain;

use Oficina\Shared\Domain\Exception\DomainException;

/**
 * Ciclo de vida da Ordem de Serviço (máquina de estados).
 * Toda transição passa por transitionTo(): regra centralizada, testável e sem if espalhado em controller.
 */
enum ServiceOrderStatus: string
{
    case RECEIVED = 'RECEBIDA';
    case IN_DIAGNOSIS = 'EM_DIAGNOSTICO';
    case AWAITING_APPROVAL = 'AGUARDANDO_APROVACAO';
    case IN_PROGRESS = 'EM_EXECUCAO';
    case COMPLETED = 'FINALIZADA';
    case DELIVERED = 'ENTREGUE';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::RECEIVED => [self::IN_DIAGNOSIS],
            self::IN_DIAGNOSIS => [self::AWAITING_APPROVAL],
            self::AWAITING_APPROVAL => [
                self::IN_PROGRESS,   // orçamento aprovado
                self::IN_DIAGNOSIS,  // orçamento revisado
                self::COMPLETED,     // orçamento rejeitado -> devolução sem execução (decisão a validar no Event Storming)
            ],
            self::IN_PROGRESS => [
                self::AWAITING_APPROVAL, // reparo adicional encontrado -> nova aprovação do cliente
                self::COMPLETED,
            ],
            self::COMPLETED => [self::DELIVERED],
            self::DELIVERED => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function transitionTo(self $target): self
    {
        if (! $this->canTransitionTo($target)) {
            throw new DomainException(sprintf(
                'Transição inválida da OS: %s -> %s.',
                $this->label(),
                $target->label(),
            ));
        }

        return $target;
    }

    public function isFinal(): bool
    {
        return $this === self::DELIVERED;
    }

    public function label(): string
    {
        return match ($this) {
            self::RECEIVED => 'Recebida',
            self::IN_DIAGNOSIS => 'Em diagnóstico',
            self::AWAITING_APPROVAL => 'Aguardando aprovação',
            self::IN_PROGRESS => 'Em execução',
            self::COMPLETED => 'Finalizada',
            self::DELIVERED => 'Entregue',
        };
    }
}
