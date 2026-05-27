<?php

namespace Modules\CustomerInsights\Contracts;

interface CustomerSegmentProviderInterface
{
    public function hasSegment(int $customerId, string $segmentCode): bool;

    /**
     * @return array<int, string>
     */
    public function getSegments(int $customerId): array;
}
