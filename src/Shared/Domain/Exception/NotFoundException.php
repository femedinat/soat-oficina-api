<?php

declare(strict_types=1);

namespace Oficina\Shared\Domain\Exception;

/** Recurso de domínio inexistente. A camada de Interfaces traduz para HTTP 404. */
class NotFoundException extends DomainException {}
