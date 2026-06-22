<?php

namespace App\Contracts;

use App\Models\Service;

/**
 * Core binding-seam for service lifecycle control. Concrete: App\Actions\Service\Manage (app).
 */
interface ServiceManager
{
    public function start(Service $service): void;

    public function stop(Service $service): void;

    public function restart(Service $service): void;

    public function reload(Service $service): void;

    public function enable(Service $service): void;

    public function disable(Service $service): void;
}
