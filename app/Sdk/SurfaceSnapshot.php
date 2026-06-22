<?php

namespace App\Sdk;

use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

/**
 * Builds a deterministic snapshot of the ENTIRE published Plugin SDK surface — the generated
 * `Contracts/*` (methods + `@property-read` data shape) plus the hand-authored Facades, Attributes,
 * Exceptions, the capability contracts, and PluginInterface. This is the source of truth the BC gate
 * (sdk:bc-check) diffs against to classify changes (added => minor, removed/retyped => major).
 */
final class SurfaceSnapshot
{
    private const NAMESPACE = 'Vito\\Plugin\\';

    public function srcPath(): string
    {
        return base_path('packages/plugin-sdk-php/src');
    }

    /**
     * @return array<string, array{kind: string, extends: list<string>, methods: array<string, string>, properties: array<string, string>}>
     */
    public function build(): array
    {
        $surface = [];

        foreach ($this->classes() as $fqcn) {
            $reflection = new ReflectionClass($fqcn);
            $surface[$fqcn] = [
                'kind' => $this->kind($reflection),
                'extends' => $this->parents($reflection),
                'methods' => $this->methods($reflection),
                'properties' => $this->propertyReads($reflection),
            ];
        }

        ksort($surface);

        return $surface;
    }

    public function toJson(): string
    {
        return json_encode($this->build(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
    }

    /**
     * @return list<class-string>
     */
    private function classes(): array
    {
        $base = $this->srcPath();
        $classes = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($base) + 1, -4);
            $fqcn = self::NAMESPACE.str_replace(DIRECTORY_SEPARATOR, '\\', $relative);

            if (class_exists($fqcn) || interface_exists($fqcn) || trait_exists($fqcn) || enum_exists($fqcn)) {
                $classes[] = $fqcn;
            }
        }

        sort($classes);

        return $classes;
    }

    /**
     * @param  ReflectionClass<object>  $reflection
     */
    private function kind(ReflectionClass $reflection): string
    {
        return match (true) {
            $reflection->isInterface() => 'interface',
            $reflection->isEnum() => 'enum',
            $reflection->isTrait() => 'trait',
            default => 'class',
        };
    }

    /**
     * @param  ReflectionClass<object>  $reflection
     * @return list<string>
     */
    private function parents(ReflectionClass $reflection): array
    {
        $names = array_keys($reflection->getInterfaces());
        if ($parent = $reflection->getParentClass()) {
            $names[] = $parent->getName();
        }
        sort($names);

        return $names;
    }

    /**
     * @param  ReflectionClass<object>  $reflection
     * @return array<string, string>
     */
    private function methods(ReflectionClass $reflection): array
    {
        $methods = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() && $method->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }
            $methods[$method->getName()] = $this->signature($method);
        }

        ksort($methods);

        return $methods;
    }

    private function signature(ReflectionMethod $method): string
    {
        $params = array_map(fn (ReflectionParameter $p): string => $this->param($p), $method->getParameters());

        return '('.implode(', ', $params).'): '.$this->typeString($method->getReturnType());
    }

    private function param(ReflectionParameter $parameter): string
    {
        $type = $this->typeString($parameter->getType());
        $prefix = $parameter->isVariadic() ? '...' : '';
        $suffix = $parameter->isOptional() ? '?' : '';

        return trim($type.' '.$prefix.'$'.$parameter->getName()).$suffix;
    }

    private function typeString(?ReflectionType $type): string
    {
        if ($type instanceof ReflectionNamedType) {
            return ($type->allowsNull() && $type->getName() !== 'null' && $type->getName() !== 'mixed' ? '?' : '').$type->getName();
        }

        if ($type instanceof ReflectionUnionType) {
            return implode('|', array_map(fn (ReflectionType $t): string => $this->typeString($t), $type->getTypes()));
        }

        if ($type instanceof ReflectionIntersectionType) {
            return implode('&', array_map(fn (ReflectionType $t): string => $this->typeString($t), $type->getTypes()));
        }

        return 'mixed';
    }

    /**
     * @param  ReflectionClass<object>  $reflection
     * @return array<string, string>
     */
    private function propertyReads(ReflectionClass $reflection): array
    {
        $doc = $reflection->getDocComment();
        if ($doc === false) {
            return [];
        }

        preg_match_all('/@property-read\s+(.+?)\s+\$(\w+)/', $doc, $matches, PREG_SET_ORDER);

        $properties = [];
        foreach ($matches as $match) {
            $properties[$match[2]] = trim($match[1]);
        }

        ksort($properties);

        return $properties;
    }
}
