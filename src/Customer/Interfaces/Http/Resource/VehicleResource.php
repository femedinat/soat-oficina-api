<?php

declare(strict_types=1);

namespace Oficina\Customer\Interfaces\Http\Resource;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Oficina\Customer\Application\DTO\VehicleOutputDto;

/**
 * @property VehicleOutputDto $resource
 */
final class VehicleResource extends JsonResource
{
    /**
     * @return array{
     *     id: string,
     *     customer_id: ?string,
     *     license_plate: string,
     *     is_mercosul: bool,
     *     brand: string,
     *     model: string,
     *     year: int,
     *     color: ?string,
     *     mileage: int,
     *     active: bool
     * }
     */
    public function toArray(Request $request): array
    {
        return $this->resource->toArray();
    }
}
