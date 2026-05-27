<?php

namespace Modules\Catalog\Console\Commands;

use Illuminate\Console\Command;
use Modules\Catalog\Services\IikoComboSyncService;

class SyncIikoCombosCommand extends Command
{
    protected $signature = 'catalog:sync-combos
        {--organization=* : iiko organization id, can be passed multiple times}';

    protected $description = 'Sync iiko combos, combo groups and combo group items into the local catalog snapshot.';

    public function handle(IikoComboSyncService $sync): int
    {
        $summary = $sync->sync($this->option('organization'));

        $this->components->info(sprintf(
            'Synced combos: %d organizations, %d combos, %d groups, %d items, %d iiko request(s).',
            $summary['organizations'],
            $summary['combos'],
            $summary['groups'],
            $summary['items'],
            $summary['iiko_requests'],
        ));

        return self::SUCCESS;
    }
}
