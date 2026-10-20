<?php

namespace Svinx\Validator\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Svinx\Validator\ValidationError;
use Svinx\Validator\Validators\Date;
use Svinx\Validator\Validators\Datetime;
use Svinx\Validator\Validators\Decimal;
use Svinx\Validator\Validators\EmailStr;
use Svinx\Validator\Validators\HttpUrl;
use Svinx\Validator\Validators\IPv4Address;
use Svinx\Validator\Validators\IPv6Address;
use Svinx\Validator\Validators\UUID;
use Svinx\Validator\Examples\Validators\ContactList;
use Svinx\Validator\Examples\Validators\Customer;
use Svinx\Validator\Examples\Validators\LimitedEmail;

class ValidatorsTest extends TestCase
{
    // ---------------------------------------------------------------- EmailStr

    public function testEmailStrAcceptsValidEmail(): void
    {
        $email = new EmailStr('eor.bah@example.com');

        $this->assertSame('eor.bah@example.com', $email->value());
        $this->assertSame('eor.bah@example.com', $email->dump());
        $this->assertSame('eor.bah@example.com', (string) $email);
        $this->assertSame('eor.bah', $email->local());
        $this->assertSame('example.com', $email->domain());
    }

    public function testEmailStrTrimsWhitespace(): void
    {
        $this->assertSame('a@b.co', (new EmailStr('  a@b.co  '))->value());
    }

    public function testEmailStrRejectsInvalidEmail(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('n\'est pas une adresse email valide');

        new EmailStr('pas-un-email');
    }

    public function testEmailStrRejectsNonString(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('doit être une chaîne de caractères');

        new EmailStr(42);
    }

    // ----------------------------------------------------------------- HttpUrl

    public function testHttpUrlAcceptsValidUrl(): void
    {
        $url = new HttpUrl('https://example.com:8443/path?x=1#top');

        $this->assertSame('https', $url->scheme());
        $this->assertSame('example.com', $url->host());
        $this->assertSame(8443, $url->port());
        $this->assertSame('/path', $url->path());
        $this->assertSame('x=1', $url->query());
        $this->assertSame('top', $url->fragment());
    }

    public function testHttpUrlRejectsOtherScheme(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('schéma "ftp" non autorisé');

        new HttpUrl('ftp://example.com');
    }

    public function testHttpUrlRejectsInvalidUrl(): void
    {
        $this->expectException(ValidationError::class);

        new HttpUrl('pas une url');
    }

    // ------------------------------------------------------------- Adresses IP

    public function testIPv4AddressNormalizes(): void
    {
        $ip = new IPv4Address('192.168.1.10');

        $this->assertSame('192.168.1.10', $ip->value());
        $this->assertSame(3232235786, $ip->toInt());
        $this->assertSame("\xC0\xA8\x01\x0A", $ip->toBinary());
    }

    public function testIPv4AddressRejectsIPv6(): void
    {
        $this->expectException(ValidationError::class);

        new IPv4Address('::1');
    }

    public function testIPv6AddressCompresses(): void
    {
        $ip = new IPv6Address('2001:0DB8:0000:0000:0000:0000:0000:0001');

        $this->assertSame('2001:db8::1', $ip->value());
        $this->assertSame(16, strlen($ip->toBinary()));
    }

    public function testIPv6AddressRejectsIPv4(): void
    {
        $this->expectException(ValidationError::class);

        new IPv6Address('127.0.0.1');
    }

    public function testIPv6MappedIPv4(): void
    {
        $ip = new IPv6Address('::ffff:192.168.1.1');

        $this->assertSame('192.168.1.1', $ip->toIPv4()?->value());
    }

    // -------------------------------------------------------------------- UUID

    public function testUUIDAcceptsCanonicalForm(): void
    {
        $uuid = new UUID('123e4567-e89b-42d3-a456-426614174000');

        $this->assertSame('123e4567-e89b-42d3-a456-426614174000', $uuid->value());
        $this->assertSame(4, $uuid->version());
        $this->assertSame('RFC 4122', $uuid->variant());
    }

