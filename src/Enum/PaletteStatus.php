<?php

namespace App\Enum;

enum PaletteStatus: string
{
    case EN_ATTENTE = 'en_attente';
    case VALIDEE = 'validee';
    case REJETEE = 'rejetee';
    case SORTIE_PARTIELLE = 'sortie_partielle';
    case SORTIE_COMPLETE = 'sortie_complete';

    public function label(): string
    {
        return match($this) {
            self::EN_ATTENTE => 'En attente',
            self::VALIDEE => 'Validée',
            self::REJETEE => 'Rejetée',
            self::SORTIE_PARTIELLE => 'Sortie partielle',
            self::SORTIE_COMPLETE => 'Sortie complète',
        };
    }

    public function cssClass(): string
    {
        return match($this) {
            self::EN_ATTENTE => 'warning',
            self::VALIDEE => 'success',
            self::REJETEE => 'danger',
            self::SORTIE_PARTIELLE => 'info',
            self::SORTIE_COMPLETE => 'secondary',
        };
    }
}
