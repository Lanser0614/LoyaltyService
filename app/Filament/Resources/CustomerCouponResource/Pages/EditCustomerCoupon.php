<?php

namespace App\Filament\Resources\CustomerCouponResource\Pages;

use App\Filament\Resources\CustomerCouponResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomerCoupon extends EditRecord
{
    protected static string $resource = CustomerCouponResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
