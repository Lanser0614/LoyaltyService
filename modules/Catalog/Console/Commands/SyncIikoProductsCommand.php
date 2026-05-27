<?php

namespace Modules\Catalog\Console\Commands;

use Illuminate\Console\Command;
use Modules\Catalog\Services\IikoProductSyncService;

class SyncIikoProductsCommand extends Command
{
    protected $signature = 'catalog:sync-products
        {--organization=* : iiko organization id, can be passed multiple times}
        {--start-revision=0 : iiko nomenclature startRevision}';

    protected $description = 'Sync iiko nomenclature products and product groups into the local catalog snapshot.';

    public function handle(IikoProductSyncService $sync): int
    {
        $summary = $sync->sync(
            organizationIds: $this->option('organization'),
            startRevision: (int) $this->option('start-revision'),
        );

        $this->components->info(sprintf(
            'Synced products: %d organizations, %d groups, %d products, %d iiko request(s).',
            $summary['organizations'],
            $summary['groups'],
            $summary['products'],
            $summary['iiko_requests'],
        ));

        return self::SUCCESS;
    }
}
