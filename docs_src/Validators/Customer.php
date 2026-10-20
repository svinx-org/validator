<?php

namespace Svinx\Validator\Examples\Validators;

use Svinx\Validator\BaseModel;
use Svinx\Validator\Field;
use Svinx\Validator\Validators\Date;
use Svinx\Validator\Validators\Datetime;
use Svinx\Validator\Validators\Decimal;
use Svinx\Validator\Validators\EmailStr;
use Svinx\Validator\Validators\HttpUrl;
use Svinx\Validator\Validators\IPv4Address;
use Svinx\Validator\Validators\IPv6Address;
use Svinx\Validator\Validators\UUID;

class Customer extends BaseModel
{
    #[Field(alias: 'id')]
    public UUID $id;

    #[Field(alias: 'email')]
    public EmailStr $email;

    #[Field(alias: 'site')]
    public HttpUrl $site;

    public Decimal $balance;

    public Date $birthday;

    public Datetime $created_at;

    public ?IPv4Address $last_ip = null;

    public IPv6Address $ipv6;
}
