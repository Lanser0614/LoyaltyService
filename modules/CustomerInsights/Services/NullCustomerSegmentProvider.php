<?php

namespace Modules\CustomerInsights\Services;

use Modules\CustomerInsights\Contracts\CustomerSegmentProviderInterface;

class NullCustomerSegmentProvider implements CustomerSegmentProviderInterface
{
    public function hasSegment(int $customerId, string $segmentCode): bool
    {
        return false;
    }

    public function getSegments(int $customerId): array
    {
        return [];
    }
}
