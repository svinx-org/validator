<?php

namespace Svinx\Validator\Examples\Settings;

use Svinx\Validator\Settings\BaseSettings;

class CaseSensitiveSettings extends BaseSettings
{
    public static function case_sensitive(): bool
    {
        return true;
    }

    public string $ServiceName = 'default';

    public string $region = 'default';
}
