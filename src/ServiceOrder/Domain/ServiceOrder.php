<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Domain;

use DateInterval;
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
use Oficina\ServiceOrder\Domain\Exception\OrderItemNotFoundException;
use Oficina\Shared\Domain\Event\RecordsEvents;
use Oficina\Shared\Domain\Exception\DomainException;

/**
 * Agregado raiz do contexto Atendimento (Core Domain): a Ordem de Serviço, da abertura à entrega.
 *
 * Invariantes:
 *  - status só muda pela máquina de estados (ServiceOrderStatus) e toda mudança entra no histórico;
 *  - itens só são incluídos/removidos no diagnóstico; na execução, só como reparo adicional (nova aprovação);
 *  - o orçamento precisa de ao menos um serviço; preços são congelados na inclusão;
 *  - a aprovação tem prazo (48 h); vencido, o orçamento inicial cancela a OS e o adicional é recusado;
 *  - o cronômetro de execução começa na aprovação e para em "Serviços Finalizados".
 *
 * Reserva, baixa e liberação de estoque são feitas pela Aplicação (contrato do Estoque) na mesma
 * transação da mudança de status; o agregado informa o que reservar (pendingParts) e se havia reserva.
 */
final class ServiceOrder
{
    use RecordsEvents;

    public const int APPROVAL_WINDOW_HOURS = 48;

    /** @var list<StatusChange> */
    private array $newHistory = [];

    /**
     * @param  array<string, ServiceLine>  $services
     * @param  array<string, PartLine>  $parts
     * @param  list<StatusChange>  $history
     */
    private function __construct(
        public readonly ServiceOrderId $id,
        public readonly string $customerId,
        public readonly string $vehicleId,
        private ServiceOrderStatus $status,
        private array $services,
        private array $parts,
        private array $history,
        private readonly DateTimeImmutable $openedAt,
        private ?DateTimeImmutable $budgetSentAt = null,
        private ?DateTimeImmutable $approvalDeadline = null,
        private ?DateTimeImmutable $approvedAt = null,
        private ?DateTimeImmutable $executionStartedAt = null,
        private ?DateTimeImmutable $executionFinishedAt = null,
        private ?DateTimeImmutable $deliveredAt = null,
        private ?DateTimeImmutable $canceledAt = null,
        private ?CancellationReason $cancellationReason = null,
        private bool $awaitingAdditionalApproval = false,
    ) {}

    public static function open(string $customerId, string $vehicleId, DateTimeImmutable $now): self
    {
        $order = new self(ServiceOrderId::generate(), $customerId, $vehicleId, ServiceOrderStatus::RECEIVED, [], [], [], $now);
        $order->appendHistory(ServiceOrderStatus::RECEIVED, $now);
        $order->record(new ServiceOrderOpened($order->id->value, $customerId, $vehicleId, $now));

        return $order;
    }

    /**
     * Reconstitui a partir da persistência (sem eventos nem histórico novo).
     *
     * @param  list<ServiceLine>  $services
     * @param  list<PartLine>  $parts
     * @param  list<StatusChange>  $history
     */
    public static function reconstitute(
        ServiceOrderId $id,
        string $customerId,
        string $vehicleId,
        ServiceOrderStatus $status,
        array $services,
        array $parts,
        array $history,
        DateTimeImmutable $openedAt,
        ?DateTimeImmutable $budgetSentAt,
        ?DateTimeImmutable $approvalDeadline,
        ?DateTimeImmutable $approvedAt,
        ?DateTimeImmutable $executionStartedAt,
        ?DateTimeImmutable $executionFinishedAt,
        ?DateTimeImmutable $deliveredAt,
        ?DateTimeImmutable $canceledAt,
        ?CancellationReason $cancellationReason,
        bool $awaitingAdditionalApproval,
    ): self {
        $indexedServices = [];
        foreach ($services as $line) {
            $indexedServices[$line->id] = $line;
        }

        $indexedParts = [];
        foreach ($parts as $line) {
            $indexedParts[$line->id] = $line;
        }

        return new self(
            $id, $customerId, $vehicleId, $status, $indexedServices, $indexedParts, $history, $openedAt,
            $budgetSentAt, $approvalDeadline, $approvedAt, $executionStartedAt, $executionFinishedAt,
            $deliveredAt, $canceledAt, $cancellationReason, $awaitingAdditionalApproval,
        );
    }

