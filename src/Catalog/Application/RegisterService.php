<?php

declare(strict_types=1);

namespace Oficina\Catalog\Application;

use Oficina\Catalog\Domain\Exception\ServiceAlreadyRegisteredException;
use Oficina\Catalog\Domain\Service;
use Oficina\Catalog\Domain\ServiceCode;
use Oficina\Catalog\Domain\ServiceRepository;
use Oficina\Shared\Application\EventPublisher;
use Oficina\Shared\Application\TransactionManager;
use Oficina\Shared\Domain\ValueObject\Money;

/** Caso de uso: cadastrar novo serviço (política: verificar código e nome únicos). */
final readonly class RegisterService
{
    public function __construct(
        private ServiceRepository $services,
        private TransactionManager $transaction,
        private EventPublisher $events,
    ) {}

    public function execute(RegisterServiceInput $input): ServiceView
    {
        $service = Service::register(
            ServiceCode::from($input->code),
            $input->name,
            $input->description,
            $input->category,
            Money::fromDecimal($input->laborPrice),
            $input->estimatedMinutes,
        );

        $this->transaction->run(function () use ($service): void {
            if ($this->services->codeExists($service->code())) {
                throw ServiceAlreadyRegisteredException::withCode($service->code()->value);
            }

            if ($this->services->nameExists($service->name())) {
                throw ServiceAlreadyRegisteredException::withName($service->name());
            }

            $this->services->save($service);
            $this->events->publish(...$service->pullEvents());
        });

        return ServiceView::fromEntity($service);
    }
}
