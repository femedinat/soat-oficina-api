<?php

declare(strict_types=1);

namespace Oficina\Identity\Domain;

/** Perfil de acesso do usuário do sistema. */
enum Role: string
{
    case ADMINISTRATOR = 'administrator';
    case MECHANIC = 'mechanic';
    case CUSTOMER = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::ADMINISTRATOR => 'Administrador',
            self::MECHANIC => 'Mecânico',
            self::CUSTOMER => 'Cliente',
        };
    }

    /** Somente usuários com perfil Cliente são vinculados a um cadastro de Cliente. */
    public function requiresCustomer(): bool
    {
        return $this === self::CUSTOMER;
    }
}
