<?php

namespace App\Enum;

enum TypeStockage: string
{
    case FORFAIT = 'forfait';
    case SEJOUR  = 'sejour';

    public function label(): string
    {
        return match($this) {
            self::FORFAIT => 'Forfait',
            self::SEJOUR  => 'Séjour',
        };
    }

    public function cssClass(): string
    {
        return match($this) {
            self::FORFAIT => 'primary',
            self::SEJOUR  => 'info',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::FORFAIT => 'bi-tag-fill',
            self::SEJOUR  => 'bi-calendar-week',
        };
    }
}