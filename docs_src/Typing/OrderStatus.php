<?php

namespace Svinx\Validator\Examples\Typing;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
}
