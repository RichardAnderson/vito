<?php

namespace App\ValidationRules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * DomainRule with a single optional leading `*.` label. It is a separate rule rather than a
 * flag on DomainRule because DomainRule's only call sites are Site creation, where a wildcard
 * would compose an on-disk path and a vhost filename and let a glob reach an unescaped rm -rf.
 */
class WildcardDomainRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value) {
            return;
        }

        $host = (string) $value;

        if (str_starts_with($host, '*.')) {
            $host = substr($host, 2);
        }

        if (str_contains($host, '*')) {
            $fail('Domain is not valid')->translate();

            return;
        }

        (new DomainRule)->validate($attribute, $host, $fail);
    }
}
