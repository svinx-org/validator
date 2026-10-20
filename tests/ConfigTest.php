<?php

namespace Svinx\Validator\Tests;

use PHPUnit\Framework\TestCase;
use Svinx\Validator\Config;
use Svinx\Validator\ValidationError;
use Svinx\Validator\Examples\Config\AliasGeneratorModel;
use Svinx\Validator\Examples\Config\AllowConfigModel;
use Svinx\Validator\Examples\Config\ForbidConfigModel;
use Svinx\Validator\Examples\Config\NoPopulateByNameModel;
use Svinx\Validator\Examples\Config\NormalizedConfigModel;
use Svinx\Validator\Examples\Config\RequiredSchemaModel;

class ConfigTest extends TestCase
{
    public function testExtraIgnoreDropsUnknownKeys(): void
    {
        $model = new NormalizedConfigModel(['code' => 'a', 'inconnu' => 'x']);

        $this->assertSame('A', $model->code);
        $this->assertSame([], $model->extra());
    }

    public function testExtraForbidThrows(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Champs inconnus : inconnu');

        new ForbidConfigModel(['name' => 'ok', 'inconnu' => 'x']);
    }

    public function testExtraAllowKeepsUnknownKeys(): void
    {
        $model = new AllowConfigModel(['name' => 'ok', 'surplus' => 42]);

        $this->assertSame(['surplus' => 42], $model->extra());
        $this->assertSame(['name' => 'ok', 'surplus' => 42], $model->model_dump());
    }

    public function testAliasGeneratorBuildsAliases(): void
    {
        $model = new AliasGeneratorModel(['X_FIRST_NAME' => 'Alice']);

        $this->assertSame('Alice', $model->first_name);
        $this->assertSame(['X_FIRST_NAME' => 'Alice'], $model->model_dump());
    }

    public function testPopulateByNameDisabledRequiresAlias(): void
    {
        $this->assertSame('ok', (new NoPopulateByNameModel(['n' => 'ok']))->name);

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('est requis');

        new NoPopulateByNameModel(['name' => 'ok']);
    }

    public function testStringNormalization(): void
    {
        $model = new NormalizedConfigModel(['code' => '  abc  ']);

        $this->assertSame('ABC', $model->code);
    }

    public function testNormalizationDoesNotTouchOtherTypes(): void
    {
        $model = new NormalizedConfigModel(['number' => 12]);

        $this->assertSame(12, $model->number);
    }

    public function testConfigMakeAndWith(): void
    {
        $config = Config::make(['extra' => Config::EXTRA_FORBID]);

        $this->assertSame(Config::EXTRA_FORBID, $config->extra);
        $this->assertSame(Config::EXTRA_FORBID, $config->with(['title' => 'X'])->extra);
        $this->assertSame('X', $config->with(['title' => 'X'])->title);
        $this->assertIsArray($config->toArray());
    }

    public function testConfigRejectsInvalidExtra(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Option extra inconnue');

        new Config(extra: 'bogus');
    }

    public function testConfigRejectsUnknownOption(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Config::make(['inconnu' => true]);
    }

    public function testConfigRejectsNonCallableAliasGenerator(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Config(alias_generator: 'pas une fonction');
    }

    public function testModelSchemaUsesConfigTitle(): void
    {
        $schema = NormalizedConfigModel::model_schema();

        $this->assertSame('NormalizedConfigModel', $schema['title']);
        $this->assertSame('object', $schema['type']);
        $this->assertSame([], $schema['required']);
        $this->assertSame(['type' => 'string'], $schema['properties']['code']);
    }

    public function testModelSchemaListsRequiredFieldsWithAliases(): void
    {
        $schema = RequiredSchemaModel::model_schema();

        $this->assertSame(['req'], $schema['required']);
        $this->assertArrayHasKey('req', $schema['properties']);
    }
}
