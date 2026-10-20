<?php

namespace Svinx\Validator\Typing;

/**
 * Type dictionnaire, équivalent de `dict[K, V]` en Pydantic.
 *
 *     Type::map(Type::str(), Type::int());   // map[string, int]
 *     Map::make(Type::str(), Type::of(UUID::class));
 *
 * Les clés d'un tableau PHP ne peuvent être que des entiers ou des chaînes :
 * les clés numériques saisies en chaîne sont rétablies selon le type de clé.
 */
class Map extends Type
{
    public function __construct(Type $key, Type $value, bool $coerce = false)
    {
        parent::__construct(kind: self::MAP, key: $key, value: $value, coerce: $coerce);
    }

    public static function make(Type|string|TypeVar $key, Type|string|TypeVar $value): self
    {
        return new self(Type::from($key), Type::from($value));
    }

    public function keyType(): Type
    {
        return $this->key ?? Type::any();
    }

    public function valueType(): Type
    {
        return $this->value ?? Type::any();
    }
}
