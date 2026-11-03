<?php

declare(strict_types=1);

namespace Oficina\Catalog\Domain;

use Oficina\Shared\Application\Page;

interface ServiceRepository
{
    public function save(Service $service): void;

    public function findById(ServiceId $id): ?Service;

    /** SELECT ... FOR UPDATE: use dentro de uma transação. */
    public function lockById(ServiceId $id): ?Service;

    public function codeExists(ServiceCode $code): bool;

    /** Nome comparado sem diferenciar maiúsculas; ignora o próprio serviço numa atualização. */
    public function nameExists(string $name, ?ServiceId $exceptId = null): bool;

    /** @return list<Service> serviços (ativos ou não) que têm a peça como padrão, bloqueados para atualização */
    public function lockWithStandardPart(string $inventoryItemId): array;

    /** @return Page<Service> ordenado por nome */
    public function paginate(int $page, int $perPage, ServiceFilter $filter): Page;
}
