<?php

namespace Svinx\Validator\Examples\Validators;

use Svinx\Validator\BaseModel;
use Svinx\Validator\Field;
use Svinx\Validator\Validators\EmailStr;

class LimitedEmail extends BaseModel
{
    #[Field(max_length: 10)]
    public EmailStr $email;
}
