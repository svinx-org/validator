<?php

namespace Svinx\Validator\Tests;

use PHPUnit\Framework\TestCase;
use Svinx\Validator\Settings\BaseSettings;
use Svinx\Validator\ValidationError;
use Svinx\Validator\Validators\SecretStr;
use Svinx\Validator\Examples\Settings\AppSettings;
use Svinx\Validator\Examples\Settings\CaseSensitiveSettings;
use Svinx\Validator\Examples\Settings\DotEnvSettings;
use Svinx\Validator\Examples\Settings\NestedDbSettings;
use Svinx\Validator\Examples\Settings\RequiredSettings;

class SettingsTest extends TestCase
{
    private static string $envFile = '';

    public static function envFilePath(): string
    {
        if (self::$envFile === '') {
            self::$envFile = sys_get_temp_dir() . '/svinx-settings-' . getmypid() . '.env';
        }

        return self::$envFile;
    }

    protected function tearDown(): void
    {
        @unlink(self::envFilePath());
        putenv('SVINX_ENV_FILE');
    }

    private function writeEnvFile(string $content): void
    {
        file_put_contents(self::envFilePath(), $content);
        putenv('SVINX_ENV_FILE=' . self::envFilePath());
    }

    // --------------------------------------------------------------- Lecture

    public function testReadsAndCoercesEnvironmentValues(): void
    {
        $settings = AppSettings::load([
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'true',
            'APP_PORT' => '9000',
            'APP_RATIO' => '0.5',
            'APP_ADMIN_EMAIL' => 'admin@example.com',
        ]);

        $this->assertSame('production', $settings->env);
        $this->assertTrue($settings->debug);
        $this->assertSame(9000, $settings->port);
        $this->assertSame(0.5, $settings->ratio);
        $this->assertSame('admin@example.com', $settings->admin_email?->value());
    }

    public function testDeveloperEnvironmentWithoutPrefixIsIgnored(): void
    {
        $settings = AppSettings::load(['ENV' => 'staging', 'PORT' => '1234']);

        $this->assertSame('dev', $settings->env);
        $this->assertSame(8080, $settings->port);
    }

    public function testDefaultsWhenEnvironmentIsEmpty(): void
    {
        $settings = AppSettings::load([]);

        $this->assertSame('dev', $settings->env);
        $this->assertFalse($settings->debug);
        $this->assertSame(8080, $settings->port);
        $this->assertNull($settings->db);
        $this->assertNull($settings->api_key);
    }

    public function testExplicitDataOverridesEnvironment(): void
    {
        $settings = new AppSettings(['port' => 1234, 'env' => 'local'], ['APP_PORT' => '9000', 'APP_ENV' => 'prod']);

        $this->assertSame(1234, $settings->port);
        $this->assertSame('local', $settings->env);
    }

    public function testAliasDrivesEnvironmentName(): void
    {
        $settings = AppSettings::load(['APP_JWT_SECRET' => 'top-secret']);

        $this->assertSame('top-secret', $settings->token);
    }

    public function testCaseInsensitiveByDefault(): void
    {
        $settings = AppSettings::load(['app_env' => 'min' ]);

        $this->assertSame('min', $settings->env);
    }

    public function testCaseSensitiveSettings(): void
    {
        $settings = CaseSensitiveSettings::load(['ServiceName' => 'custom', 'region' => 'value']);

        $this->assertSame('custom', $settings->ServiceName);
        $this->assertSame('value', $settings->region);

        $lowercased = CaseSensitiveSettings::load(['servicename' => 'ignored']);

        $this->assertSame('default', $lowercased->ServiceName);
    }

    public function testInvalidEnvironmentValueThrows(): void
    {
        $this->expectException(ValidationError::class);

        AppSettings::load(['APP_PORT' => 'pas un entier']);
    }

    public function testMissingRequiredSettingThrows(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage("Le champ 'database_url' est requis");

        RequiredSettings::load([]);
    }

    // -------------------------------------------------------------- Imbriqué

    public function testNestedSettingsFromEnvironment(): void
    {
        $settings = AppSettings::load([
            'APP_DB__HOST' => 'db.internal',
            'APP_DB__PORT' => '6432',
            'APP_DB__SSL' => 'yes',
        ]);

        $this->assertInstanceOf(NestedDbSettings::class, $settings->db);
        $this->assertSame('db.internal', $settings->db->host);
        $this->assertSame(6432, $settings->db->port);
        $this->assertTrue($settings->db->ssl);
    }

    public function testNestedSettingsKeepDefaults(): void
    {
        $settings = AppSettings::load(['APP_DB__HOST' => 'db.internal']);

        $this->assertSame('db.internal', $settings->db?->host);
        $this->assertSame(5432, $settings->db?->port);
    }

    public function testExplicitNestedDataWins(): void
    {
        $settings = new AppSettings(
            ['db' => ['host' => 'explicit', 'port' => 1111]],
            ['APP_DB__HOST' => 'env-host', 'APP_DB__PORT' => '2222'],
        );

        $this->assertSame('explicit', $settings->db?->host);
        $this->assertSame(1111, $settings->db?->port);
    }

    // -------------------------------------------------------------- Secrets

    public function testSecretIsMaskedInDump(): void
    {
        $settings = AppSettings::load(['APP_API_KEY' => 'super-secret']);

        $this->assertInstanceOf(SecretStr::class, $settings->api_key);
        $this->assertSame('super-secret', $settings->api_key?->getSecretValue());
        $this->assertSame('**********', $settings->model_dump()['api_key']);
        $this->assertStringNotContainsString('super-secret', $settings->model_dump_json());
        $this->assertSame('**********', (string) $settings->api_key);
    }

    // -------------------------------------------------------------- Fichier .env

    public function testReadsDotEnvFile(): void
    {
        $this->writeEnvFile(<<<'ENV'
            # un commentaire
            DOTENV_VALUE=from-file
            export DOTENV_COUNT=7
            DOTENV_QUOTED="valeur avec espaces"

            ENV);

        $settings = new DotEnvSettings();

        $this->assertSame('from-file', $settings->value);
        $this->assertSame(7, $settings->count);
        $this->assertSame('valeur avec espaces', $settings->quoted);
    }

    public function testExplicitDataOverridesDotEnvFile(): void
    {
        $this->writeEnvFile("DOTENV_VALUE=from-file\n");

        $settings = new DotEnvSettings(['value' => 'explicit']);

        $this->assertSame('explicit', $settings->value);
    }

    public function testParseEnvFileHandlesCommentsAndQuotes(): void
    {
        $this->writeEnvFile(<<<'ENV'
            # commentaire
            SIMPLE=1
            SINGLE='a b'
            DOUBLE="c\d"
            INVALID_LINE
            =valeur-sans-cle
            ENV);

        $parsed = BaseSettings::parseEnvFile(self::envFilePath());

        $this->assertSame('1', $parsed['SIMPLE']);
        $this->assertSame('a b', $parsed['SINGLE']);
        $this->assertSame('cd', $parsed['DOUBLE']);
        $this->assertArrayNotHasKey('INVALID_LINE', $parsed);
        $this->assertArrayNotHasKey('', $parsed);
    }

    public function testParseEnvFileIgnoresMissingFile(): void
    {
        $this->assertSame([], BaseSettings::parseEnvFile('/chemin/inexistant/' . uniqid() . '.env'));
    }

    public function testReadEnvironmentIncludesExplicitEnvironment(): void
    {
        $environment = BaseSettings::readEnvironment(null);

        $this->assertIsArray($environment);
    }
}
