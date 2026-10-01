<?php

namespace App\Enums;

use App\Contracts\VitoEnum;

enum DeploymentStage: string implements VitoEnum
{
    case CLONE = 'clone';
    case BUILD = 'build';
    case PRE_FLIGHT = 'pre-flight';
    case RELEASE = 'release';

    public function getColor(): string
    {
        return match ($this) {
            self::CLONE => 'info',
            self::BUILD,
            self::PRE_FLIGHT => 'warning',
            self::RELEASE => 'success',
        };
    }

    public function getText(): string
    {
        return $this->value;
    }

    public function next(): ?self
    {
        return match ($this) {
            self::CLONE => self::BUILD,
            self::BUILD => self::PRE_FLIGHT,
            self::PRE_FLIGHT => self::RELEASE,
            self::RELEASE => null,
        };
    }
}