    // --- Fase 2: diagnóstico e orçamento -------------------------------------------------------

    /** Política "Iniciar Diagnóstico": o primeiro item lançado pelo mecânico tira a OS de "Recebida". */
    public function addService(ServiceLine $line, DateTimeImmutable $now): void
    {
        $this->guardEditable($now);

        foreach ($this->services as $existing) {
            if ($existing->serviceId === $line->serviceId) {
                throw new DomainException(sprintf('O serviço %s já está nesta OS.', $line->code));
            }
        }

        $this->services[$line->id] = $line;
        $this->record(new ServiceAddedToOrder($this->id->value, $line->serviceId, $line->price->cents, false, $now));
    }

    /** A mesma peça incluída de novo soma a quantidade na linha existente (preço da primeira inclusão). */
    public function addPart(PartLine $line, DateTimeImmutable $now): void
    {
        $this->guardEditable($now);

        foreach ($this->parts as $id => $existing) {
            if ($existing->inventoryItemId === $line->inventoryItemId) {
                $this->parts[$id] = $existing->withQuantity($existing->quantity->add($line->quantity));
                $this->record(new PartAddedToOrder($this->id->value, $line->inventoryItemId, $line->quantity->toDecimal(), false, $now));

                return;
            }
        }

        $this->parts[$line->id] = $line;
        $this->record(new PartAddedToOrder($this->id->value, $line->inventoryItemId, $line->quantity->toDecimal(), false, $now));
    }

    public function removeService(string $lineId, DateTimeImmutable $now): void
    {
        $this->guardEditable($now);

        if (! isset($this->services[$lineId])) {
            throw new OrderItemNotFoundException;
        }

        unset($this->services[$lineId]);
        $this->record(new OrderItemRemoved($this->id->value, $lineId, $now));
    }

    public function removePart(string $lineId, DateTimeImmutable $now): void
    {
        $this->guardEditable($now);

        if (! isset($this->parts[$lineId])) {
            throw new OrderItemNotFoundException;
        }

        unset($this->parts[$lineId]);
        $this->record(new OrderItemRemoved($this->id->value, $lineId, $now));
    }

    /** Calcular Orçamento + Notificar Cliente para Aprovação (prazo de 48 h). */
    public function sendBudget(DateTimeImmutable $now): void
    {
        if ($this->status !== ServiceOrderStatus::IN_DIAGNOSIS) {
            throw new DomainException('O orçamento só pode ser enviado com a OS em diagnóstico.');
        }

        if ($this->services === []) {
            throw new DomainException('Inclua ao menos um serviço antes de enviar o orçamento.');
        }

        $this->changeStatus(ServiceOrderStatus::AWAITING_APPROVAL, $now);
        $this->budgetSentAt = $now;
        $this->approvalDeadline = $now->add(new DateInterval(sprintf('PT%dH', self::APPROVAL_WINDOW_HOURS)));

        $this->record(new BudgetSent($this->id->value, $this->customerId, $this->budget()->total()->cents, false, $this->approvalDeadline, $now));
    }

    /** Devolve o orçamento inicial ao diagnóstico (ex.: faltou estoque na aprovação, cliente pediu ajuste). */
    public function returnToDiagnosis(DateTimeImmutable $now): void
    {
        $this->guardAwaitingInitialApproval();

        $this->changeStatus(ServiceOrderStatus::IN_DIAGNOSIS, $now);
        $this->approvalDeadline = null;

        $this->record(new BudgetReturnedToDiagnosis($this->id->value, $now));
    }

    // --- Fase 3: aprovação ------------------------------------------------------------------------

