<?php

namespace App\Filament\Widgets;

use App\Models\Provider;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class TopProvidersByOrders extends TableWidget
{
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Watoa huduma kwa idadi ya order')
            ->query(
                Provider::query()
                    ->with('user')
                    ->withCount('orders')
                    ->orderByDesc('orders_count')
            )
            ->columns([
                TextColumn::make('user.name')
                    ->label('Jina')
                    ->searchable(),

                TextColumn::make('user.phone')
                    ->label('Simu')
                    ->searchable(),

                TextColumn::make('orders_count')
                    ->label('Orders')
                    ->sortable(),

                TextColumn::make('online_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'online' => 'Online',
                        'offline' => 'Offline',
                        'busy' => 'Busy',
                        'blocked_debt' => 'Blocked debt',
                        default => ucfirst(str_replace('_', ' ', $state)),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'online' => 'success',
                        'busy' => 'warning',
                        'blocked_debt' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('approval_status')
                    ->label('Uhakiki')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'approved' => 'Approved',
                        'pending' => 'Pending',
                        'needs_more_steps' => 'Needs more steps',
                        'rejected' => 'Rejected',
                        default => ucfirst(str_replace('_', ' ', $state)),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'needs_more_steps' => 'danger',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('orders_count', 'desc');
    }
}

