<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Application;

use Oficina\ServiceOrder\Domain\Exception\ServiceOrderNotFoundException;
use Oficina\ServiceOrder\Domain\ServiceOrderId;
use Oficina\ServiceOrder\Domain\ServiceOrderRepository;
use Oficina\Shared\Domain\Exception\InvalidValueException;
use Oficina\Shared\Domain\ValueObject\Document;

/**
 * Caso de uso: acompanhamento público da OS (sem login), pelo número da OS + CPF/CNPJ do cliente.
 * Qualquer divergência (OS inexistente, documento inválido ou de outro cliente) responde 404 igual,
 * para não servir de oráculo de existência. Devolve só o mínimo (sem itens nem dados pessoais).
 */
final readonly class TrackServiceOrder
{
    public function __construct(
        private ServiceOrderRepository $orders,
        private CustomerDirectory $customers,
        private VehicleRegistry $vehicles,
    ) {}

    public function execute(string $orderId, string $document): TrackingView
    {
        try {
            $order = $this->orders->findById(ServiceOrderId::from($orderId));
            $informed = Document::from($document);
        } catch (InvalidValueException) {
            throw new ServiceOrderNotFoundException;
        }

        $customer = $order === null ? null : $this->customers->findById($order->customerId);

        if ($order === null || $customer === null || ! hash_equals($customer->document, $informed->value)) {
            throw new ServiceOrderNotFoundException;
        }

        return TrackingView::of(ServiceOrderView::fromEntity($order), $this->vehicles->findById($order->vehicleId));
    }
}
