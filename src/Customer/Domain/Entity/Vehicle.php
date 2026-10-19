<?php

declare(strict_types=1);

namespace Oficina\Customer\Domain\Entity;

use Oficina\Shared\Domain\Exception\DomainException;
use Oficina\Shared\Domain\Exception\InvalidValueException;
use Oficina\Shared\Domain\ValueObject\LicensePlate;

/**
 * Raiz de Agregado: Veículo.
 * Identificado unicamente por sua placa, marca, modelo e ano.
 */
final class Vehicle
{
    private const int MIN_YEAR = 1900;

    private string $brand;

    private string $model;

    private ?string $color;

    private ?string $customerId;

    private function __construct(
        private ?string $id,
        private LicensePlate $licensePlate,
        string $brand,
        string $model,
        private int $year,
        ?string $customerId = null,
        ?string $color = null,
        private int $mileage = 0,
        private bool $active = true,
    ) {
        $this->brand = self::validateBrand($brand);
        $this->model = self::validateModel($model);
        self::validateYear($this->year);
        self::validateMileage($this->mileage);
        $this->color = self::normalizeOptionalString($color);
        $this->customerId = self::normalizeOptionalString($customerId);
    }

    public static function create(
        LicensePlate $licensePlate,
        string $brand,
        string $model,
        int $year,
        ?string $customerId = null,
        ?string $color = null,
        int $mileage = 0,
        bool $active = true,
        ?string $id = null,
    ): self {
        return new self(
            id: $id,
            licensePlate: $licensePlate,
            brand: $brand,
            model: $model,
            year: $year,
            customerId: $customerId,
            color: $color,
            mileage: $mileage,
            active: $active,
        );
    }

    public function update(
        string $brand,
        string $model,
        int $year,
        ?string $color = null,
        ?LicensePlate $licensePlate = null,
    ): void {
        $this->brand = self::validateBrand($brand);
        $this->model = self::validateModel($model);
        self::validateYear($year);

        $this->year = $year;
        $this->color = self::normalizeOptionalString($color);

        if ($licensePlate !== null) {
            $this->licensePlate = $licensePlate;
        }
    }

    public function registerMileage(int $mileage): void
    {
        if (! $this->active) {
            throw new DomainException('Não é possível registrar quilometragem em um veículo inativo.');
        }

        self::validateMileage($mileage);

        if ($mileage < $this->mileage) {
            throw new InvalidValueException('A nova quilometragem não pode ser menor que a quilometragem atual.');
        }

        $this->mileage = $mileage;
    }

    public function transferOwnership(string $newCustomerId): void
    {
        if (! $this->active) {
            throw new DomainException('Não é possível transferir um veículo inativo.');
        }

        $normalizedCustomerId = trim($newCustomerId);

        if ($normalizedCustomerId === '') {
            throw new InvalidValueException('O identificador do novo proprietário é obrigatório.');
        }

        if ($this->customerId === $normalizedCustomerId) {
            throw new DomainException('O veículo já pertence ao cliente informado.');
        }

        $this->customerId = $normalizedCustomerId;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    public function activate(): void
    {
        $this->active = true;
    }

    public function id(): ?string
    {
        return $this->id;
    }

    public function customerId(): ?string
    {
        return $this->customerId;
    }

    public function licensePlate(): LicensePlate
    {
        return $this->licensePlate;
    }

    public function brand(): string
    {
        return $this->brand;
    }

    public function model(): string
    {
        return $this->model;
    }

    public function year(): int
    {
        return $this->year;
    }

    public function color(): ?string
    {
        return $this->color;
    }

    public function mileage(): int
    {
        return $this->mileage;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    private static function validateBrand(string $brand): string
    {
        $trimmed = trim($brand);

        if ($trimmed === '') {
            throw new InvalidValueException('A marca do veículo é obrigatória.');
        }

        return $trimmed;
    }

    private static function validateModel(string $model): string
    {
        $trimmed = trim($model);

        if ($trimmed === '') {
            throw new InvalidValueException('O modelo do veículo é obrigatório.');
        }

        return $trimmed;
    }

    private static function validateYear(int $year): void
    {
        $maxYear = (int) date('Y') + 1;

        if ($year < self::MIN_YEAR || $year > $maxYear) {
            throw new InvalidValueException('O ano do veículo é inválido.');
        }
    }

    private static function validateMileage(int $mileage): void
    {
        if ($mileage < 0) {
            throw new InvalidValueException('A quilometragem do veículo não pode ser negativa.');
        }
    }

    private static function normalizeOptionalString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
