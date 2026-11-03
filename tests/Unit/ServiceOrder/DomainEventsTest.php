<?php

declare(strict_types=1);

namespace Tests\Unit\ServiceOrder;

use DateTimeImmutable;
use Oficina\ServiceOrder\Domain\Event\AdditionalRepairAuthorized;
use Oficina\ServiceOrder\Domain\Event\AdditionalRepairRefused;
use Oficina\ServiceOrder\Domain\Event\BudgetApproved;
use Oficina\ServiceOrder\Domain\Event\BudgetRejected;
use Oficina\ServiceOrder\Domain\Event\BudgetReturnedToDiagnosis;
use Oficina\ServiceOrder\Domain\Event\BudgetSent;
use Oficina\ServiceOrder\Domain\Event\DiagnosisStarted;
use Oficina\ServiceOrder\Domain\Event\OrderItemRemoved;
use Oficina\ServiceOrder\Domain\Event\PartAddedToOrder;
use Oficina\ServiceOrder\Domain\Event\ServiceAddedToOrder;
use Oficina\ServiceOrder\Domain\Event\ServiceOrderCanceled;
use Oficina\ServiceOrder\Domain\Event\ServiceOrderOpened;
use Oficina\ServiceOrder\Domain\Event\ServicesFinished;
use Oficina\ServiceOrder\Domain\Event\VehicleDelivered;
use Oficina\Shared\Domain\Event\DomainEvent;
use Oficina\Shared\Infrastructure\SystemClock;
use PHPUnit\Framework\TestCase;

/** Os eventos do Event Storming da OS existem no código com a data do fato. */
final class DomainEventsTest extends TestCase
{
    public function test_every_event_records_when_it_happened(): void
    {
        $at = new DateTimeImmutable('2026-10-29 10:00:00');
        $id = '2f6b8f4e-3b0e-4c1f-9d77-0a6f6f1f0c11';

        $events = [
            new ServiceOrderOpened($id, $id, $id, $at),
            new DiagnosisStarted($id, $at),
            new ServiceAddedToOrder($id, $id, 12000, false, $at),
            new PartAddedToOrder($id, $id, '4.500', false, $at),
            new OrderItemRemoved($id, $id, $at),
            new BudgetSent($id, $id, 30900, false, $at->modify('+48 hours'), $at),
            new BudgetReturnedToDiagnosis($id, $at),
            new BudgetApproved($id, 30900, $at),
            new BudgetRejected($id, $at),
            new AdditionalRepairAuthorized($id, 8000, $at),
            new AdditionalRepairRefused($id, true, $at),
            new ServicesFinished($id, $id, 5400, $at),
            new VehicleDelivered($id, $at),
            new ServiceOrderCanceled($id, $id, 'ORCAMENTO_REJEITADO', false, $at),
        ];

        foreach ($events as $event) {
            self::assertInstanceOf(DomainEvent::class, $event);
            self::assertSame($at, $event->occurredAt());
        }
    }

    public function test_system_clock_returns_current_time(): void
    {
        $before = new DateTimeImmutable;
        $now = (new SystemClock)->now();

        self::assertGreaterThanOrEqual($before, $now);
    }
}
