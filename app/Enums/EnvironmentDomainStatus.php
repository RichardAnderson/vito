<?php

namespace App\Enums;

use App\Contracts\VitoEnum;
use Forjed\InertiaTable\Contracts\HasTableDisplay;

enum EnvironmentDomainStatus: string implements HasTableDisplay, VitoEnum
{
    case CREATING = 'creating';
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case FAILED = 'failed';
    case DELETING = 'deleting';

    public function getColor(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::CREATING => 'info',
            self::PENDING => 'warning',
            self::INACTIVE => 'gray',
            self::FAILED,
            self::DELETING => 'danger',
        };
    }

    public function getText(): string
    {
        return $this->value;
    }
}
