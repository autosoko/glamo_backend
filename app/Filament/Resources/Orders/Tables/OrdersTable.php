<?php

namespace App\Filament\Resources\Orders\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['client', 'provider.user', 'service']))
            ->columns([
                TextColumn::make('order_no')
                    ->label('Order no')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('client.name')
                    ->label('Mteja')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('provider.user.name')
                    ->label('Mtoa huduma')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('service.name')
                    ->label('Huduma')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        'accepted' => 'info',
                        'on_the_way' => 'warning',
                        'in_progress' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'paid' => 'success',
                        'failed' => 'danger',
                        'pending' => 'warning',
                        'refunded' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('price_total')
                    ->label('Jumla')
                    ->money('TZS')
                    ->sortable(),

                TextColumn::make('commission_amount')
                    ->label('Commission')
                    ->money('TZS')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Imewekwa')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'accepted' => 'Accepted',
                        'on_the_way' => 'On the way',
                        'in_progress' => 'In progress',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),

                SelectFilter::make('payment_status')
                    ->label('Payment')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
