<?php

namespace Svinx\Validator\Typing;

/**
 * Variable de type, équivalent de `TypeVar("T")` en Pydantic.
 *
 * PHP n'ayant pas de génériques, une TypeVar sert à décrire un conteneur
 * dont le type des éléments n'est connu qu'à la construction d'un
 * TypeAdapter :
 *
 *     $t = TypeVar::of('T');
 *     $schema = Type::list($t);
 *     TypeAdapter::of($schema, ['T' => Type::int()])->validate([1, 2, 3]);
 *
 * Une TypeVar peut être bornée (bound) et/ou avoir un type par défaut.
 */
class TypeVar
{
    public function __construct(
        public readonly string $name,
        public readonly ?Type $bound = null,
        public readonly ?Type $default = null,
    ) {}

    public static function of(string $name, Type|string|null $bound = null, Type|string|null $default = null): self
    {
        return new self(
            $name,
            $bound !== null ? Type::from($bound) : null,
            $default !== null ? Type::from($default) : null,
        );
    }

    public function describe(): string
    {
        return '$' . $this->name;
    }
}
