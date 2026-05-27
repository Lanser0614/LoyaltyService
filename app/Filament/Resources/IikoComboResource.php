<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IikoComboResource\Pages;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Catalog\Models\IikoCombo;

class IikoComboResource extends Resource
{
    protected static ?string $model = IikoCombo::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Catalog';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('external_id')->copyable()->searchable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('organization_id')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\TextEntry::make('external_id')->copyable(),
            Infolists\Components\TextEntry::make('name'),
            Infolists\Components\TextEntry::make('organization_id'),
            Infolists\Components\TextEntry::make('status')->badge(),
            Infolists\Components\IconEntry::make('is_active')->boolean(),
            Infolists\Components\KeyValueEntry::make('raw_payload')->columnSpanFull(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIikoCombos::route('/'),
            'view' => Pages\ViewIikoCombo::route('/{record}'),
        ];
    }
}
