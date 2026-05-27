<?php

namespace Modules\Promotion\DTOs;

use Modules\Shared\Enums\FailureReason;

class ConditionCheckResult
{
    public function __construct(
        public readonly bool $passed,
        public readonly ?FailureReason $failureReason = null,
        public readonly array $details = [],
    ) {
    }

    public static function passed(array $details = []): self
    {
        return new self(true, null, $details);
    }

    public static function failed(FailureReason $reason, array $details = []): self
    {
        return new self(false, $reason, $details);
    }
}
