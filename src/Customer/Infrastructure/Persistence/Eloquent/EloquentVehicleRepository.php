<?php

declare(strict_types=1);

namespace Oficina\Customer\Infrastructure\Persistence\Eloquent;

use Oficina\Customer\Domain\Entity\Vehicle;
use Oficina\Customer\Domain\Repository\VehicleRepositoryInterface;
use Oficina\Shared\Domain\ValueObject\LicensePlate;

final class EloquentVehicleRepository implements VehicleRepositoryInterface
{
    public function save(Vehicle $vehicle): Vehicle
    {
        $model = $vehicle->id() !== null
            ? VehicleModel::query()->find($vehicle->id())
            : null;

        if ($model === null) {
            $model = new VehicleModel;
            if ($vehicle->id() !== null) {
                $model->id = $vehicle->id();
            }
        }

        $model->fill([
            'customer_id' => $vehicle->customerId(),
            'license_plate' => $vehicle->licensePlate()->value,
            'brand' => $vehicle->brand(),
            'model' => $vehicle->model(),
            'year' => $vehicle->year(),
            'color' => $vehicle->color(),
            'mileage' => $vehicle->mileage(),
            'active' => $vehicle->isActive(),
        ]);

        $model->save();

        return $this->toDomain($model);
    }

    public function findById(string $id): ?Vehicle
    {
        $model = VehicleModel::query()->find($id);

        return $model !== null ? $this->toDomain($model) : null;
    }

    public function findByLicensePlate(LicensePlate $licensePlate): ?Vehicle
    {
        $model = VehicleModel::query()
            ->where('license_plate', $licensePlate->value)
            ->first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    /**
     * @return list<Vehicle>
     */
    public function findByCustomerId(string $customerId, ?bool $onlyActive = null): array
    {
        $query = VehicleModel::query()->where('customer_id', $customerId);

        if ($onlyActive !== null) {
            $query->where('active', $onlyActive);
        }

        /** @var list<VehicleModel> $models */
        $models = $query->orderBy('created_at', 'desc')->get()->all();

        return array_map(
            fn (VehicleModel $model): Vehicle => $this->toDomain($model),
            $models,
        );
    }

    /**
     * @return list<Vehicle>
     */
    public function findAll(?bool $onlyActive = null): array
    {
        $query = VehicleModel::query();

        if ($onlyActive !== null) {
            $query->where('active', $onlyActive);
        }

        /** @var list<VehicleModel> $models */
        $models = $query->orderBy('created_at', 'desc')->get()->all();

        return array_map(
            fn (VehicleModel $model): Vehicle => $this->toDomain($model),
            $models,
        );
    }

    public function existsByLicensePlate(LicensePlate $licensePlate, ?string $excludeId = null): bool
    {
        $query = VehicleModel::query()->where('license_plate', $licensePlate->value);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    private function toDomain(VehicleModel $model): Vehicle
    {
        return Vehicle::create(
            licensePlate: LicensePlate::from($model->license_plate),
            brand: $model->brand,
            model: $model->model,
            year: (int) $model->year,
            customerId: $model->customer_id,
            color: $model->color,
            mileage: (int) $model->mileage,
            active: (bool) $model->active,
            id: $model->id,
        );
    }
}
