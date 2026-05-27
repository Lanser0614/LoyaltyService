<?php

namespace Modules\Shared\Exceptions;

use Modules\Shared\Enums\FailureReason;
use RuntimeException;

class IncentiveRejectedException extends RuntimeException
{
    public function __construct(public readonly FailureReason $reason, string $message = '')
    {
        parent::__construct($message ?: $reason->value);
    }
}
