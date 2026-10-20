<?php

namespace Svinx\Validator\Examples\Config;

use Svinx\Validator\BaseModel;
use Svinx\Validator\Config;

class AliasGeneratorModel extends BaseModel
{
    public static function model_config(): Config
    {
        return new Config(alias_generator: static fn (string $property) => 'X_' . strtoupper($property));
    }

    public string $first_name = '';
}
