<?php

namespace App\Sdk;

final class SdkPaths
{
    public static function contractsDir(): string
    {
        return base_path('packages/plugin-sdk-php/src/Contracts');
    }

    public static function contractFile(string $contract): string
    {
        return self::contractsDir().'/'.$contract.'.php';
    }

    public static function typeScriptFile(): string
    {
        return base_path('packages/plugin-sdk-js/src/types/generated.ts');
    }

    public static function snapshotFile(): string
    {
        return base_path('packages/plugin-sdk-php/contract-snapshot.json');
    }
}
