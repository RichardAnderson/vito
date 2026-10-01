<?php

namespace App\Enums;

use App\Contracts\VitoEnum;
use Forjed\InertiaTable\Contracts\HasTableDisplay;

enum ComponentStatus: string implements HasTableDisplay, VitoEnum
{
    case CREATING = 'creating';
    case INSTALLING = 'installing';
    case INSTALLATION_FAILED = 'installation_failed';
    case READY = 'ready';
    case DEPLOYING = 'deploying';
    case FAILED = 'failed';
    case DELETING = 'deleting';

    public function getColor(): string
    {
        return match ($this) {
            self::READY => 'success',
            self::CREATING => 'info',
            self::INSTALLING,
            self::DEPLOYING => 'warning',
            self::INSTALLATION_FAILED,
            self::FAILED,
            self::DELETING => 'danger',
        };
    }

    public function getText(): string
    {
        return $this->value;
    }
}