    /** Liberar OS para a oficina e iniciar o cronômetro. A Aplicação reserva pendingParts() na mesma transação. */
    public function approveBudget(DateTimeImmutable $now): void
    {
        $this->guardAwaitingInitialApproval();
        $this->guardDeadline($now);

        $this->approvePendingLines();
        $this->changeStatus(ServiceOrderStatus::IN_PROGRESS, $now);
        $this->approvedAt = $now;
        $this->executionStartedAt = $now;
        $this->approvalDeadline = null;

        $this->record(new BudgetApproved($this->id->value, $this->budget()->total()->cents, $now));
    }

    public function rejectBudget(DateTimeImmutable $now): void
    {
        $this->guardAwaitingInitialApproval();

        $this->record(new BudgetRejected($this->id->value, $now));
        $this->cancelWith(CancellationReason::BUDGET_REJECTED, $now);
    }

    // --- Fase 4: execução e reparo adicional ------------------------------------------------------

    /**
     * Solicitar Reparo / Insumo Adicional: os itens entram pendentes e a OS volta a aguardar aprovação.
     *
     * @param  list<ServiceLine>  $services
     * @param  list<PartLine>  $parts
     */
    public function requestAdditionalRepair(array $services, array $parts, DateTimeImmutable $now): void
    {
        if ($this->status !== ServiceOrderStatus::IN_PROGRESS) {
            throw new DomainException('Reparo adicional só pode ser solicitado com a OS em execução.');
        }

        if ($services === [] && $parts === []) {
            throw new DomainException('Informe ao menos um serviço ou peça para o reparo adicional.');
        }

        foreach ($services as $line) {
            foreach ($this->services as $existing) {
                if ($existing->serviceId === $line->serviceId) {
                    throw new DomainException(sprintf('O serviço %s já está nesta OS.', $line->code));
                }
            }

            $line = new ServiceLine($line->id, $line->serviceId, $line->code, $line->name, $line->price, $line->estimatedMinutes, additional: true);
            $this->services[$line->id] = $line;
            $this->record(new ServiceAddedToOrder($this->id->value, $line->serviceId, $line->price->cents, true, $now));
        }

        foreach ($parts as $line) {
            $line = new PartLine($line->id, $line->inventoryItemId, $line->sku, $line->name, $line->unit, $line->quantity, $line->unitPrice, additional: true);
            $this->parts[$line->id] = $line;
            $this->record(new PartAddedToOrder($this->id->value, $line->inventoryItemId, $line->quantity->toDecimal(), true, $now));
        }

        $this->changeStatus(ServiceOrderStatus::AWAITING_APPROVAL, $now);
        $this->awaitingAdditionalApproval = true;
        $this->approvalDeadline = $now->add(new DateInterval(sprintf('PT%dH', self::APPROVAL_WINDOW_HOURS)));

        $this->record(new BudgetSent($this->id->value, $this->customerId, $this->pendingBudget()->total()->cents, true, $this->approvalDeadline, $now));
    }

    /** Política: baixar (reservar) peças extras e integrar à OS. A Aplicação reserva pendingParts() antes. */
    public function authorizeAdditionalRepair(DateTimeImmutable $now): void
    {
        $this->guardAwaitingAdditionalApproval();
        $this->guardDeadline($now);

        $extra = $this->pendingBudget()->total()->cents;
        $this->approvePendingLines();
        $this->resumeExecution($now);

        $this->record(new AdditionalRepairAuthorized($this->id->value, $extra, $now));
    }

    /** Política: manter o escopo original e retomar a execução. */
    public function refuseAdditionalRepair(DateTimeImmutable $now): void
    {
        $this->guardAwaitingAdditionalApproval();
        $this->dropPendingLines();
        $this->resumeExecution($now);

        $this->record(new AdditionalRepairRefused($this->id->value, false, $now));
    }

