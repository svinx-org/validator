<?php

namespace Svinx\Validator\Examples\Config;

use Svinx\Validator\BaseModel;
use Svinx\Validator\Config;

class ForbidConfigModel extends BaseModel
{
    public static function model_config(): Config
    {
        return new Config(extra: Config::EXTRA_FORBID);
    }

    public string $name = '';
}
