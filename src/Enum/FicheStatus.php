<?php

namespace App\Enum;

enum FicheStatus: string
{
    case EN_COURS_CONTROLE = 'en_cours_controle';
    case EN_ATTENTE_VALIDATION = 'en_attente_validation';
    case VALIDEE = 'validee';
    case REJETEE = 'rejetee';

    public function label(): string
    {
        return match($this) {
            self::EN_COURS_CONTROLE => 'En cours de contrôle',
            self::EN_ATTENTE_VALIDATION => 'En attente de validation',
            self::VALIDEE => 'Validée',
            self::REJETEE => 'Rejetée',
        };
    }

    public function cssClass(): string
    {
        return match($this) {
            self::EN_COURS_CONTROLE => 'info',
            self::EN_ATTENTE_VALIDATION => 'warning',
            self::VALIDEE => 'success',
            self::REJETEE => 'danger',
        };
    }
}
