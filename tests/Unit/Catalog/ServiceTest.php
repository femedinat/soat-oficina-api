<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog;

use Oficina\Catalog\Domain\Event\ServiceDeactivated;
use Oficina\Catalog\Domain\Event\ServiceEstimatedTimeAdjusted;
use Oficina\Catalog\Domain\Event\ServicePriceChanged;
use Oficina\Catalog\Domain\Event\ServiceReactivated;
use Oficina\Catalog\Domain\Event\ServiceRegistered;
use Oficina\Catalog\Domain\Event\ServiceUpdated;
use Oficina\Catalog\Domain\Event\StandardPartLinked;
use Oficina\Catalog\Domain\Event\StandardPartUnlinked;
use Oficina\Catalog\Domain\Exception\InactiveServiceException;
use Oficina\Catalog\Domain\Service;
use Oficina\Catalog\Domain\ServiceCode;
use Oficina\Catalog\Domain\ServiceId;
use Oficina\Catalog\Domain\StandardPart;
use Oficina\Shared\Domain\Event\DomainEvent;
use Oficina\Shared\Domain\Exception\DomainException;
use Oficina\Shared\Domain\Exception\InvalidValueException;
use Oficina\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ServiceTest extends TestCase
{
    private const string OIL = '0b0e7d0c-8a39-4a5b-9e9e-4d1d9a1f2c10';

    private const string FILTER = '1c1f8e1d-9b4a-4b6c-8f8f-5e2e8b2a3d21';

    private function service(): Service
    {
        $service = Service::register(ServiceCode::from(' troca-oleo '), ' Troca de óleo ', 'Óleo e filtro', 'Preventiva', Money::fromDecimal('120.00'), 60);
        $service->pullEvents();

        return $service;
    }

    /** @return list<class-string> */
    private static function names(Service $service): array
    {
        return array_map(static fn (DomainEvent $e): string => $e::class, $service->pullEvents());
    }

    public function test_registers_with_normalized_values_and_event(): void
    {
        $service = Service::register(ServiceCode::from(' troca-oleo '), ' Troca de óleo ', '  ', null, Money::fromDecimal('120'), 60);

        self::assertSame('TROCA-OLEO', $service->code()->value);
        self::assertSame('Troca de óleo', $service->name());
        self::assertNull($service->description());
        self::assertNull($service->category());
        self::assertSame(12000, $service->laborPrice()->cents);
        self::assertSame(60, $service->estimatedMinutes());
        self::assertTrue($service->isActive());
        self::assertSame([], $service->standardParts());

        $events = $service->pullEvents();
        self::assertInstanceOf(ServiceRegistered::class, $events[0]);
        self::assertSame(12000, $events[0]->laborPriceCents);
        self::assertNotNull($events[0]->occurredAt());
    }

    /** @return array<string, array{string}> */
    public static function invalidCodes(): array
    {
        return ['curto' => ['A'], 'espaço' => ['TROCA OLEO'], 'acento' => ['REVISÃO'], 'longo' => [str_repeat('A', 31)], 'símbolo inicial' => ['-ABC']];
    }

    #[DataProvider('invalidCodes')]
    public function test_rejects_invalid_code(string $code): void
    {
        $this->expectException(InvalidValueException::class);
        ServiceCode::from($code);
    }

    public function test_rejects_blank_name_long_texts_and_invalid_minutes(): void
    {
        $code = ServiceCode::from('ALINHAMENTO');
        $price = Money::fromDecimal('80');
        $cases = [
            static fn () => Service::register($code, '  ', null, null, $price, 30),
            static fn () => Service::register($code, str_repeat('a', Service::NAME_MAX_LENGTH + 1), null, null, $price, 30),
            static fn () => Service::register($code, 'Alinhamento', str_repeat('a', Service::DESCRIPTION_MAX_LENGTH + 1), null, $price, 30),
            static fn () => Service::register($code, 'Alinhamento', null, str_repeat('a', Service::CATEGORY_MAX_LENGTH + 1), $price, 30),
            static fn () => Service::register($code, 'Alinhamento', null, null, $price, 0),
            static fn () => Service::register($code, 'Alinhamento', null, null, $price, Service::MAX_ESTIMATED_MINUTES + 1),
        ];

        foreach ($cases as $i => $case) {
            try {
                $case();
                self::fail("Caso $i deveria falhar.");
            } catch (InvalidValueException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_revises_data_but_keeps_code(): void
    {
        $service = $this->service();
        $service->revise('Troca de óleo completa', null, 'Motor');

        self::assertSame('Troca de óleo completa', $service->name());
        self::assertNull($service->description());
        self::assertSame('Motor', $service->category());
        self::assertSame('TROCA-OLEO', $service->code()->value);
        self::assertSame([ServiceUpdated::class], self::names($service));
    }

    public function test_price_change_records_previous_and_ignores_same_price(): void
    {
        $service = $this->service();
        $service->changePrice(Money::fromDecimal('120.00'));
        self::assertSame([], self::names($service));

        $service->changePrice(Money::fromDecimal('135.50'));
        $events = $service->pullEvents();
        self::assertInstanceOf(ServicePriceChanged::class, $events[0]);
        self::assertSame(12000, $events[0]->previousCents);
        self::assertSame(13550, $events[0]->newCents);
    }

    public function test_adjusts_estimated_time_only_when_different(): void
    {
        $service = $this->service();
        $service->adjustEstimatedTime(60);
        self::assertSame([], self::names($service));

        $service->adjustEstimatedTime(45);
        self::assertSame(45, $service->estimatedMinutes());
        self::assertSame([ServiceEstimatedTimeAdjusted::class], self::names($service));
    }

    public function test_links_relinks_and_unlinks_standard_parts(): void
    {
        $service = $this->service();
        $service->linkStandardPart(StandardPart::of(self::OIL, '4,5'));
        $service->linkStandardPart(StandardPart::of(strtoupper(self::FILTER), '1'));
        $service->linkStandardPart(StandardPart::of(self::OIL, '5'));

        self::assertCount(2, $service->standardParts());
        self::assertSame('5.000', $service->standardParts()[0]->quantity());
        self::assertTrue($service->hasStandardPart(strtoupper(self::FILTER)));

        $service->unlinkStandardPart(self::FILTER);
        self::assertFalse($service->hasStandardPart(self::FILTER));
        self::assertSame([StandardPartLinked::class, StandardPartLinked::class, StandardPartLinked::class, StandardPartUnlinked::class], self::names($service));

        $this->expectException(DomainException::class);
        $service->unlinkStandardPart(self::FILTER);
    }

    public function test_rejects_invalid_standard_part(): void
    {
        foreach ([['nao-e-uuid', '1'], [self::OIL, '0'], [self::OIL, '1.2345'], [self::OIL, 'abc']] as [$id, $qty]) {
            try {
                StandardPart::of($id, $qty);
                self::fail("$id / $qty deveria falhar.");
            } catch (InvalidValueException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_inactive_service_is_hidden_from_new_budgets_and_parts(): void
    {
        $service = $this->service();
        $service->deactivate();
        $service->deactivate();

        self::assertFalse($service->isActive());
        self::assertSame([ServiceDeactivated::class], self::names($service));

        try {
            $service->guardAvailableForBudget();
            self::fail('Serviço inativo não pode entrar em orçamento.');
        } catch (InactiveServiceException) {
            $this->addToAssertionCount(1);
        }

        try {
            $service->linkStandardPart(StandardPart::of(self::OIL, '1'));
            self::fail('Serviço inativo não recebe peça padrão.');
        } catch (InactiveServiceException) {
            $this->addToAssertionCount(1);
        }

        $service->reactivate();
        $service->reactivate();
        $service->guardAvailableForBudget();
        self::assertSame([ServiceReactivated::class], self::names($service));
    }

    public function test_reconstitutes_without_events(): void
    {
        $id = ServiceId::generate();
        $service = Service::reconstitute($id, ServiceCode::from('BALANCEAMENTO'), 'Balanceamento', null, null, Money::fromCents(5000), 40, [StandardPart::of(self::OIL, '2')], false);

        self::assertSame($id->value, ServiceId::from(strtoupper($id->value))->value);
        self::assertFalse($service->isActive());
        self::assertTrue($service->hasStandardPart(self::OIL));
        self::assertSame([], $service->pullEvents());

        $this->expectException(InvalidValueException::class);
        ServiceId::from('123');
    }
}