    public function testUUIDAcceptsCompactAndBracedForms(): void
    {
        $expected = '123e4567-e89b-42d3-a456-426614174000';

        $this->assertSame($expected, (new UUID('123E4567E89B42D3A456426614174000'))->value());
        $this->assertSame($expected, (new UUID('{123e4567-e89b-42d3-a456-426614174000}'))->value());
        $this->assertSame('123e4567e89b42d3a456426614174000', (new UUID($expected))->toCompact());
    }

    public function testUUIDRejectsInvalid(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('n\'est pas un UUID valide');

        new UUID('123e4567-e89b-42d3-a456-42661417400');
    }

    // ----------------------------------------------------------------- Decimal

    public function testDecimalKeepsStringPrecision(): void
    {
        $this->assertSame('0.1', (new Decimal('0.1'))->value());
        $this->assertSame('0.30000000000000004', (new Decimal(0.1 + 0.2))->value());
        $this->assertSame('1.5', (new Decimal('1.50'))->value());
        $this->assertSame('1000', (new Decimal('1e3'))->value());
        $this->assertSame('0.001', (new Decimal('1e-3'))->value());
        $this->assertSame('0', (new Decimal('-0'))->value());
        $this->assertSame('-2.25', (new Decimal('-2.25'))->value());
        $this->assertSame('12', (new Decimal(12))->value());
    }

    public function testDecimalComparison(): void
    {
        $this->assertTrue((new Decimal('1.50'))->equals(new Decimal('1.5')));
        $this->assertSame(0, (new Decimal('10'))->compare('10.000'));
        $this->assertSame(-1, (new Decimal('9.99'))->compare('10'));
        $this->assertSame(1, (new Decimal('-1'))->compare('-2'));
    }

    public function testDecimalConversions(): void
    {
        $decimal = new Decimal('42');

        $this->assertTrue($decimal->isInteger());
        $this->assertSame(42, $decimal->toInt());
        $this->assertFalse((new Decimal('0.5'))->isInteger());
    }

    public function testDecimalToIntRejectsFraction(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('sans perte');

        (new Decimal('1.5'))->toInt();
    }

    public function testDecimalRejectsInvalid(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('n\'est pas un nombre décimal valide');

        new Decimal('abc');
    }

    // --------------------------------------------------------------- Date / Datetime

    public function testDateAcceptsIsoDate(): void
    {
        $date = new Date('2024-02-29');

        $this->assertInstanceOf(DateTimeImmutable::class, $date->value());
        $this->assertSame('2024-02-29', $date->dump());
        $this->assertSame(2024, $date->year());
        $this->assertSame(2, $date->month());
        $this->assertSame(29, $date->day());
    }

    public function testDateAcceptsDateTimeInterface(): void
    {
        $date = new Date(new \DateTime('2023-07-04 15:30:00'));

        $this->assertSame('2023-07-04', $date->dump());
    }

    public function testDateRejectsInvalidDate(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('AAAA-MM-JJ');

        new Date('2024-02-30');
    }

    public function testDateRejectsAmbiguousFormat(): void
    {
        $this->expectException(ValidationError::class);

        // new \DateTime('12/25/2023') accepterait, Date non (parsing strict).
        new Date('12/25/2023');
    }

    public function testDatetimeAcceptsIso8601(): void
    {
        $this->assertSame('2024-01-02T03:04:05+00:00', (new Datetime('2024-01-02T03:04:05Z'))->dump());
        $this->assertSame('2024-01-02T03:04:05+00:00', (new Datetime('2024-01-02 03:04:05Z'))->dump());
        $this->assertSame('2024-01-02T03:04:05+02:00', (new Datetime('2024-01-02T03:04:05+02:00'))->dump());
        $this->assertSame('2024-01-02T03:04:00+00:00', (new Datetime('2024-01-02T03:04Z'))->dump());
        $this->assertSame('2024-01-02T00:00:00+00:00', (new Datetime('2024-01-02Z'))->dump());
    }

    public function testDatetimeWithMicroseconds(): void
    {
        $this->assertSame('2024-01-02T03:04:05.123456+00:00', (new Datetime('2024-01-02T03:04:05.123456Z'))->dump());
    }

    public function testDatetimeTimezoneConversion(): void
    {
        $utc = new Datetime('2024-01-02T12:00:00Z');
        $paris = $utc->toTimezone('Europe/Paris');

        $this->assertSame('2024-01-02T13:00:00+01:00', $paris->dump());
        $this->assertSame('2024-01-02', $utc->toDate()->dump());
    }