    /** Política "Se 48 h sem resposta": orçamento inicial cancela a OS; adicional é tratado como recusado. */
    public function expireApproval(DateTimeImmutable $now): void
    {
        if ($this->status !== ServiceOrderStatus::AWAITING_APPROVAL || ! $this->isApprovalOverdue($now)) {
            throw new DomainException('A OS não está com o prazo de aprovação vencido.');
        }

        if ($this->awaitingAdditionalApproval) {
            $this->dropPendingLines();
            $this->resumeExecution($now);
            $this->record(new AdditionalRepairRefused($this->id->value, true, $now));

            return;
        }

        $this->cancelWith(CancellationReason::APPROVAL_TIMEOUT, $now);
    }

    /** Finalizar Execução dos Serviços: para o cronômetro. A Aplicação dá baixa no estoque na mesma transação. */
    public function finishServices(DateTimeImmutable $now): void
    {
        if ($this->status !== ServiceOrderStatus::IN_PROGRESS) {
            throw new DomainException('Só é possível finalizar uma OS em execução.');
        }

        $this->changeStatus(ServiceOrderStatus::COMPLETED, $now);
        $this->executionFinishedAt = $now;

        $this->record(new ServicesFinished($this->id->value, $this->customerId, (int) $this->executionSeconds(), $now));
    }

    // --- Fase 5: entrega ------------------------------------------------------------------------

    public function deliver(DateTimeImmutable $now): void
    {
        if ($this->status !== ServiceOrderStatus::COMPLETED) {
            throw new DomainException('O veículo só pode ser entregue com a OS finalizada.');
        }

        $this->changeStatus(ServiceOrderStatus::DELIVERED, $now);
        $this->deliveredAt = $now;

        $this->record(new VehicleDelivered($this->id->value, $now));
    }

    /** Desistência antes da finalização. Se havia aprovação, a Aplicação libera a reserva de estoque. */
    public function cancel(DateTimeImmutable $now): void
    {
        if (! $this->status->canTransitionTo(ServiceOrderStatus::CANCELED)) {
            throw new DomainException(sprintf('Uma OS %s não pode ser cancelada.', mb_strtolower($this->status->label())));
        }

        $this->cancelWith(CancellationReason::WORKSHOP_DECISION, $now);
    }

    // --- Consultas ------------------------------------------------------------------------------

    /** Orçamento atual: todos os itens da OS, aprovados ou ainda pendentes. */
    public function budget(): Budget
    {
        return Budget::of(array_values($this->services), array_values($this->parts));
    }

    /** Itens aguardando aprovação (orçamento inicial ou reparo adicional). */
    public function pendingBudget(): Budget
    {
        return Budget::of($this->pendingServices(), $this->pendingParts());
    }

    /** @return list<PartLine> peças ainda não aprovadas: é o que a Aplicação reserva na aprovação */
    public function pendingParts(): array
    {
        return array_values(array_filter($this->parts, static fn (PartLine $line): bool => ! $line->approved));
    }

    /** @return list<ServiceLine> */
    public function pendingServices(): array
    {
        return array_values(array_filter($this->services, static fn (ServiceLine $line): bool => ! $line->approved));
    }

    /** Já houve aprovação, logo existe reserva de estoque (se havia peças). */
    public function hasApprovedBudget(): bool
    {
        return $this->approvedAt !== null;
    }

    public function isApprovalOverdue(DateTimeImmutable $now): bool
    {
        return $this->approvalDeadline !== null && $now >= $this->approvalDeadline;
    }

    public function executionSeconds(): ?int
    {
        if ($this->executionStartedAt === null || $this->executionFinishedAt === null) {
            return null;
        }

        return max(0, $this->executionFinishedAt->getTimestamp() - $this->executionStartedAt->getTimestamp());
    }

    public function status(): ServiceOrderStatus
    {
        return $this->status;
    }

    /** @return list<ServiceLine> */
    public function services(): array
    {
        return array_values($this->services);
    }

    /** @return list<PartLine> */
    public function parts(): array
    {
        return array_values($this->parts);
    }

    /** @return list<StatusChange> */
    public function history(): array
    {
        return $this->history;
    }

    /** @return list<StatusChange> mudanças ainda não gravadas (o histórico é só de inclusão) */
    public function pullNewHistory(): array
    {
        $new = $this->newHistory;
        $this->newHistory = [];

        return $new;
    }

