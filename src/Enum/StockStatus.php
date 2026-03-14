<?php

namespace App\Enum;

enum StockStatus: string
{
    case PENDING = 'pending';
    case VALIDATED = 'validated';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'En attente',
            self::VALIDATED => 'Validé',
            self::REJECTED => 'Rejeté',
        };
    }

    public function cssClass(): string
    {
        return match($this) {
            self::PENDING => 'warning',
            self::VALIDATED => 'success',
            self::REJECTED => 'danger',
        };
    }
}
