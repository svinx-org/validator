<?php

namespace Svinx\Validator;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Field {
    public function __construct(
        public readonly ?int $min_length = null,
        public readonly ?int $max_length = null,
        public readonly ?int $gt = null,
        public readonly ?int $lt = null,
        public readonly ?int $min = null,
        public readonly ?int $max = null,
        public readonly ?string $regex = null,
        public readonly ?string $alias = null,
        public readonly ?string $email = null,
        public readonly ?array $oneOf = null
    ) {}
}