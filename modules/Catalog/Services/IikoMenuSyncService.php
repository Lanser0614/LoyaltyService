<?php

namespace Modules\Catalog\Services;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Http\IikoApiClient;

class IikoMenuSyncService
{
    public function __construct(
        private readonly IikoApiClient $client,
        private readonly IikoOrganizationResolver $organizations,
    ) {
    }

    public function sync(array $organizationIds = [], int $startRevision = 0, array $externalMenuIds = []): array
    {
        $resolvedOrganizationIds = $this->organizations->resolve($organizationIds);

        if ($resolvedOrganizationIds === []) {
            return ['menus' => 0, 'menu_items' => 0, 'iiko_requests' => 0];
        }

        $summary = ['menus' => 0, 'menu_items' => 0, 'iiko_requests' => 0];
        $menus = $this->menusForSync($externalMenuIds, $summary);

        foreach ($menus as $index => $menu) {
            $menuId = $menu['id'];

            DB::table('iiko_menus')->updateOrInsert(
                ['external_id' => $menuId],
                [
                    'name' => $menu['name'] ?? '',
                    'description' => $menu['description'] ?? null,
                    'raw_payload' => json_encode($menu),
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );

            $dbMenuId = DB::table('iiko_menus')->where('external_id', $menuId)->value('id');

            if ($index > 0) {
                sleep((int) config('iiko.sync.request_delay_seconds', 4));
            }

            $payload = $this->client->post('/2/menu/by_id', [
                'externalMenuId' => $menuId,
                'organizationIds' => $resolvedOrganizationIds,
                'priceCategoryId' => null,
                'startRevision' => $startRevision,
            ])->json();
            $summary['iiko_requests']++;

            DB::table('iiko_menus')->where('id', $dbMenuId)->update([
                'revision' => (int) ($payload['revision'] ?? 0),
                'raw_payload' => json_encode($payload),
                'updated_at' => now(),
            ]);

            $summary['menus']++;
            $summary['menu_items'] += $this->upsertMenuItems($dbMenuId, $payload, $resolvedOrganizationIds);
        }

        return $summary;
    }

    private function upsertMenuItems(int $menuId, array $payload, array $organizationIds): int
    {
        $count = 0;
        $items = $this->extractItems($payload['itemCategories'] ?? $payload['items'] ?? []);

        foreach ($items as $item) {
            $productId = $item['itemId'] ?? $item['id'] ?? $item['productId'] ?? null;

            if (! $productId) {
                continue;
            }

            foreach ($organizationIds as $organizationId) {
                DB::table('iiko_menu_items')->updateOrInsert(
                    [
                        'iiko_menu_id' => $menuId,
                        'organization_id' => $organizationId,
                        'product_external_id' => $productId,
                    ],
                    [
                        'price' => $this->priceForOrganization($item, $organizationId),
                        'is_active' => ! (bool) ($item['isDeleted'] ?? false),
                        'raw_payload' => json_encode($item),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ],
                );
                $count++;
            }
        }

        return $count;
    }

    private function menusForSync(array $externalMenuIds, array &$summary): array
    {
        if ($externalMenuIds !== []) {
            return array_map(
                fn (string $id) => ['id' => $id, 'name' => $id, 'description' => null],
                array_values($externalMenuIds),
            );
        }

        $summary['iiko_requests']++;

        return $this->client->post('/2/menu')->json('externalMenus', []);
    }

    private function extractItems(array $nodes): array
    {
        $items = [];

        foreach ($nodes as $node) {
            if (isset($node['items']) && is_array($node['items'])) {
                $items = [...$items, ...$this->extractItems($node['items'])];
                continue;
            }

            if (isset($node['itemSizes']) || isset($node['prices']) || isset($node['itemId'])) {
                $items[] = $node;
            }
        }

        return $items;
    }

    private function priceForOrganization(array $item, string $organizationId): int
    {
        $prices = $item['prices'] ?? $item['itemSizes'][0]['prices'] ?? [];

        foreach ($prices as $price) {
            if (($price['organizationId'] ?? null) === $organizationId) {
                return (int) round(((float) ($price['price'] ?? 0)) * 100);
            }
        }

        return (int) round(((float) ($prices[0]['price'] ?? 0)) * 100);
    }
}
