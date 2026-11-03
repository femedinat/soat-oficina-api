<?php

declare(strict_types=1);

namespace Oficina\Catalog\Infrastructure;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Oficina\Catalog\Domain\Exception\ServiceAlreadyRegisteredException;
use Oficina\Catalog\Domain\Service;
use Oficina\Catalog\Domain\ServiceCode;
use Oficina\Catalog\Domain\ServiceFilter;
use Oficina\Catalog\Domain\ServiceId;
use Oficina\Catalog\Domain\ServiceRepository;
use Oficina\Catalog\Domain\StandardPart;
use Oficina\Shared\Application\Page;
use Oficina\Shared\Domain\ValueObject\Money;

final class EloquentServiceRepository implements ServiceRepository
{
    public function save(Service $service): void
    {
        try {
            DB::transaction(function () use ($service): void {
                $model = ServiceModel::query()->find($service->id->value) ?? new ServiceModel;
                $model->id = $service->id->value;
                $model->fill([
                    'code' => $service->code()->value,
                    'name' => $service->name(),
                    'description' => $service->description(),
                    'category' => $service->category(),
                    'labor_price_cents' => $service->laborPrice()->cents,
                    'estimated_minutes' => $service->estimatedMinutes(),
                    'active' => $service->isActive(),
                ]);
                $model->save();

                // Peças padrão são parte do agregado: regravadas junto com ele.
                ServiceStandardPartModel::query()->where('service_id', $service->id->value)->delete();
                $rows = array_map(static fn (StandardPart $part): array => [
                    'service_id' => $service->id->value,
                    'inventory_item_id' => $part->inventoryItemId,
                    'quantity' => $part->quantity(),
                ], $service->standardParts());

                if ($rows !== []) {
                    ServiceStandardPartModel::query()->insert($rows);
                }
            });
        } catch (UniqueConstraintViolationException) {
            // Corrida entre a checagem do caso de uso e o INSERT: o índice único é a última barreira.
            throw new ServiceAlreadyRegisteredException('Já existe um serviço com este código ou nome.');
        }
    }

    public function findById(ServiceId $id): ?Service
    {
        $model = ServiceModel::query()->with('standardParts')->find($id->value);

        return $model === null ? null : $this->toEntity($model);
    }

    public function lockById(ServiceId $id): ?Service
    {
        $model = ServiceModel::query()->whereKey($id->value)->lockForUpdate()->first();

        return $model === null ? null : $this->toEntity($model->load('standardParts'));
    }

    public function codeExists(ServiceCode $code): bool
    {
        return ServiceModel::query()->where('code', $code->value)->exists();
    }

    public function nameExists(string $name, ?ServiceId $exceptId = null): bool
    {
        return ServiceModel::query()
            ->whereRaw('lower(name) = ?', [mb_strtolower(trim($name))])
            ->when($exceptId !== null, static fn (Builder $q) => $q->whereKeyNot($exceptId?->value))
            ->exists();
    }

    public function lockWithStandardPart(string $inventoryItemId): array
    {
        return ServiceModel::query()
            ->whereIn('id', ServiceStandardPartModel::query()->select('service_id')->where('inventory_item_id', strtolower($inventoryItemId)))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->map(fn (ServiceModel $model): Service => $this->toEntity($model->load('standardParts')))
            ->values()
            ->all();
    }

    public function paginate(int $page, int $perPage, ServiceFilter $filter): Page
    {
        $query = ServiceModel::query()->with('standardParts');

        if (! $filter->includeInactive) {
            $query->where('active', true);
        }

        if ($filter->category !== null) {
            $query->whereRaw('lower(category) = ?', [mb_strtolower($filter->category)]);
        }

        if ($filter->search !== null) {
            // Escapa os curingas do LIKE: o termo do usuário é sempre literal.
            $term = '%'.addcslashes($filter->search, '%_\\').'%';
            $query->where(static fn (Builder $q) => $q->where('name', 'ilike', $term)->orWhere('code', 'ilike', $term));
        }

        $total = (clone $query)->count();
        $models = $query->orderBy('name')->orderBy('id')->forPage($page, $perPage)->get();

        return new Page(
            $models->map(fn (ServiceModel $model): Service => $this->toEntity($model))->values()->all(),
            $total,
            $page,
            $perPage,
        );
    }

    private function toEntity(ServiceModel $model): Service
    {
        return Service::reconstitute(
            ServiceId::from($model->id),
            ServiceCode::from($model->code),
            $model->name,
            $model->description,
            $model->category,
            Money::fromCents($model->labor_price_cents),
            $model->estimated_minutes,
            $model->standardParts
                ->map(static fn (ServiceStandardPartModel $part): StandardPart => StandardPart::of($part->inventory_item_id, $part->quantity))
                ->values()
                ->all(),
            $model->active,
        );
    }
}
