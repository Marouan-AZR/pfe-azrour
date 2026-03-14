<?php

namespace App\Enum;

enum UserRole: string
{
    case CHEF_STOCK = 'ROLE_CHEF_STOCK';
    case CONTROLEUR = 'ROLE_CONTROLEUR';
    case DIRECTEUR = 'ROLE_DIRECTEUR';
    case PATRON = 'ROLE_PATRON';
    case CLIENT = 'ROLE_CLIENT';

    public function label(): string
    {
        return match($this) {
            self::CHEF_STOCK => 'Chef de Stock',
            self::CONTROLEUR => 'Contrôleur',
            self::DIRECTEUR => 'Directeur',
            self::PATRON => 'Patron',
            self::CLIENT => 'Client',
        };
    }

    public static function getInternalRoles(): array
    {
        return [self::CHEF_STOCK, self::CONTROLEUR, self::DIRECTEUR, self::PATRON];
    }

    public function canValidateStock(): bool
    {
        return $this === self::CHEF_STOCK;
    }

    public function canCreateStockOperation(): bool
    {
        return in_array($this, [self::CHEF_STOCK, self::CONTROLEUR]);
    }

    public function canValidateInvoice(): bool
    {
        return $this === self::DIRECTEUR;
    }
}
