<?php

declare(strict_types=1);

namespace Tests\Unit\ServiceOrder;

use DateTimeImmutable;
use Oficina\ServiceOrder\Domain\CancellationReason;
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
use Oficina\ServiceOrder\Domain\Exception\OrderItemNotFoundException;
use Oficina\ServiceOrder\Domain\PartLine;
use Oficina\ServiceOrder\Domain\Quantity;
use Oficina\ServiceOrder\Domain\ServiceLine;
use Oficina\ServiceOrder\Domain\ServiceOrder;
use Oficina\ServiceOrder\Domain\ServiceOrderId;
use Oficina\ServiceOrder\Domain\ServiceOrderStatus as S;
use Oficina\Shared\Domain\Event\DomainEvent;
use Oficina\Shared\Domain\Exception\DomainException;
use Oficina\Shared\Domain\Exception\InvalidValueException;
use Oficina\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class ServiceOrderTest extends TestCase
{
    private const string CUSTOMER = '6a3c1c9e-1f1e-4d3a-9a63-2b7c9f8e1a01';

    private const string VEHICLE = '7b4d2d0f-2a2f-4e4b-8b74-3c8d0a9f2b02';

    private const string OIL_SERVICE = '8c5e3e1a-3b3a-4f5c-9c85-4d9e1b0a3c03';

    private const string ALIGN_SERVICE = '9d6f4f2b-4c4b-4a6d-8d96-5e0f2c1b4d04';

    private const string OIL = 'ae7a5a3c-5d5c-4b7e-9ea7-6f1a3d2c5e05';

    private const string FILTER = 'bf8b6b4d-6e6d-4c8f-8fb8-7a2b4e3d6f06';

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-10-29 09:00:00');
    }

    private function at(string $modifier): DateTimeImmutable
    {
        return $this->now->modify($modifier);
    }

    private static function oilChange(bool $additional = false): ServiceLine
    {
        return ServiceLine::quote(self::OIL_SERVICE, 'TROCA-OLEO', 'Troca de óleo', Money::fromDecimal('120.00'), 60, $additional);
    }

    private static function alignment(): ServiceLine
    {
        return ServiceLine::quote(self::ALIGN_SERVICE, 'ALINHAMENTO', 'Alinhamento', Money::fromDecimal('80.00'), 30);
    }

    private static function oil(string $quantity = '4.5'): PartLine
    {
        return PartLine::quote(self::OIL, 'OLEO-5W30', 'Óleo 5W30', 'L', Quantity::of($quantity), Money::fromDecimal('42.00'));
    }

    private static function filter(): PartLine
    {
        return PartLine::quote(self::FILTER, 'FILTRO-OLEO', 'Filtro de óleo', 'UN', Quantity::of('1'), Money::fromDecimal('35.90'));
    }

    /** @return list<class-string> */
    private static function names(ServiceOrder $order): array
    {
        return array_map(static fn (DomainEvent $e): string => $e::class, $order->pullEvents());
    }

    private function inDiagnosis(): ServiceOrder
    {
        $order = ServiceOrder::open(self::CUSTOMER, self::VEHICLE, $this->now);
        $order->addService(self::oilChange(), $this->now);
        $order->addPart(self::oil(), $this->now);
        $order->pullEvents();

        return $order;
    }

    private function awaitingApproval(): ServiceOrder
    {
        $order = $this->inDiagnosis();
        $order->sendBudget($this->at('+1 hour'));
        $order->pullEvents();

        return $order;
    }

    private function inProgress(): ServiceOrder
    {
        $order = $this->awaitingApproval();
        $order->approveBudget($this->at('+2 hours'));
        $order->pullEvents();

        return $order;
    }

    public function test_opens_received_with_history_and_event(): void
    {
        $order = ServiceOrder::open(self::CUSTOMER, self::VEHICLE, $this->now);

        self::assertSame(S::RECEIVED, $order->status());
        self::assertSame($this->now, $order->openedAt());
        self::assertCount(1, $order->history());
        self::assertCount(1, $order->pullNewHistory());
        self::assertSame([], $order->pullNewHistory());
        self::assertSame([ServiceOrderOpened::class], self::names($order));
        self::assertSame(0, $order->budget()->total()->cents);
    }

    public function test_first_item_starts_diagnosis_and_budget_is_calculated(): void
    {
        $order = ServiceOrder::open(self::CUSTOMER, self::VEHICLE, $this->now);
        $order->pullEvents();
        $order->addService(self::oilChange(), $this->now);
        $order->addPart(self::oil(), $this->now);
        $order->addPart(self::filter(), $this->now);

        self::assertSame(S::IN_DIAGNOSIS, $order->status());
        self::assertSame([DiagnosisStarted::class, ServiceAddedToOrder::class, PartAddedToOrder::class, PartAddedToOrder::class], self::names($order));

        $budget = $order->budget();
        self::assertSame('120.00', $budget->services->toDecimal());
        self::assertSame('224.90', $budget->parts->toDecimal()); // 4,5 x 42,00 + 35,90
        self::assertSame('344.90', $budget->total()->toDecimal());
    }

    public function test_same_part_sums_quantity_and_same_service_is_refused(): void
    {
        $order = $this->inDiagnosis();
        $order->addPart(self::oil('0.5'), $this->now);

        self::assertCount(1, $order->parts());
        self::assertSame('5.000', $order->parts()[0]->quantity->toDecimal());

        $this->expectException(DomainException::class);
        $order->addService(self::oilChange(), $this->now);
    }

    public function test_removes_items_and_rejects_unknown_line(): void
    {
        $order = $this->inDiagnosis();
        $order->removeService($order->services()[0]->id, $this->now);
        $order->removePart($order->parts()[0]->id, $this->now);

        self::assertSame([], $order->services());
        self::assertSame([], $order->parts());
        self::assertSame([OrderItemRemoved::class, OrderItemRemoved::class], self::names($order));

        foreach ([static fn () => $order->removeService('x', new DateTimeImmutable), static fn () => $order->removePart('x', new DateTimeImmutable)] as $remove) {
            try {
                $remove();
                self::fail('Linha inexistente deveria falhar.');
            } catch (OrderItemNotFoundException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_budget_needs_a_service_and_diagnosis(): void
    {
        $order = ServiceOrder::open(self::CUSTOMER, self::VEHICLE, $this->now);

        try {
            $order->sendBudget($this->now);
            self::fail('Sem diagnóstico não envia orçamento.');
        } catch (DomainException) {
            $this->addToAssertionCount(1);
        }

        $order->addPart(self::oil(), $this->now);
        $this->expectExceptionMessage('ao menos um serviço');
        $order->sendBudget($this->now);
    }

    public function test_sends_budget_with_48h_deadline(): void
    {
        $order = $this->inDiagnosis();
        $sentAt = $this->at('+1 hour');
        $order->sendBudget($sentAt);

        self::assertSame(S::AWAITING_APPROVAL, $order->status());
        self::assertSame($sentAt, $order->budgetSentAt());
        self::assertEquals($sentAt->modify('+48 hours'), $order->approvalDeadline());

        $events = $order->pullEvents();
        self::assertInstanceOf(BudgetSent::class, $events[0]);
        self::assertSame(30900, $events[0]->totalCents);
        self::assertFalse($events[0]->additional);

        // Itens não mudam fora do diagnóstico.
        $this->expectException(DomainException::class);
        $order->addService(self::alignment(), $sentAt);
    }

    public function test_returns_budget_to_diagnosis(): void
    {
        $order = $this->awaitingApproval();
        $order->returnToDiagnosis($this->at('+3 hours'));

        self::assertSame(S::IN_DIAGNOSIS, $order->status());
        self::assertNull($order->approvalDeadline());
        self::assertSame([BudgetReturnedToDiagnosis::class], self::names($order));
    }

    public function test_approval_starts_execution_and_lists_parts_to_reserve(): void
    {
        $order = $this->awaitingApproval();
        self::assertCount(1, $order->pendingParts());
        self::assertFalse($order->hasApprovedBudget());

        $approvedAt = $this->at('+2 hours');
        $order->approveBudget($approvedAt);

        self::assertSame(S::IN_PROGRESS, $order->status());
        self::assertSame($approvedAt, $order->approvedAt());
        self::assertSame($approvedAt, $order->executionStartedAt());
        self::assertNull($order->approvalDeadline());
        self::assertTrue($order->hasApprovedBudget());
        self::assertSame([], $order->pendingParts());
        self::assertSame([], $order->pendingServices());
        self::assertSame([BudgetApproved::class], self::names($order));
    }

    public function test_cannot_approve_after_deadline(): void
    {
        $order = $this->awaitingApproval();
        self::assertTrue($order->isApprovalOverdue($this->at('+49 hours')));

        $this->expectExceptionMessage('prazo de aprovação venceu');
        $order->approveBudget($this->at('+49 hours'));
    }

    public function test_rejection_cancels_without_stock(): void
    {
        $order = $this->awaitingApproval();
        $order->rejectBudget($this->at('+5 hours'));

        self::assertSame(S::CANCELED, $order->status());
        self::assertSame(CancellationReason::BUDGET_REJECTED, $order->cancellationReason());
        self::assertNotNull($order->canceledAt());
        $events = $order->pullEvents();
        self::assertInstanceOf(BudgetRejected::class, $events[0]);
        self::assertInstanceOf(ServiceOrderCanceled::class, $events[1]);
        self::assertFalse($events[1]->hadStockReserved);
        self::assertTrue($order->status()->isFinal());
    }

    public function test_initial_decisions_are_refused_when_awaiting_additional(): void
    {
        $order = $this->inProgress();
        $order->requestAdditionalRepair([self::alignment()], [], $this->at('+3 hours'));

        foreach ([
            fn () => $order->approveBudget($this->at('+4 hours')),
            fn () => $order->rejectBudget($this->at('+4 hours')),
            fn () => $order->returnToDiagnosis($this->at('+4 hours')),
        ] as $decision) {
            try {
                $decision();
                self::fail('Decisão do orçamento inicial não vale para reparo adicional.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_additional_repair_authorized_keeps_timer_and_reserves_extras(): void
    {
        $order = $this->inProgress();
        $order->requestAdditionalRepair([self::alignment()], [self::filter()], $this->at('+3 hours'));

        self::assertSame(S::AWAITING_APPROVAL, $order->status());
        self::assertTrue($order->isAwaitingAdditionalApproval());
        self::assertSame('115.90', $order->pendingBudget()->total()->toDecimal());
        self::assertCount(1, $order->pendingParts());
        $events = $order->pullEvents();
        self::assertSame([ServiceAddedToOrder::class, PartAddedToOrder::class, BudgetSent::class], array_map(static fn ($e) => $e::class, $events));
        self::assertTrue($events[2]->additional);
        self::assertSame(11590, $events[2]->totalCents);

        $order->authorizeAdditionalRepair($this->at('+4 hours'));
        self::assertSame(S::IN_PROGRESS, $order->status());
        self::assertFalse($order->isAwaitingAdditionalApproval());
        self::assertCount(2, $order->services());
        self::assertTrue($order->services()[1]->additional);
        self::assertEquals($this->at('+2 hours'), $order->executionStartedAt());
        $events = $order->pullEvents();
        self::assertInstanceOf(AdditionalRepairAuthorized::class, $events[0]);
        self::assertSame(11590, $events[0]->extraCents);
    }

    public function test_additional_repair_refused_keeps_original_scope(): void
    {
        $order = $this->inProgress();
        $order->requestAdditionalRepair([self::alignment()], [self::filter()], $this->at('+3 hours'));
        $order->pullEvents();
        $order->refuseAdditionalRepair($this->at('+4 hours'));

        self::assertSame(S::IN_PROGRESS, $order->status());
        self::assertCount(1, $order->services());
        self::assertCount(1, $order->parts());
        self::assertSame('309.00', $order->budget()->total()->toDecimal());
        $events = $order->pullEvents();
        self::assertInstanceOf(AdditionalRepairRefused::class, $events[0]);
        self::assertFalse($events[0]->byTimeout);
    }

    public function test_additional_repair_guards(): void
    {
        $order = $this->inProgress();

        foreach ([
            fn () => $order->requestAdditionalRepair([], [], $this->now),
            fn () => $order->requestAdditionalRepair([self::oilChange()], [], $this->now),
            fn () => $order->authorizeAdditionalRepair($this->now),
            fn () => $order->refuseAdditionalRepair($this->now),
            fn () => $this->inDiagnosis()->requestAdditionalRepair([self::alignment()], [], $this->now),
        ] as $i => $call) {
            try {
                $call();
                self::fail("Caso $i deveria falhar.");
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }

        $order->requestAdditionalRepair([self::alignment()], [], $this->at('+3 hours'));
        $this->expectExceptionMessage('prazo de aprovação venceu');
        $order->authorizeAdditionalRepair($this->at('+52 hours'));
    }

    public function test_expired_initial_budget_cancels_order(): void
    {
        $order = $this->awaitingApproval();

        try {
            $order->expireApproval($this->at('+2 hours'));
            self::fail('Ainda dentro do prazo.');
        } catch (DomainException) {
            $this->addToAssertionCount(1);
        }

        $order->expireApproval($this->at('+49 hours'));

        self::assertSame(S::CANCELED, $order->status());
        self::assertSame(CancellationReason::APPROVAL_TIMEOUT, $order->cancellationReason());
        self::assertSame([ServiceOrderCanceled::class], self::names($order));
    }

    public function test_expired_additional_repair_is_refused_and_execution_resumes(): void
    {
        $order = $this->inProgress();
        $order->requestAdditionalRepair([self::alignment()], [], $this->at('+3 hours'));
        $order->pullEvents();
        $order->expireApproval($this->at('+52 hours'));

        self::assertSame(S::IN_PROGRESS, $order->status());
        self::assertCount(1, $order->services());
        $events = $order->pullEvents();
        self::assertInstanceOf(AdditionalRepairRefused::class, $events[0]);
        self::assertTrue($events[0]->byTimeout);
    }

    public function test_finishes_and_delivers_with_execution_time(): void
    {
        $order = $this->inProgress();
        self::assertNull($order->executionSeconds());

        try {
            $order->deliver($this->now);
            self::fail('Entrega exige OS finalizada.');
        } catch (DomainException) {
            $this->addToAssertionCount(1);
        }

        $order->finishServices($this->at('+3 hours 30 minutes'));
        self::assertSame(S::COMPLETED, $order->status());
        self::assertSame(5400, $order->executionSeconds());
        $events = $order->pullEvents();
        self::assertInstanceOf(ServicesFinished::class, $events[0]);
        self::assertSame(5400, $events[0]->executionSeconds);

        try {
            $order->finishServices($this->now);
            self::fail('Só finaliza em execução.');
        } catch (DomainException) {
            $this->addToAssertionCount(1);
        }

        try {
            $order->cancel($this->now);
            self::fail('OS finalizada não pode ser cancelada.');
        } catch (DomainException) {
            $this->addToAssertionCount(1);
        }

        $order->deliver($this->at('+1 day'));
        self::assertSame(S::DELIVERED, $order->status());
        self::assertEquals($this->at('+1 day'), $order->deliveredAt());
        self::assertSame([VehicleDelivered::class], self::names($order));
        self::assertSame(['RECEBIDA', 'EM_DIAGNOSTICO', 'AGUARDANDO_APROVACAO', 'EM_EXECUCAO', 'FINALIZADA', 'ENTREGUE'], array_map(static fn ($c) => $c->status->value, $order->history()));
    }

    public function test_workshop_cancels_in_progress_order_signalling_reserved_stock(): void
    {
        $order = $this->inProgress();
        $order->cancel($this->at('+3 hours'));

        self::assertSame(CancellationReason::WORKSHOP_DECISION, $order->cancellationReason());
        $events = $order->pullEvents();
        self::assertInstanceOf(ServiceOrderCanceled::class, $events[0]);
        self::assertTrue($events[0]->hadStockReserved);
        self::assertSame('CANCELADA_PELA_OFICINA', $events[0]->reason);
        self::assertSame('Cancelada pela oficina', CancellationReason::WORKSHOP_DECISION->label());
    }

    public function test_reconstitutes_state_without_events(): void
    {
        $original = $this->inProgress();
        $copy = ServiceOrder::reconstitute(
            ServiceOrderId::from($original->id->value),
            $original->customerId,
            $original->vehicleId,
            $original->status(),
            $original->services(),
            $original->parts(),
            $original->history(),
            $original->openedAt(),
            $original->budgetSentAt(),
            $original->approvalDeadline(),
            $original->approvedAt(),
            $original->executionStartedAt(),
            $original->executionFinishedAt(),
            $original->deliveredAt(),
            $original->canceledAt(),
            $original->cancellationReason(),
            $original->isAwaitingAdditionalApproval(),
        );

        self::assertSame([], $copy->pullEvents());
        self::assertSame([], $copy->pullNewHistory());
        self::assertEquals($original->budget(), $copy->budget());
        self::assertSame(S::IN_PROGRESS, $copy->status());
    }

    public function test_quantity_rules(): void
    {
        self::assertSame('4.500', Quantity::of('4,5')->toDecimal());
        self::assertSame(1500, Quantity::fromThousandths(1000)->add(Quantity::of('0.5'))->thousandths);

        foreach (['0', '-1', '1.2345', 'abc'] as $invalid) {
            try {
                Quantity::of($invalid);
                self::fail("$invalid deveria falhar.");
            } catch (InvalidValueException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->expectException(InvalidValueException::class);
        ServiceOrderId::from('nao-e-uuid');
    }
}