    public function testDatetimeRejectsInvalid(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('ISO 8601');

        new Datetime('demain');
    }

    public function testDatetimeRejectsImpossibleHour(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('heure impossible');

        new Datetime('2024-01-02T25:00:00Z');
    }

    // ------------------------------------------------------- Intégration modèle

    public function testModelCoercesValidatedTypes(): void
    {
        $customer = new Customer([
            'id' => '123e4567-e89b-42d3-a456-426614174000',
            'email' => 'Client@Example.com ',
            'site' => 'https://example.com',
            'balance' => '19.90',
            'birthday' => '1990-05-12',
            'created_at' => '2024-01-02T03:04:05Z',
            'last_ip' => '10.0.0.1',
            'ipv6' => '2001:DB8::1',
        ]);

        $this->assertInstanceOf(UUID::class, $customer->id);
        $this->assertSame('123e4567-e89b-42d3-a456-426614174000', $customer->id->value());
        $this->assertSame('Client@Example.com', $customer->email->value());
        $this->assertSame('https://example.com', $customer->site->value());
        $this->assertSame('19.9', $customer->balance->value());
        $this->assertSame('1990-05-12', $customer->birthday->dump());
        $this->assertSame('2024-01-02T03:04:05+00:00', $customer->created_at->dump());
        $this->assertSame('10.0.0.1', $customer->last_ip?->value());
        $this->assertSame('2001:db8::1', $customer->ipv6->value());
    }

    public function testModelDumpSerializesValidatedTypes(): void
    {
        $customer = new Customer([
            'id' => '123e4567-e89b-42d3-a456-426614174000',
            'email' => 'client@example.com',
            'site' => 'https://example.com',
            'balance' => '19.90',
            'birthday' => '1990-05-12',
            'created_at' => '2024-01-02T03:04:05Z',
            'ipv6' => '2001:db8::1',
        ]);

        $dumped = $customer->model_dump();

        $this->assertSame('123e4567-e89b-42d3-a456-426614174000', $dumped['id']);
        $this->assertSame('19.9', $dumped['balance']);
        $this->assertSame('1990-05-12', $dumped['birthday']);
        $this->assertSame('2024-01-02T03:04:05+00:00', $dumped['created_at']);
        $this->assertNull($dumped['last_ip']);
        $this->assertIsString($customer->model_dump_json());
        $this->assertStringContainsString('"balance":"19.9"', $customer->model_dump_json());
    }

    public function testModelRejectsInvalidValidatedType(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('n\'est pas une adresse email valide');

        new Customer([
            'id' => '123e4567-e89b-42d3-a456-426614174000',
            'email' => 'client@nope',
            'site' => 'https://example.com',
            'balance' => '1',
            'birthday' => '1990-05-12',
            'created_at' => '2024-01-02T03:04:05Z',
            'ipv6' => '2001:db8::1',
        ]);
    }

    public function testFieldConstraintsApplyToRawValueBeforeConversion(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('ne doit pas dépasser 10 caractères');

        new LimitedEmail(['email' => 'tres.long@example.com']);
    }

    public function testValidatedArrayDump(): void
    {
        $collection = new ContactList(['emails' => ['a@b.co', 'c@d.co']]);

        $this->assertSame(['a@b.co', 'c@d.co'], $collection->model_dump()['emails']);
    }

    public function testValidatedTypesCanBeNested(): void
    {
        $datetime = new Datetime('2023-12-31T23:00:00-05:00');

        $this->assertSame('2023-12-31', (new Date($datetime))->dump());
        $this->assertSame('2024-06-01T00:00:00+00:00', (new Datetime(new Date('2024-06-01')))->dump());
        $this->assertSame('3.14', (new Decimal(new Decimal('3.140')))->value());
        $this->assertSame('a@b.co', (new EmailStr(new EmailStr('a@b.co')))->value());
    }

    public function testValidatedTypeInstanceIsKeptAsIs(): void
    {
        $uuid = new UUID('123e4567-e89b-42d3-a456-426614174000');

        $this->assertSame($uuid, UUID::validate($uuid));
    }
}
