<?php

namespace Modules\Catalog\Services;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Http\IikoApiClient;

class IikoComboSyncService
{
    public function __construct(
        private readonly IikoApiClient $client,
        private readonly IikoOrganizationResolver $organizations,
    ) {
    }

    public function sync(array $organizationIds = []): array
    {
        $resolvedOrganizationIds = $organizationIds === []
            ? [(string) config('iiko.cloud_call_center_org_id')]
            : $this->organizations->resolve($organizationIds);

        if ($resolvedOrganizationIds === []) {
            return ['organizations' => 0, 'combos' => 0, 'groups' => 0, 'items' => 0, 'iiko_requests' => 0];
        }

        $summary = [
            'organizations' => count($resolvedOrganizationIds),
            'combos' => 0,
            'groups' => 0,
            'items' => 0,
            'iiko_requests' => 0,
            'synced_organization_ids' => [],
        ];

        foreach ($resolvedOrganizationIds as $index => $organizationId) {
            if ($index > 0) {
                sleep((int) config('iiko.sync.request_delay_seconds', 4));
            }

            $payload = $this->client->post('/1/combo/get_combos_info', [
                'organizationId' => $organizationId,
            ])->json();

            $summary['iiko_requests']++;

            DB::transaction(function () use ($organizationId, $payload, &$summary) {
                foreach ($payload['comboSpecifications'] ?? $payload['combos'] ?? [] as $combo) {
                    $this->upsertCombo($organizationId, $combo, $summary);
                }
            });

            $summary['synced_organization_ids'][] = $organizationId;
        }

        return $summary;
    }

    private function upsertCombo(string $organizationId, array $combo, array &$summary): void
    {
        $comboExternalId = $combo['sourceActionId'] ?? $combo['id'] ?? null;

        if (! $comboExternalId) {
            return;
        }

        DB::table('iiko_combos')->updateOrInsert(
            [
                'organization_id' => $organizationId,
                'external_id' => $comboExternalId,
            ],
            [
                'name' => $combo['name'] ?? '',
                'status' => $this->comboStatus($combo),
                'is_active' => $this->comboIsActive($combo),
                'raw_payload' => json_encode($combo),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        $comboDbId = DB::table('iiko_combos')
            ->where('organization_id', $organizationId)
            ->where('external_id', $comboExternalId)
            ->value('id');

        DB::table('iiko_combo_groups')->where('iiko_combo_id', $comboDbId)->delete();

        foreach ($combo['groups'] ?? $combo['comboGroups'] ?? [] as $group) {
            $groupDbId = DB::table('iiko_combo_groups')->insertGetId([
                'iiko_combo_id' => $comboDbId,
                'external_id' => $group['id'] ?? null,
                'name' => $group['name'] ?? null,
                'raw_payload' => json_encode($group),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $summary['groups']++;

            foreach ($group['products'] ?? $group['items'] ?? [] as $item) {
                $productId = $item['productId'] ?? $item['id'] ?? null;

                if (! $productId) {
                    continue;
                }

                DB::table('iiko_combo_group_items')->insert([
                    'iiko_combo_group_id' => $groupDbId,
                    'product_external_id' => $productId,
                    'raw_payload' => json_encode($item),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $summary['items']++;
            }
        }

        $summary['combos']++;
    }

    private function comboStatus(array $combo): ?string
    {
        if (array_key_exists('status', $combo)) {
            return $combo['status'];
        }

        if (array_key_exists('isActive', $combo)) {
            return $combo['isActive'] ? 'active' : 'inactive';
        }

        return null;
    }

    private function comboIsActive(array $combo): bool
    {
        if (array_key_exists('isActive', $combo)) {
            return (bool) $combo['isActive'];
        }

        return ($combo['status'] ?? 'ACTIVE') !== 'DELETED';
    }
}
