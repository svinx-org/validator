<?php

namespace Svinx\Validator;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ModelValidator
{
    public function __construct(
        public ?string $key = null,
    ) {}
}