<?php

namespace App\SiteFeatures;

use Vito\Plugin\DTOs\DynamicForm;
use Illuminate\Http\Request;

interface FeatureInterface
{
    public function name(): string;

    public function active(): bool;

    public function form(): ?DynamicForm;

    public function handle(Request $request): void;
}
