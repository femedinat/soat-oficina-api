<?php

declare(strict_types=1);

namespace Oficina\Customer\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\UniqueConstraintViolationException;
use Oficina\Customer\Domain\Entity\Vehicle;
use Oficina\Customer\Domain\Exception\VehicleAlreadyExistsException;
use Oficina\Customer\Domain\Repository\VehicleRepositoryInterface;
use Oficina\Shared\Application\Page;
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

        try {
            $model->save();
        } catch (UniqueConstraintViolationException) {
            throw VehicleAlreadyExistsException::withLicensePlate($vehicle->licensePlate()->value);
        }

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
     * @return Page<Vehicle>
     */
    public function paginate(int $page, int $perPage, ?string $customerId = null, ?bool $onlyActive = null): Page
    {
        $query = VehicleModel::query();

        if ($customerId !== null) {
            $query->where('customer_id', $customerId);
        }

        if ($onlyActive !== null) {
            $query->where('active', $onlyActive);
        }

        $total = (clone $query)->count();
        $models = $query
            ->orderBy('created_at', 'desc')
            ->orderBy('id')
            ->forPage($page, $perPage)
            ->get();

        return new Page(
            $models->map(fn (VehicleModel $model): Vehicle => $this->toDomain($model))->values()->all(),
            $total,
            $page,
            $perPage,
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
