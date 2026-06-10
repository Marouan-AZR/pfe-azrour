<?php

namespace App\Enum;

enum StockStatus: string
{
    case EN_ATTENTE_CONTROLE = 'en_attente_controle';
    case EN_ATTENTE_VALIDATION = 'en_attente_validation';
    case PENDING = 'pending'; // kept for backward compatibility
    case VALIDATED = 'validated';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match($this) {
            self::EN_ATTENTE_CONTROLE => 'En attente de contrôle',
            self::EN_ATTENTE_VALIDATION => 'En attente de validation',
            self::PENDING => 'En attente',
            self::VALIDATED => 'Validé',
            self::REJECTED => 'Rejeté',
        };
    }

    public function cssClass(): string
    {
        return match($this) {
            self::EN_ATTENTE_CONTROLE => 'secondary',
            self::EN_ATTENTE_VALIDATION => 'warning',
            self::PENDING => 'warning',
            self::VALIDATED => 'success',
            self::REJECTED => 'danger',
        };
    }
}
