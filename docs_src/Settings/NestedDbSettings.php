<?php

namespace Svinx\Validator\Examples\Settings;

use Svinx\Validator\Settings\BaseSettings;

class NestedDbSettings extends BaseSettings
{
    public static function env_prefix(): string
    {
        return 'SXDB_';
    }

    public string $host = 'localhost';

    public int $port = 5432;

    public bool $ssl = false;
}
