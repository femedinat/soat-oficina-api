<?php

declare(strict_types=1);

namespace Oficina\Shared\Domain\Exception;

/** Conflito com o estado atual (ex.: duplicidade). A camada de Interfaces traduz para HTTP 409. */
class ConflictException extends DomainException {}
