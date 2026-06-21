<?php

namespace Vito\Plugin\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class HostContract
{
    /**
     * @param  class-string  $model  The core model this projection describes and whose generated contract it backs.
     */
    public function __construct(public string $model) {}
}
