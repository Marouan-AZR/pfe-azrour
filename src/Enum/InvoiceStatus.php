<?php

namespace App\Enum;

enum InvoiceStatus: string
{
    case DRAFT = 'draft';
    case PENDING_VALIDATION = 'pending_validation';
    case VALIDATED = 'validated';
    case SENT = 'sent';

    public function label(): string
    {
        return match($this) {
            self::DRAFT => 'Brouillon',
            self::PENDING_VALIDATION => 'En attente de validation',
            self::VALIDATED => 'Validée',
            self::SENT => 'Envoyée',
        };
    }

    public function cssClass(): string
    {
        return match($this) {
            self::DRAFT => 'secondary',
            self::PENDING_VALIDATION => 'warning',
            self::VALIDATED => 'success',
            self::SENT => 'info',
        };
    }

    public function isEditable(): bool
    {
        return $this === self::DRAFT;
    }
}
