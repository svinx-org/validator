<?php

namespace Svinx\Validator\Examples\Config;

use Svinx\Validator\BaseModel;
use Svinx\Validator\Config;
use Svinx\Validator\Field;

class NoPopulateByNameModel extends BaseModel
{
    public static function model_config(): Config
    {
        return new Config(populate_by_name: false);
    }

    #[Field(alias: 'n')]
    public string $name;
}
