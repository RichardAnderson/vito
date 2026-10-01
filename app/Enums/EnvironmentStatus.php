<?php

namespace App\Enums;

use App\Contracts\VitoEnum;

enum EnvironmentStatus: string implements VitoEnum
{
    case READY = 'ready';
    case DEPLOYING = 'deploying';
    case FAILED = 'failed';
    case DELETING = 'deleting';

    public function getColor(): string
    {
        return match ($this) {
            self::READY => 'success',
            self::DEPLOYING => 'warning',
            self::FAILED,
            self::DELETING => 'danger',
        };
    }

    public function getText(): string
    {
        return $this->value;
    }
}
