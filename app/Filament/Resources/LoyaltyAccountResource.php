<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LoyaltyAccountResource\Pages;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Loyalty\Models\LoyaltyAccount;

class LoyaltyAccountResource extends Resource
{
    protected static ?string $model = LoyaltyAccount::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Loyalty';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer_id')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('balance')->money('UZS')->sortable(),
                Tables\Columns\TextColumn::make('reserved_balance')->money('UZS')->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\TextEntry::make('customer_id'),
            Infolists\Components\TextEntry::make('balance')->money('UZS'),
            Infolists\Components\TextEntry::make('reserved_balance')->money('UZS'),
            Infolists\Components\TextEntry::make('status')->badge(),
            Infolists\Components\RepeatableEntry::make('transactions')
                ->schema([
                    Infolists\Components\TextEntry::make('type')->badge(),
                    Infolists\Components\TextEntry::make('amount')->money('UZS'),
                    Infolists\Components\TextEntry::make('balance_after')->money('UZS'),
                    Infolists\Components\TextEntry::make('status')->badge(),
                    Infolists\Components\TextEntry::make('reason'),
                    Infolists\Components\TextEntry::make('created_at')->dateTime(),
                ])
                ->columns(3)
                ->columnSpanFull(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoyaltyAccounts::route('/'),
            'view' => Pages\ViewLoyaltyAccount::route('/{record}'),
        ];
    }
}
