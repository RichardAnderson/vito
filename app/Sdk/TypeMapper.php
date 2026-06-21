<?php

namespace App\Sdk;

use App\Sdk\Descriptors\PropertyDescriptor;
use DateTimeInterface;
use ReflectionEnum;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;
use Spatie\LaravelData\Data;

final class TypeMapper
{
    /**
     * @param  array<class-string, string>  $dataClassToContract
     */
    public function __construct(private readonly array $dataClassToContract) {}

    public function map(ReflectionParameter $param, ?string $collectionOf = null): PropertyDescriptor
    {
        $type = $param->getType();

        if (! $type instanceof ReflectionNamedType) {
            throw new RuntimeException("Projection property \"{$param->getName()}\" must have a single named type.");
        }

        $nullable = $type->allowsNull();

        [$php, $ts] = $collectionOf !== null
            ? $this->collection($collectionOf, $param->getName())
            : $this->resolve($type->getName(), $param->getName());

        if ($nullable) {
            $php .= '|null';
            $ts .= ' | null';
        }

        return new PropertyDescriptor($param->getName(), $php, $ts);
    }

    /**
     * @return array{string, string}
     */
    private function collection(string $dataClass, string $property): array
    {
        $contract = $this->dataClassToContract[$dataClass] ?? null;

        if ($contract === null) {
            throw new RuntimeException("Collection element \"{$dataClass}\" on property \"{$property}\" has no #[HostContract].");
        }

        return ['array<int, \\Vito\\Plugin\\Contracts\\'.$contract.'>', $contract.'[]'];
    }

    /**
     * @return array{string, string} [phpDocType, tsType]
     */
    private function resolve(string $name, string $property): array
    {
        return match (true) {
            $name === 'int', $name === 'float' => [$name, 'number'],
            $name === 'string' => ['string', 'string'],
            $name === 'bool' => ['bool', 'boolean'],
            $name === 'array' => ['array<string, mixed>', 'Record<string, unknown>'],
            enum_exists($name) => ['\\BackedEnum&\\Vito\\Plugin\\Contracts\\VitoEnum', $this->enumTs($name)],
            is_subclass_of($name, Data::class) => $this->nested($name, $property),
            is_a($name, DateTimeInterface::class, true) => [$this->fqcn($name), 'string'],
            default => throw new RuntimeException("Unsupported projection type \"{$name}\" on property \"{$property}\"."),
        };
    }

    /**
     * @return array{string, string}
     */
    private function nested(string $dataClass, string $property): array
    {
        $contract = $this->dataClassToContract[$dataClass] ?? null;

        if ($contract === null) {
            throw new RuntimeException("Nested projection \"{$dataClass}\" on property \"{$property}\" has no #[HostContract].");
        }

        return ['\\Vito\\Plugin\\Contracts\\'.$contract, $contract];
    }

    private function enumTs(string $enum): string
    {
        $reflection = new ReflectionEnum($enum);
        $backingIsString = (string) $reflection->getBackingType() === 'string';

        $cases = array_map(
            fn (\UnitEnum $case): string => $backingIsString
                ? "'".$case->value."'" // @phpstan-ignore property.notFound
                : (string) $case->value, // @phpstan-ignore property.notFound
            $enum::cases(),
        );

        return implode(' | ', $cases);
    }

    private function fqcn(string $name): string
    {
        return '\\'.ltrim($name, '\\');
    }
}
