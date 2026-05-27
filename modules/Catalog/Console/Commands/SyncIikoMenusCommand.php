<?php

namespace Modules\Catalog\Console\Commands;

use Illuminate\Console\Command;
use Modules\Catalog\Services\IikoMenuSyncService;

class SyncIikoMenusCommand extends Command
{
    protected $signature = 'catalog:sync-menus
        {--organization=* : iiko organization id, can be passed multiple times}
        {--menu=* : iiko external menu id, can be passed multiple times}
        {--start-revision=0 : iiko menu startRevision}';

    protected $description = 'Sync iiko external menus and menu items into the local catalog snapshot.';

    public function handle(IikoMenuSyncService $sync): int
    {
        $summary = $sync->sync(
            organizationIds: $this->option('organization'),
            startRevision: (int) $this->option('start-revision'),
            externalMenuIds: $this->option('menu'),
        );

        $this->components->info(sprintf(
            'Synced menus: %d menus, %d menu items, %d iiko request(s).',
            $summary['menus'],
            $summary['menu_items'],
            $summary['iiko_requests'],
        ));

        return self::SUCCESS;
    }
}
