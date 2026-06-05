<?php

namespace App\Enums;

use App\Contracts\VitoEnum;
use Forjed\InertiaTable\Contracts\HasTableDisplay;

enum MemberStatus: string implements HasTableDisplay, VitoEnum
{
    case JOINING = 'joining';
    case ACTIVE = 'active';
    case LEAVING = 'leaving';
    case FAILED = 'failed';

    public function getColor(): string
    {
        return match ($this) {
            self::JOINING, self::LEAVING => 'info',
            self::ACTIVE => 'success',
            self::FAILED => 'danger',
        };
    }

    public function getText(): string
    {
        return $this->value;
    }
}
