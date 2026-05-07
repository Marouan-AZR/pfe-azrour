<?php

namespace App\Enum;

enum OperationType: string
{
    case ENTRY = 'entry';
    case EXIT = 'exit';

    public function label(): string
    {
        return match($this) {
            self::ENTRY => 'Entrée',
            self::EXIT => 'Sortie',
        };
    }
}
