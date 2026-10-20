<?php

namespace Svinx\Validator\Examples\Settings;

use Svinx\Validator\Settings\BaseSettings;

class DotEnvSettings extends BaseSettings
{
    public static function env_prefix(): string
    {
        return 'DOTENV_';
    }

    public static function env_file(): ?string
    {
        return getenv('SVINX_ENV_FILE') ?: null;
    }

    public string $value = 'default';

    public int $count = 0;

    public string $quoted = '';
}
