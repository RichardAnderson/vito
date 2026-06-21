<?php

namespace Vito\Plugin\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class ExposesMethods
{
    /**
     * Behaviour the contract commits to: a map of model method name => PHP return type.
     * These become real method declarations on the generated interface, so the model
     * structurally satisfies them via `implements` (PHP-enforced).
     *
     * @param  array<string, string>  $methods
     */
    public function __construct(public array $methods = []) {}
}
