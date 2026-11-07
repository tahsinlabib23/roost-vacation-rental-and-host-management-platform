<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class MessageConflictException extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('This message retry token was already used for different message content or conversation.');
    }
}
