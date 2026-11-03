<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Application;

use Oficina\ServiceOrder\Domain\ServiceOrder;
use Oficina\ServiceOrder\Domain\ServiceOrderRepository;
use Oficina\Shared\Application\Clock;
use Oficina\Shared\Application\EventPublisher;
use Oficina\Shared\Application\TransactionManager;
use Oficina\Shared\Domain\Exception\DomainException;

/**
 * Caso de uso (Fase 1 — Abertura): identificar cliente por CPF/CNPJ, identificar ou cadastrar o veículo e abrir a OS.
 */
final readonly class OpenServiceOrder
{
    public function __construct(
        private CustomerDirectory $customers,
        private VehicleRegistry $vehicles,
        private ServiceOrderRepository $orders,
        private TransactionManager $transaction,
        private EventPublisher $events,
        private Clock $clock,
    ) {}

    public function execute(string $document, VehicleData $vehicleData): ServiceOrderView
    {
        return $this->transaction->run(function () use ($document, $vehicleData): ServiceOrderView {
            $customer = $this->customers->findByDocument($document);

            if (! $customer->active) {
                throw new DomainException('Cliente inativo: reative o cadastro antes de abrir uma OS.');
            }

            $vehicle = $this->vehicles->identifyOrRegister($customer->id, $vehicleData);
            $order = ServiceOrder::open($customer->id, $vehicle->id, $this->clock->now());

            $this->orders->save($order);
            $this->events->publish(...$order->pullEvents());

            return ServiceOrderView::fromEntity($order);
        });
    }
}
