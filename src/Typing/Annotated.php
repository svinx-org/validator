<?php

namespace Svinx\Validator\Typing;

use Svinx\Validator\FieldInfo;

/**
 * Type annoté, équivalent de `Annotated[T, ...]` en Pydantic.
 *
 *     Annotated::of(Type::str(), new Field(min_length: 2, max_length: 50));
 *
 * Les FieldInfo passés en métadonnées deviennent des contraintes appliquées
 * pendant la validation ; les autres métadonnées sont conservées telles quelles
 * (description, tags, informations métier...).
 */
final class Annotated
{
    /**
     * @param FieldInfo|mixed ...$metadata
     */
    public static function of(Type|string|TypeVar $type, mixed ...$metadata): Type
    {
        $resolved = Type::from($type);

        $constraints = array_values(array_filter($metadata, static fn (mixed $item) => $item instanceof FieldInfo));
        $others = array_values(array_filter($metadata, static fn (mixed $item) => !$item instanceof FieldInfo));

        if ($constraints !== []) {
            $resolved = $resolved->withConstraints(...$constraints);
        }

        if ($others !== []) {
            $resolved = $resolved->withMetadata(...$others);
        }

        return $resolved;
    }
}
