<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog;

use DateTimeImmutable;
use Oficina\Catalog\Domain\Event\ServiceDeactivated;
use Oficina\Catalog\Domain\Event\ServiceEstimatedTimeAdjusted;
use Oficina\Catalog\Domain\Event\ServicePriceChanged;
use Oficina\Catalog\Domain\Event\ServiceReactivated;
use Oficina\Catalog\Domain\Event\ServiceRegistered;
use Oficina\Catalog\Domain\Event\ServiceUpdated;
use Oficina\Catalog\Domain\Event\StandardPartLinked;
use Oficina\Catalog\Domain\Event\StandardPartUnlinked;
use Oficina\Shared\Domain\Event\DomainEvent;
use PHPUnit\Framework\TestCase;

/** Os eventos do Event Storming de Serviço existem no código com a data do fato. */
final class DomainEventsTest extends TestCase
{
    public function test_every_event_records_when_it_happened(): void
    {
        $at = new DateTimeImmutable('2026-10-29 10:00:00');
        $id = '2f6b8f4e-3b0e-4c1f-9d77-0a6f6f1f0c11';

        $events = [
            new ServiceRegistered($id, 'TROCA-OLEO', 'Troca de óleo', 12000, $at),
            new ServiceUpdated($id, $at),
            new ServicePriceChanged($id, 12000, 13000, $at),
            new ServiceEstimatedTimeAdjusted($id, 60, 45, $at),
            new StandardPartLinked($id, $id, '4.500', $at),
            new StandardPartUnlinked($id, $id, $at),
            new ServiceDeactivated($id, $at),
            new ServiceReactivated($id, $at),
        ];

        foreach ($events as $event) {
            self::assertInstanceOf(DomainEvent::class, $event);
            self::assertSame($at, $event->occurredAt());
        }
    }
}
