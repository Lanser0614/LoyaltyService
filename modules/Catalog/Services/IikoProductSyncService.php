<?php

namespace Modules\Catalog\Services;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Http\IikoApiClient;

class IikoProductSyncService
{
    public function __construct(
        private readonly IikoApiClient $client,
        private readonly IikoOrganizationResolver $organizations,
    ) {
    }

    public function sync(array $organizationIds = [], int $startRevision = 0): array
    {
        $resolvedOrganizationIds = $organizationIds === []
            ? [(string) config('iiko.cloud_call_center_org_id')]
            : $this->organizations->resolve($organizationIds);

        if ($resolvedOrganizationIds === []) {
            return ['organizations' => 0, 'groups' => 0, 'products' => 0, 'iiko_requests' => 0];
        }

        $summary = [
            'organizations' => count($resolvedOrganizationIds),
            'groups' => 0,
            'products' => 0,
            'iiko_requests' => 0,
            'synced_organization_ids' => [],
        ];

        foreach ($resolvedOrganizationIds as $index => $organizationId) {
            if ($index > 0) {
                sleep((int) config('iiko.sync.request_delay_seconds', 4));
            }

            $payload = $this->client->post('/1/nomenclature', [
                'organizationId' => $organizationId,
                'startRevision' => $startRevision,
            ])->json();

            $summary['iiko_requests']++;

            DB::transaction(function () use ($organizationId, $payload, &$summary) {
                $this->upsertGroups($organizationId, $payload['groups'] ?? [], $summary);
                $this->upsertProducts($organizationId, $payload['products'] ?? [], $summary);
            });

            $summary['synced_organization_ids'][] = $organizationId;
        }

        return $summary;
    }

    private function upsertGroups(string $organizationId, array $groups, array &$summary): void
    {
        foreach ($groups as $group) {
            DB::table('iiko_product_groups')->updateOrInsert(
                [
                    'organization_id' => $organizationId,
                    'external_id' => $group['id'],
                ],
                [
                    'parent_external_id' => $group['parentGroup'] ?? $group['parentGroupId'] ?? null,
                    'name' => $group['name'] ?? '',
                    'is_active' => ! (bool) ($group['isDeleted'] ?? false),
                    'raw_payload' => json_encode($group),
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
            $summary['groups']++;
        }
    }

    private function upsertProducts(string $organizationId, array $products, array &$summary): void
    {
        foreach ($products as $product) {
            DB::table('iiko_products')->updateOrInsert(
                [
                    'organization_id' => $organizationId,
                    'external_id' => $product['id'],
                ],
                [
                    'group_external_id' => $product['parentGroup'] ?? $product['groupId'] ?? null,
                    'name' => $product['name'] ?? '',
                    'type' => $product['type'] ?? null,
                    'is_active' => ! (bool) ($product['isDeleted'] ?? false),
                    'raw_payload' => json_encode($product),
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
            $summary['products']++;
        }
    }
}
