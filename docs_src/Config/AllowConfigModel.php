<?php

namespace Svinx\Validator\Examples\Config;

use Svinx\Validator\BaseModel;
use Svinx\Validator\Config;

class AllowConfigModel extends BaseModel
{
    public static function model_config(): Config
    {
        return new Config(extra: Config::EXTRA_ALLOW);
    }

    public string $name = '';
}
