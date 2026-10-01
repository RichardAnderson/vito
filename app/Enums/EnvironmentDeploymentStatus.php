<?php

namespace App\Enums;

use App\Contracts\VitoEnum;
use Forjed\InertiaTable\Contracts\HasTableDisplay;

enum EnvironmentDeploymentStatus: string implements HasTableDisplay, VitoEnum
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case FINISHED = 'finished';
    case PARTIAL = 'partial';
    case FAILED = 'failed';

    public function getColor(): string
    {
        return match ($this) {
            self::FINISHED => 'success',
            self::PENDING => 'info',
            self::RUNNING,
            self::PARTIAL => 'warning',
            self::FAILED => 'danger',
        };
    }

    public function getText(): string
    {
        return $this->value;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::FINISHED, self::PARTIAL, self::FAILED], true);
    }
}
