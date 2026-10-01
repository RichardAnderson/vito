<?php

namespace App\Enums;

use App\Contracts\VitoEnum;
use Forjed\InertiaTable\Contracts\HasTableDisplay;

enum ComponentType: string implements HasTableDisplay, VitoEnum
{
    case APP = 'app';
    case LOAD_BALANCER = 'load_balancer';
    case PROXY = 'proxy';
    case CONTAINER = 'container';

    public function getColor(): string
    {
        return match ($this) {
            self::APP => 'info',
            self::LOAD_BALANCER => 'purple',
            self::PROXY => 'blue',
            self::CONTAINER => 'cyan',
        };
    }

    public function getText(): string
    {
        return $this->value;
    }

    public function terminatesHttp(): bool
    {
        return in_array($this, [self::LOAD_BALANCER, self::PROXY], true);
    }
}
