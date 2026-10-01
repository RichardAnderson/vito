<?php

namespace App\Enums;

use App\Contracts\VitoEnum;

enum ApplicationStatus: string implements VitoEnum
{
    case READY = 'ready';
    case DELETING = 'deleting';

    public function getColor(): string
    {
        return match ($this) {
            self::READY => 'success',
            self::DELETING => 'danger',
        };
    }

    public function getText(): string
    {
        return $this->value;
    }
}
