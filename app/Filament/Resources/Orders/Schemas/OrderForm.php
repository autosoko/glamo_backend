<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order')
                    ->schema([
                        Forms\Components\TextInput::make('order_no')
                            ->label('Order no')
                            ->disabled(),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->required()
                            ->options([
                                'pending' => 'Pending',
                                'accepted' => 'Accepted',
                                'on_the_way' => 'On the way',
                                'in_progress' => 'In progress',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                            ]),

                        Forms\Components\Select::make('payment_status')
                            ->label('Payment status')
                            ->options([
                                'pending' => 'Pending',
                                'paid' => 'Paid',
                                'failed' => 'Failed',
                                'refunded' => 'Refunded',
                            ]),

                        Forms\Components\Select::make('payment_method')
                            ->label('Payment method')
                            ->options([
                                'cash' => 'Cash',
                                'prepay' => 'Prepay',
                            ]),

                        Forms\Components\TextInput::make('payment_channel')
                            ->label('Payment channel')
                            ->maxLength(80),

                        Forms\Components\TextInput::make('payment_provider')
                            ->label('Payment provider')
                            ->maxLength(80),

                        Forms\Components\TextInput::make('payment_reference')
                            ->label('Payment reference')
                            ->maxLength(120),
                    ])
                    ->columns(3),

                Section::make('Bei na commission')
                    ->schema([
                        Forms\Components\TextInput::make('price_total')
                            ->label('Jumla (TZS)')
                            ->numeric(),

                        Forms\Components\TextInput::make('commission_rate')
                            ->label('Commission rate')
                            ->numeric(),

                        Forms\Components\TextInput::make('commission_amount')
                            ->label('Commission amount (TZS)')
                            ->numeric(),

                        Forms\Components\TextInput::make('payout_amount')
                            ->label('Payout amount (TZS)')
                            ->numeric(),
                    ])
                    ->columns(4),

                Section::make('Timeline')
                    ->schema([
                        Forms\Components\DateTimePicker::make('scheduled_at')
                            ->label('Scheduled at')
                            ->native(false),

                        Forms\Components\DateTimePicker::make('accepted_at')
                            ->label('Accepted at')
                            ->native(false),

                        Forms\Components\DateTimePicker::make('on_the_way_at')
                            ->label('On the way at')
                            ->native(false),

                        Forms\Components\DateTimePicker::make('provider_arrived_at')
                            ->label('Provider arrived at')
                            ->native(false),

                        Forms\Components\DateTimePicker::make('client_arrival_confirmed_at')
                            ->label('Client confirmed arrival')
                            ->native(false),

                        Forms\Components\DateTimePicker::make('completed_at')
                            ->label('Completed at')
                            ->native(false),
                    ])
                    ->columns(3),

                Section::make('Maelezo')
                    ->schema([
                        Forms\Components\Textarea::make('address_text')
                            ->label('Address')
                            ->rows(2),

                        Forms\Components\Textarea::make('completion_note')
                            ->label('Completion note')
                            ->rows(3),

                        Forms\Components\TextInput::make('refund_reference')
                            ->label('Refund reference')
                            ->maxLength(120),
                    ])
                    ->columns(1),
            ]);
    }
}
