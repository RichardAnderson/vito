<?php

namespace App\Enums;

use App\Contracts\VitoEnum;
use Forjed\InertiaTable\Contracts\HasTableDisplay;

enum PrivateNetworkStatus: string implements HasTableDisplay, VitoEnum
{
    case CREATING = 'creating';
    case READY = 'ready';
    case UPDATING = 'updating';
    case FAILED = 'failed';

    public function getColor(): string
    {
        return match ($this) {
            self::CREATING, self::UPDATING => 'info',
            self::READY => 'success',
            self::FAILED => 'danger',
        };
    }

    public function getText(): string
    {
        return $this->value;
    }
}
