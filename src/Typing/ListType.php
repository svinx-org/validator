<?php

namespace Svinx\Validator\Typing;

/**
 * Type liste homogène, équivalent de `list[T]` en Pydantic.
 *
 *     Type::list(Type::of(EmailStr::class));
 *     ListType::make(Type::int());
 *
 * Une liste doit être indexée séquentiellement (0, 1, 2...) : un tableau
 * associatif est refusé, utilisez alors Map.
 */
class ListType extends Type
{
    public function __construct(Type $item, bool $coerce = false)
    {
        parent::__construct(kind: self::LIST, item: $item, coerce: $coerce);
    }

    public static function make(Type|string|TypeVar $item): self
    {
        return new self(Type::from($item));
    }

    public function item(): Type
    {
        return $this->item ?? Type::any();
    }
}
