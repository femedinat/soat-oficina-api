<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Domain;

use Oficina\Shared\Domain\Exception\DomainException;
use Oficina\Shared\Domain\Exception\InvalidValueException;

/**
 * Ciclo de vida da Ordem de Serviço (máquina de estados).
 * Toda transição passa por transitionTo(): regra centralizada, testável e sem if espalhado em controller.
 *
 * Os 6 status do enunciado + "Cancelada" (decisão do grupo no Event Storming): orçamento rejeitado,
 * prazo de 48 h sem resposta ou desistência antes da finalização encerram a OS sem execução.
 */
enum ServiceOrderStatus: string
{
    case RECEIVED = 'RECEBIDA';
    case IN_DIAGNOSIS = 'EM_DIAGNOSTICO';
    case AWAITING_APPROVAL = 'AGUARDANDO_APROVACAO';
    case IN_PROGRESS = 'EM_EXECUCAO';
    case COMPLETED = 'FINALIZADA';
    case DELIVERED = 'ENTREGUE';
    case CANCELED = 'CANCELADA';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::RECEIVED => [self::IN_DIAGNOSIS, self::CANCELED],
            self::IN_DIAGNOSIS => [self::AWAITING_APPROVAL, self::CANCELED],
            self::AWAITING_APPROVAL => [
                self::IN_PROGRESS,   // orçamento (ou reparo adicional) aprovado / reparo adicional recusado
                self::IN_DIAGNOSIS,  // orçamento devolvido para revisão
                self::CANCELED,      // orçamento rejeitado ou sem resposta em 48 h
            ],
            self::IN_PROGRESS => [
                self::AWAITING_APPROVAL, // reparo adicional encontrado -> nova aprovação do cliente
                self::COMPLETED,
                self::CANCELED,          // desistência durante a execução (reserva de estoque liberada)
            ],
            self::COMPLETED => [self::DELIVERED],
            self::DELIVERED, self::CANCELED => [],
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
        return $this === self::DELIVERED || $this === self::CANCELED;
    }

    public static function parse(string $value): self
    {
        return self::tryFrom(strtoupper(trim($value)))
            ?? throw new InvalidValueException('Status de OS inválido.');
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
            self::CANCELED => 'Cancelada',
        };
    }
}
