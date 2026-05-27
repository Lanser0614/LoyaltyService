<?php

namespace Modules\Catalog\Services;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Http\IikoApiClient;

class IikoOrganizationResolver
{
    /** @var array<int, string>|null */
    private static ?array $cachedOrganizationIds = null;

    public function __construct(private readonly IikoApiClient $client)
    {
    }

    /**
     * @return array<int, string>
     */
    public function resolve(array $organizationIds = []): array
    {
        if ($organizationIds !== []) {
            return array_values($organizationIds);
        }

        if (self::$cachedOrganizationIds !== null) {
            return self::$cachedOrganizationIds;
        }

        return DB::table('iiko_organizations')
            ->where('is_active', true)
            ->pluck('external_id')
           ->toArray();

//        if ($localOrganizationIds !== []) {
//            self::$cachedOrganizationIds = $localOrganizationIds;
//
//            return self::$cachedOrganizationIds;
//        }
//
//        $response = $this->client->post('/1/organizations', [
//            'organizationIds' => null,
//            'returnAdditionalInfo' => true,
//            'includeDisabled' => false,
//        ]);
//
//        $organizations = $response->json('organizations', []);
//
//        foreach ($organizations as $organization) {
//            DB::table('iiko_organizations')->updateOrInsert(
//                ['external_id' => $organization['id']],
//                [
//                    'name' => $organization['name'] ?? '',
//                    'country_code' => $organization['country'] ?? null,
//                    'currency' => $organization['currencyIsoName'] ?? $organization['currency'] ?? null,
//                    'timezone' => $organization['timezone'] ?? null,
//                    'is_active' => ! (bool) ($organization['isDisabled'] ?? false),
//                    'raw_payload' => json_encode($organization),
//                    'updated_at' => now(),
//                    'created_at' => now(),
//                ],
//            );
//        }
//
//        self::$cachedOrganizationIds = collect($organizations)
//            ->pluck('id')
//            ->filter()
//            ->values()
//            ->all();
//
//        return self::$cachedOrganizationIds;
    }
}