    public function openedAt(): DateTimeImmutable
    {
        return $this->openedAt;
    }

    public function budgetSentAt(): ?DateTimeImmutable
    {
        return $this->budgetSentAt;
    }

    public function approvalDeadline(): ?DateTimeImmutable
    {
        return $this->approvalDeadline;
    }

    public function approvedAt(): ?DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function executionStartedAt(): ?DateTimeImmutable
    {
        return $this->executionStartedAt;
    }

    public function executionFinishedAt(): ?DateTimeImmutable
    {
        return $this->executionFinishedAt;
    }

    public function deliveredAt(): ?DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function canceledAt(): ?DateTimeImmutable
    {
        return $this->canceledAt;
    }

    public function cancellationReason(): ?CancellationReason
    {
        return $this->cancellationReason;
    }

    public function isAwaitingAdditionalApproval(): bool
    {
        return $this->awaitingAdditionalApproval;
    }

    // --- Internos -------------------------------------------------------------------------------

    private function guardEditable(DateTimeImmutable $now): void
    {
        if ($this->status === ServiceOrderStatus::RECEIVED) {
            $this->changeStatus(ServiceOrderStatus::IN_DIAGNOSIS, $now);
            $this->record(new DiagnosisStarted($this->id->value, $now));

            return;
        }

        if ($this->status !== ServiceOrderStatus::IN_DIAGNOSIS) {
            throw new DomainException('Itens só podem ser alterados durante o diagnóstico. Na execução, solicite um reparo adicional.');
        }
    }

    private function guardAwaitingInitialApproval(): void
    {
        if ($this->status !== ServiceOrderStatus::AWAITING_APPROVAL || $this->awaitingAdditionalApproval) {
            throw new DomainException('A OS não está aguardando aprovação do orçamento inicial.');
        }
    }

    private function guardAwaitingAdditionalApproval(): void
    {
        if ($this->status !== ServiceOrderStatus::AWAITING_APPROVAL || ! $this->awaitingAdditionalApproval) {
            throw new DomainException('A OS não está aguardando aprovação de reparo adicional.');
        }
    }

    private function guardDeadline(DateTimeImmutable $now): void
    {
        if ($this->isApprovalOverdue($now)) {
            throw new DomainException('O prazo de aprovação venceu.');
        }
    }

    private function approvePendingLines(): void
    {
        foreach ($this->services as $id => $line) {
            $this->services[$id] = $line->approve();
        }

        foreach ($this->parts as $id => $line) {
            $this->parts[$id] = $line->approve();
        }
    }

    private function dropPendingLines(): void
    {
        $this->services = array_filter($this->services, static fn (ServiceLine $line): bool => $line->approved);
        $this->parts = array_filter($this->parts, static fn (PartLine $line): bool => $line->approved);
    }

    private function resumeExecution(DateTimeImmutable $now): void
    {
        $this->changeStatus(ServiceOrderStatus::IN_PROGRESS, $now);
        $this->awaitingAdditionalApproval = false;
        $this->approvalDeadline = null;
    }

    private function cancelWith(CancellationReason $reason, DateTimeImmutable $now): void
    {
        $this->changeStatus(ServiceOrderStatus::CANCELED, $now);
        $this->canceledAt = $now;
        $this->cancellationReason = $reason;
        $this->approvalDeadline = null;
        $this->awaitingAdditionalApproval = false;

        $this->record(new ServiceOrderCanceled($this->id->value, $this->customerId, $reason->value, $this->hasApprovedBudget(), $now));
    }

    private function changeStatus(ServiceOrderStatus $target, DateTimeImmutable $now): void
    {
        $this->status = $this->status->transitionTo($target);
        $this->appendHistory($target, $now);
    }

    private function appendHistory(ServiceOrderStatus $status, DateTimeImmutable $now): void
    {
        $change = new StatusChange($status, $now);
        $this->history[] = $change;
        $this->newHistory[] = $change;
    }
}
