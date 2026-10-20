<?php

namespace Svinx\Validator\Examples\Config;

use Svinx\Validator\BaseModel;
use Svinx\Validator\Field;

class RequiredSchemaModel extends BaseModel
{
    #[Field(alias: 'req')]
    public string $required_field;
}
