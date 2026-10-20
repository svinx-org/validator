<?php

namespace Svinx\Validator\Examples\Typing;

use DateTime;
use Svinx\Validator\BaseModel;
use Svinx\Validator\Validators\EmailStr;

class Order extends BaseModel
{
    public int $count;
    public ?string $label = null;
    public array $tags = [];
    public EmailStr $owner;
    public ?DateTime $born = null;
    public OrderStatus $status;
}
