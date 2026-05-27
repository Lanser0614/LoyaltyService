<?php

namespace Modules\Catalog\Console\Commands;

use Illuminate\Console\Command;
use Modules\Catalog\Services\IikoComboSyncService;
use Modules\Catalog\Services\IikoMenuSyncService;
use Modules\Catalog\Services\IikoProductSyncService;

class SyncIikoCatalogCommand extends Command
{
    protected $signature = 'catalog:sync
        {--organization=* : iiko organization id, can be passed multiple times}
        {--menu=* : iiko external menu id, can be passed multiple times}
        {--start-revision=0 : iiko startRevision for product/menu sync}
        {--products : sync products}
        {--menus : sync menus}
        {--combos : sync combos}';

    protected $description = 'Sync selected iiko catalog snapshots. If no type is selected, products, menus and combos are synced.';

    public function handle(
        IikoProductSyncService $products,
        IikoMenuSyncService $menus,
        IikoComboSyncService $combos,
    ): int {
        $onlyProducts = (bool) $this->option('products');
        $onlyMenus = (bool) $this->option('menus');
        $onlyCombos = (bool) $this->option('combos');
        $syncAll = ! $onlyProducts && ! $onlyMenus && ! $onlyCombos;
        $organizationIds = $this->option('organization');
        $externalMenuIds = $this->option('menu');
        $startRevision = (int) $this->option('start-revision');

        if ($syncAll || $onlyProducts) {
            $summary = $products->sync($organizationIds, $startRevision);
            $this->components->info(
                "Products: {$summary['products']} products, {$summary['groups']} groups, {$summary['iiko_requests']} iiko request(s)."
            );
        }

        if ($syncAll || $onlyMenus) {
            $summary = $menus->sync($organizationIds, $startRevision, $externalMenuIds);
            $this->components->info(
                "Menus: {$summary['menus']} menus, {$summary['menu_items']} menu items, {$summary['iiko_requests']} iiko request(s)."
            );
        }

        if ($syncAll || $onlyCombos) {
            $summary = $combos->sync($organizationIds);
            $this->components->info(
                "Combos: {$summary['combos']} combos, {$summary['groups']} groups, {$summary['items']} items, {$summary['iiko_requests']} iiko request(s)."
            );
        }

        return self::SUCCESS;
    }
}
