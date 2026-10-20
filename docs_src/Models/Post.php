<?php

namespace Svinx\Validator\Examples\Models;

use Svinx\Validator\BaseModel;

class Post extends BaseModel
{
    public string $title;
    public string $content;
    public User $author;
}
