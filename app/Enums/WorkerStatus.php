<?php

namespace App\Enums;

use App\Contracts\VitoEnum;
use Forjed\InertiaTable\Contracts\HasTableDisplay;

enum WorkerStatus: string implements HasTableDisplay, VitoEnum
{
    case RUNNING = 'running';
    case CREATING = 'creating';
    case DELETING = 'deleting';
    case FAILED = 'failed';
    case STARTING = 'starting';
    case STOPPING = 'stopping';
    case RESTARTING = 'restarting';
    case STOPPED = 'stopped';

    public function getColor(): string
    {
        return match ($this) {
            self::RUNNING => 'success',
            self::CREATING,
            self::DELETING,
            self::STARTING,
            self::STOPPING,
            self::RESTARTING => 'warning',
            self::FAILED => 'danger',
            self::STOPPED => 'gray',
        };
    }

    public function getText(): string
    {
        return $this->value;
    }
}
