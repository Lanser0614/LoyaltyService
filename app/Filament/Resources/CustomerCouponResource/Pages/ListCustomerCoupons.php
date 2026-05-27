<?php

namespace App\Filament\Resources\CustomerCouponResource\Pages;

use App\Filament\Resources\CustomerCouponResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomerCoupons extends ListRecords
{
    protected static string $resource = CustomerCouponResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
