<?php

namespace App\Enums;

use App\Contracts\VitoEnum;
use Forjed\InertiaTable\Contracts\HasTableDisplay;

enum RedirectStatus: string implements HasTableDisplay, VitoEnum
{
    case CREATING = 'creating';
    case READY = 'ready';
    case DELETING = 'deleting';
    case FAILED = 'failed';

    public function getColor(): string
    {
        return match ($this) {
            self::CREATING,
            self::DELETING => 'warning',
            self::READY => 'success',
            self::FAILED => 'danger',
        };
    }

    public function getText(): string
    {
        return $this->value;
    }
}
