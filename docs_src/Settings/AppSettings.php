<?php

namespace Svinx\Validator\Examples\Settings;

use Svinx\Validator\Field;
use Svinx\Validator\Settings\BaseSettings;
use Svinx\Validator\Validators\EmailStr;
use Svinx\Validator\Validators\SecretStr;

class AppSettings extends BaseSettings
{
    public static function env_prefix(): string
    {
        return 'APP_';
    }

    public static function env_nested_delimiter(): ?string
    {
        return '__';
    }

    public string $env = 'dev';

    public bool $debug = false;

    public int $port = 8080;

    public float $ratio = 1.0;

    public ?EmailStr $admin_email = null;

    public ?SecretStr $api_key = null;

    public ?NestedDbSettings $db = null;

    #[Field(alias: 'jwt_secret')]
    public ?string $token = null;
}
