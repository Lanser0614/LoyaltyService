<?php

namespace App\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Modules\Checkout\DTOs\CheckoutIncentiveRequest;
use Modules\Checkout\Services\CheckoutIncentiveService;

class CouponSimulationPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $navigationLabel = 'Simulation';

    protected static ?string $navigationGroup = 'Promotion';

    protected static string $view = 'filament.pages.coupon-simulation-page';

    public ?array $data = [];

    public ?array $result = null;

    public function mount(): void
    {
        $this->form->fill([
            'customer_id' => null,
            'order_total' => 0,
            'delivery_fee' => 0,
            'use_bellcoin' => false,
            'bellcoin_amount' => 0,
            'items' => [],
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make('Checkout')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('customer_id')->required()->numeric(),
                        Forms\Components\TextInput::make('order_total')->required()->numeric()->minValue(0),
                        Forms\Components\TextInput::make('coupon_code'),
                        Forms\Components\TextInput::make('delivery_fee')->numeric()->minValue(0)->default(0),
                        Forms\Components\Toggle::make('use_bellcoin')->live(),
                        Forms\Components\TextInput::make('bellcoin_amount')
                            ->numeric()
                            ->minValue(0)
                            ->visible(fn (Forms\Get $get) => (bool) $get('use_bellcoin')),
                    ]),
                Forms\Components\Section::make('Items')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->schema([
                                Forms\Components\TextInput::make('iiko_product_id')->required(),
                                Forms\Components\TextInput::make('quantity')->numeric()->default(1),
                                Forms\Components\TextInput::make('price')->numeric()->default(0),
                                Forms\Components\TextInput::make('combo_iiko_id'),
                            ])
                            ->columns(4)
                            ->defaultItems(0),
                    ]),
            ]);
    }

    public function simulate(CheckoutIncentiveService $service): void
    {
        $payload = $this->form->getState();
        $this->result = $service->preview(CheckoutIncentiveRequest::fromArray($payload))->toArray();

        Notification::make()
            ->title(($this->result['allowed'] ?? false) ? 'Simulation allowed' : 'Simulation rejected')
            ->body(($this->result['failure_reason'] ?? null) ?: null)
            ->success((bool) ($this->result['allowed'] ?? false))
            ->send();
    }
}
