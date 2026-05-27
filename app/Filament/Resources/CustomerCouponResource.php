<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerCouponResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\CustomerWallet\Models\CustomerCoupon;
use Modules\Shared\Enums\CustomerCouponStatus;

class CustomerCouponResource extends Resource
{
    protected static ?string $model = CustomerCoupon::class;

    protected static ?string $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationGroup = 'Customer Wallet';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('coupon_id')->relationship('coupon', 'name')->required()->searchable()->preload(),
            Forms\Components\TextInput::make('customer_id')->required()->numeric(),
            Forms\Components\TextInput::make('code')->maxLength(255),
            Forms\Components\TextInput::make('campaign_key')->maxLength(255),
            Forms\Components\Select::make('status')
                ->options(collect(CustomerCouponStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->value])->all())
                ->required()
                ->default(CustomerCouponStatus::Available->value),
            Forms\Components\TextInput::make('issued_reason')->maxLength(255),
            Forms\Components\DateTimePicker::make('starts_at'),
            Forms\Components\DateTimePicker::make('expires_at'),
            Forms\Components\KeyValue::make('metadata')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('coupon.code')->label('Coupon')->searchable(),
                Tables\Columns\TextColumn::make('customer_id')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('campaign_key')->toggleable(),
                Tables\Columns\TextColumn::make('expires_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('used_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(CustomerCouponStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->value])->all()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomerCoupons::route('/'),
            'create' => Pages\CreateCustomerCoupon::route('/create'),
            'edit' => Pages\EditCustomerCoupon::route('/{record}/edit'),
        ];
    }
}
