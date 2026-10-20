<?php

namespace Svinx\Validator\Examples\TypeAdapters;

use Svinx\Validator\BaseModel;
use Svinx\Validator\Validators\EmailStr;

class Customer extends BaseModel
{
    public string $name = 'Anonyme';

    public EmailStr $email;
}
