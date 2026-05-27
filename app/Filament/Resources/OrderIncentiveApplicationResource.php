<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderIncentiveApplicationResource\Pages;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Checkout\Models\OrderIncentiveApplication;
use Modules\Shared\Enums\IncentiveApplicationStatus;

class OrderIncentiveApplicationResource extends Resource
{
    protected static ?string $model = OrderIncentiveApplication::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Audit';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('incentive_application_id')->copyable()->searchable(),
                Tables\Columns\TextColumn::make('order_id')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('customer_id')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('total_discount_amount')->money('UZS')->sortable(),
                Tables\Columns\TextColumn::make('reserved_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('applied_at')->dateTime()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('cancelled_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(IncentiveApplicationStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->value])->all()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\TextEntry::make('incentive_application_id')->copyable(),
            Infolists\Components\TextEntry::make('order_id'),
            Infolists\Components\TextEntry::make('customer_id'),
            Infolists\Components\TextEntry::make('status')->badge(),
            Infolists\Components\TextEntry::make('total_discount_amount')->money('UZS'),
            Infolists\Components\KeyValueEntry::make('incentives_snapshot')->columnSpanFull(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrderIncentiveApplications::route('/'),
            'view' => Pages\ViewOrderIncentiveApplication::route('/{record}'),
        ];
    }
}
