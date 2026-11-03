<?php

declare(strict_types=1);

namespace Oficina\Customer\Infrastructure;

use Illuminate\Database\UniqueConstraintViolationException;
use Oficina\Customer\Domain\Customer;
use Oficina\Customer\Domain\CustomerId;
use Oficina\Customer\Domain\CustomerRepository;
use Oficina\Customer\Domain\Exception\DocumentAlreadyRegisteredException;
use Oficina\Customer\Domain\Phone;
use Oficina\Shared\Application\Page;
use Oficina\Shared\Domain\ValueObject\Document;
use Oficina\Shared\Domain\ValueObject\Email;

final class EloquentCustomerRepository implements CustomerRepository
{
    public function save(Customer $customer): void
    {
        $model = CustomerModel::query()->find($customer->id->value) ?? new CustomerModel;
        $model->id = $customer->id->value;
        $model->fill([
            'name' => $customer->name(),
            'document' => $customer->document()->value,
            'email' => $customer->email()->value,
            'phone' => $customer->phone()?->value,
            'active' => $customer->isActive(),
        ]);

        try {
            $model->save();
        } catch (UniqueConstraintViolationException) {
            // Corrida entre a checagem do caso de uso e o INSERT/UPDATE: o índice único é a última barreira.
            throw new DocumentAlreadyRegisteredException;
        }
    }

    public function findById(CustomerId $id): ?Customer
    {
        $model = CustomerModel::query()->find($id->value);

        return $model === null ? null : $this->toEntity($model);
    }

    public function findByDocument(Document $document): ?Customer
    {
        $model = CustomerModel::query()->where('document', $document->value)->first();

        return $model === null ? null : $this->toEntity($model);
    }

    public function delete(Customer $customer): void
    {
        CustomerModel::query()->whereKey($customer->id->value)->delete();
    }

    public function paginate(int $page, int $perPage, ?bool $onlyActive = null): Page
    {
        $query = CustomerModel::query();

        if ($onlyActive !== null) {
            $query->where('active', $onlyActive);
        }

        $total = (clone $query)->count();
        $models = $query
            ->orderBy('name')
            ->orderBy('id')
            ->forPage($page, $perPage)
            ->get();

        return new Page(
            $models->map(fn (CustomerModel $model): Customer => $this->toEntity($model))->values()->all(),
            $total,
            $page,
            $perPage,
        );
    }

    private function toEntity(CustomerModel $model): Customer
    {
        return Customer::reconstitute(
            CustomerId::from($model->id),
            $model->name,
            Document::from($model->document),
            Email::from($model->email),
            $model->phone === null ? null : Phone::from($model->phone),
            (bool) $model->active,
        );
    }
}
