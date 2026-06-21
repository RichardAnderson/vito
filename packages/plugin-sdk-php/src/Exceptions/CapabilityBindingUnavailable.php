<?php

namespace Vito\Plugin\Exceptions;

use RuntimeException;

final class CapabilityBindingUnavailable extends RuntimeException
{
    public static function for(string $capability): self
    {
        return new self("The \"{$capability}\" capability binding is not yet available in this version of Vito.");
    }
}
