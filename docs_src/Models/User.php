<?php

namespace Svinx\Validator\Examples\Models;

use Svinx\Validator\BaseModel;
use Svinx\Validator\Field;

class User extends BaseModel
{
    #[Field(alias: 'user_id', description: 'ID unique', gt: 0)]
    public int $id;

    #[Field(alias: 'full_name', min_length: 2, max_length: 50)]
    public string $name = 'Anonyme';

    #[Field(choices: ['admin', 'user', 'guest'])]
    public string $role = 'user';

    #[Field(alias: 'birth_date')]
    public ?\DateTime $birthday = null;
}
