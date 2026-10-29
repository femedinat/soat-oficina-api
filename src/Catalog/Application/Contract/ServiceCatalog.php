<?php

declare(strict_types=1);

namespace Oficina\Catalog\Application\Contract;

use Oficina\Catalog\Domain\Exception\InactiveServiceException;
use Oficina\Catalog\Domain\Exception\ServiceNotFoundException;

/** Contrato publicado pelo Catálogo para a Ordem de Serviço (a OS não acessa tabelas nem o agregado). */
interface ServiceCatalog
{
    /**
     * @throws ServiceNotFoundException
     * @throws InactiveServiceException serviço inativo não entra em orçamento novo
     */
    public function quote(string $serviceId): ServiceQuote;
}
