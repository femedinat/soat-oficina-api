<?php

declare(strict_types=1);

namespace Oficina\Customer\Application\UseCase;

use Oficina\Customer\Application\DTO\VehicleOutputDto;
use Oficina\Customer\Domain\Entity\Vehicle;
use Oficina\Customer\Domain\Repository\VehicleRepositoryInterface;
use Oficina\Shared\Application\Page;

final readonly class ListVehiclesUseCase
{
    public const int MAX_PER_PAGE = 100;

    public function __construct(
        private VehicleRepositoryInterface $vehicleRepository,
    ) {}

    /**
     * @return Page<VehicleOutputDto>
     */
    public function execute(
        int $page = 1,
        int $perPage = 15,
        ?string $customerId = null,
        ?bool $onlyActive = null,
    ): Page {
        $limit = min(max(1, $perPage), self::MAX_PER_PAGE);
        $normalizedCustomerId = $customerId !== null && trim($customerId) !== ''
            ? trim($customerId)
            : null;

        $result = $this->vehicleRepository->paginate(
            page: max(1, $page),
            perPage: $limit,
            customerId: $normalizedCustomerId,
            onlyActive: $onlyActive,
        );

        return new Page(
            array_map(
                static fn (Vehicle $vehicle): VehicleOutputDto => VehicleOutputDto::fromEntity($vehicle),
                $result->items,
            ),
            $result->total,
            $result->page,
            $result->perPage,
        );
    }
}
