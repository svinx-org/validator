<?php

namespace Svinx\Validator;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Field extends FieldInfo
{
    public function __construct(
        public ?string $alias = null,
        public ?string $title = null,
        public ?string $description = null,
        public float|int|null $gt = null,
        public float|int|null $ge = null,
        public float|int|null $lt = null,
        public float|int|null $le = null,
        public float|int|null $multiple_of = null,
        public ?int $min_length = null,
        public ?int $max_length = null,
        public ?string $pattern = null,
        public ?array $choices = null,
        public mixed $default = null
    ) {}
}