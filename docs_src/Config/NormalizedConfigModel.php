<?php

namespace Svinx\Validator\Examples\Config;

use Svinx\Validator\BaseModel;
use Svinx\Validator\Config;

class NormalizedConfigModel extends BaseModel
{
    public static function model_config(): Config
    {
        return new Config(str_strip_whitespace: true, str_to_upper: true);
    }

    public string $code = '';
    public int $number = 0;
}
