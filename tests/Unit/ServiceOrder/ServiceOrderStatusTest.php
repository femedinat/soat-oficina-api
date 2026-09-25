<?php

declare(strict_types=1);

namespace Tests\Unit\ServiceOrder;

use Oficina\ServiceOrder\Domain\ServiceOrderStatus as S;
use Oficina\Shared\Domain\Exception\DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ServiceOrderStatusTest extends TestCase
{
    public function test_happy_path_flow(): void
    {
        $status = S::RECEIVED
            ->transitionTo(S::IN_DIAGNOSIS)
            ->transitionTo(S::AWAITING_APPROVAL)
            ->transitionTo(S::IN_PROGRESS)
            ->transitionTo(S::COMPLETED)
            ->transitionTo(S::DELIVERED);

        self::assertSame(S::DELIVERED, $status);
        self::assertTrue($status->isFinal());
    }

    public function test_additional_repair_requires_new_approval(): void
    {
        self::assertSame(S::AWAITING_APPROVAL, S::IN_PROGRESS->transitionTo(S::AWAITING_APPROVAL));
    }

    public function test_rejected_budget_finishes_without_execution(): void
    {
        self::assertSame(S::COMPLETED, S::AWAITING_APPROVAL->transitionTo(S::COMPLETED));
    }

    #[DataProvider('invalidTransitions')]
    public function test_blocks_invalid_transitions(S $from, S $to): void
    {
        self::assertFalse($from->canTransitionTo($to));
        $this->expectException(DomainException::class);
        $from->transitionTo($to);
    }

    /** @return array<string, array{S, S}> */
    public static function invalidTransitions(): array
    {
        return [
            'pular diagnóstico' => [S::RECEIVED, S::IN_PROGRESS],
            'executar sem aprovação' => [S::IN_DIAGNOSIS, S::IN_PROGRESS],
            'entregar sem finalizar' => [S::IN_PROGRESS, S::DELIVERED],
            'reabrir entregue' => [S::DELIVERED, S::RECEIVED],
            'voltar para recebida' => [S::IN_DIAGNOSIS, S::RECEIVED],
        ];
    }

    public function test_every_status_has_label(): void
    {
        foreach (S::cases() as $case) {
            self::assertNotSame('', $case->label());
        }
    }

    public function test_only_delivered_is_final(): void
    {
        $finals = array_filter(S::cases(), static fn (S $s): bool => $s->isFinal());
        self::assertSame([S::DELIVERED], array_values($finals));
    }
}
