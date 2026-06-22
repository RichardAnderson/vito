<?php

namespace App\ServerFeatures;

use Vito\Plugin\DTOs\DynamicForm;
use App\Models\Server;

abstract class Action implements ActionInterface
{
    public function __construct(public Server $server) {}

    public function form(): ?DynamicForm
    {
        return null;
    }
}
