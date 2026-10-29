<?php

declare(strict_types=1);

namespace Oficina\Catalog\Application;

use Oficina\Catalog\Domain\Service;
use Oficina\Catalog\Domain\ServiceFilter;
use Oficina\Catalog\Domain\ServiceRepository;
use Oficina\Shared\Application\Page;

/** Caso de uso: catálogo paginado de serviços (busca por nome ou código, filtro por categoria). */
final readonly class ListServices
{
    public const int MAX_PER_PAGE = 100;

    public function __construct(private ServiceRepository $services) {}

    /** @return Page<ServiceView> */
    public function execute(int $page = 1, int $perPage = 15, ?string $search = null, ?string $category = null, bool $includeInactive = false): Page
    {
        $search = $search === null ? '' : trim($search);
        $category = $category === null ? '' : trim($category);

        $result = $this->services->paginate(
            max(1, $page),
            min(max(1, $perPage), self::MAX_PER_PAGE),
            new ServiceFilter(
                search: $search === '' ? null : mb_substr($search, 0, 100),
                category: $category === '' ? null : mb_substr($category, 0, 60),
                includeInactive: $includeInactive,
            ),
        );

        return new Page(
            array_map(static fn (Service $service): ServiceView => ServiceView::fromEntity($service), $result->items),
            $result->total,
            $result->page,
            $result->perPage,
        );
    }
}
