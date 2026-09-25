<?php

declare(strict_types=1);

namespace Oficina\Shared\Domain\Exception;

/**
 * Violação de regra de negócio. A camada de Interfaces traduz para HTTP 422.
 */
class DomainException extends \DomainException {}
