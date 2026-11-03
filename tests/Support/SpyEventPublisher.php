<?php

declare(strict_types=1);

namespace Tests\Support;

use Oficina\Shared\Application\EventPublisher;
use Oficina\Shared\Domain\Event\DomainEvent;

final class SpyEventPublisher implements EventPublisher
{
    /** @var list<DomainEvent> */
    public array $published = [];

    public function publish(DomainEvent ...$events): void
    {
        array_push($this->published, ...array_values($events));
    }

    /** @return list<class-string> */
    public function names(): array
    {
        return array_map(static fn (DomainEvent $e): string => $e::class, $this->published);
    }

    /**
     * @template T of DomainEvent
     *
     * @param  class-string<T>  $class
     * @return list<T>
     */
    public function ofType(string $class): array
    {
        return array_values(array_filter($this->published, static fn (DomainEvent $e): bool => $e instanceof $class));
    }
}
