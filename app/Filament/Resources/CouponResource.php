<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CouponResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Promotion\Models\Coupon;
use Modules\Shared\Enums\CouponKind;
use Modules\Shared\Enums\CouponStatus;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'Promotion';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('Coupon')
                ->columnSpanFull()
                ->tabs([
                    Forms\Components\Tabs\Tab::make('Main')
                        ->schema([
                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\TextInput::make('code')->required()->maxLength(255)->unique(ignoreRecord: true),
                                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                                Forms\Components\Select::make('coupon_kind')
                                    ->required()
                                    ->options(self::enumOptions(CouponKind::cases()))
                                    ->default(CouponKind::PublicCodeCoupon->value),
                                Forms\Components\Select::make('status')
                                    ->required()
                                    ->options(self::enumOptions(CouponStatus::cases()))
                                    ->default(CouponStatus::Draft->value),
                                Forms\Components\DateTimePicker::make('starts_at'),
                                Forms\Components\DateTimePicker::make('ends_at'),
                                Forms\Components\TextInput::make('priority')->numeric()->default(0),
                            ]),
                            Forms\Components\Grid::make(4)->schema([
                                Forms\Components\Toggle::make('auto_apply'),
                                Forms\Components\Toggle::make('visible_to_customer')->default(true),
                                Forms\Components\Toggle::make('requires_code_input')->default(true),
                                Forms\Components\Toggle::make('stackable'),
                            ]),
                        ]),
                    Forms\Components\Tabs\Tab::make('Usage Conditions')
                        ->schema([
                            Forms\Components\Repeater::make('conditions')
                                ->relationship()
                                ->schema(self::conditionSchema())
                                ->columns(2)
                                ->defaultItems(0),
                        ]),
                    Forms\Components\Tabs\Tab::make('Issue Conditions')
                        ->schema([
                            Forms\Components\Repeater::make('issueConditions')
                                ->relationship()
                                ->schema(self::conditionSchema(includeIssueConditions: true))
                                ->columns(2)
                                ->defaultItems(0),
                        ]),
                    Forms\Components\Tabs\Tab::make('Actions')
                        ->schema([
                            Forms\Components\Repeater::make('actions')
                                ->relationship()
                                ->schema([
                                    Forms\Components\Select::make('action_type')
                                        ->required()
                                        ->live()
                                        ->options([
                                            'percent' => 'Percent discount',
                                            'fixed' => 'Fixed discount',
                                            'free_delivery' => 'Free delivery',
                                            'free_product' => 'Free product',
                                            'free_combo' => 'Free combo',
                                        ]),
                                    Forms\Components\TextInput::make('value')
                                        ->numeric()
                                        ->visible(fn (Forms\Get $get) => in_array($get('action_type'), ['percent', 'fixed'], true)),
                                    Forms\Components\TextInput::make('max_discount_amount')
                                        ->numeric()
                                        ->visible(fn (Forms\Get $get) => $get('action_type') === 'percent'),
                                    Forms\Components\TextInput::make('product_iiko_id')
                                        ->visible(fn (Forms\Get $get) => $get('action_type') === 'free_product'),
                                    Forms\Components\TextInput::make('combo_iiko_id')
                                        ->visible(fn (Forms\Get $get) => $get('action_type') === 'free_combo'),
                                    Forms\Components\TextInput::make('quantity')
                                        ->numeric()
                                        ->default(1)
                                        ->visible(fn (Forms\Get $get) => in_array($get('action_type'), ['free_product', 'free_combo'], true)),
                                    Forms\Components\TextInput::make('price_override')->numeric(),
                                ])
                                ->columns(3)
                                ->defaultItems(1),
                        ]),
                    Forms\Components\Tabs\Tab::make('Compatibility')
                        ->schema([
                            Forms\Components\Toggle::make('is_stackable_with_bellcoin')->label('Can be used with BellCoin'),
                            Forms\Components\Toggle::make('is_stackable_with_other_coupons')->label('Can be used with other coupons'),
                        ]),
                    Forms\Components\Tabs\Tab::make('Limits')
                        ->schema([
                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\TextInput::make('usage_limit_total')->numeric(),
                                Forms\Components\TextInput::make('usage_limit_per_customer')->numeric(),
                                Forms\Components\TextInput::make('issue_limit_total')->numeric(),
                                Forms\Components\TextInput::make('issue_limit_per_customer')->numeric(),
                            ]),
                            Forms\Components\KeyValue::make('issue_policy'),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('coupon_kind')->badge(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\IconColumn::make('visible_to_customer')->boolean(),
                Tables\Columns\TextColumn::make('starts_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('ends_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(self::enumOptions(CouponStatus::cases())),
                Tables\Filters\SelectFilter::make('coupon_kind')->options(self::enumOptions(CouponKind::cases())),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }

    private static function conditionSchema(bool $includeIssueConditions = false): array
    {
        $conditionOptions = [
            'cart.min_amount' => 'Cart minimum amount',
            'customer.segment' => 'Customer segment',
            'cart.has_combo' => 'Cart has combo',
        ];

        if ($includeIssueConditions) {
            $conditionOptions['order.delivery_time_over'] = 'Order delivery time over minutes';
        }

        return [
            Forms\Components\Select::make('condition_type')
                ->required()
                ->live()
                ->options($conditionOptions),
            Forms\Components\Select::make('operator')
                ->options([
                    '>=' => '>=',
                    '>' => '>',
                    '=' => '=',
                ]),
            Forms\Components\TextInput::make('value')
                ->visible(fn (Forms\Get $get) => in_array($get('condition_type'), ['cart.min_amount', 'customer.segment', 'order.delivery_time_over'], true)),
            Forms\Components\KeyValue::make('payload')->columnSpanFull(),
        ];
    }

    private static function enumOptions(array $cases): array
    {
        return collect($cases)->mapWithKeys(fn ($case) => [$case->value => $case->value])->all();
    }
}
