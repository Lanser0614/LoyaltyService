<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CouponUsageResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Promotion\Models\CouponUsage;
use Modules\Shared\Enums\CouponUsageStatus;

class CouponUsageResource extends Resource
{
    protected static ?string $model = CouponUsage::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationGroup = 'Audit';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('coupon.code')->label('Coupon')->searchable(),
                Tables\Columns\TextColumn::make('customer_id')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('order_id')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('discount_amount')->money('UZS')->sortable(),
                Tables\Columns\TextColumn::make('applied_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('cancelled_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(CouponUsageStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->value])->all()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCouponUsages::route('/'),
            'view' => Pages\ViewCouponUsage::route('/{record}'),
        ];
    }
}
