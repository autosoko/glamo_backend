<?php

namespace App\Filament\Resources\Staffs\Tables;

use App\Models\Staff;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StaffTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Jina')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.phone')
                    ->label('Simu')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        Staff::STATUS_APPROVED => 'success',
                        Staff::STATUS_REJECTED => 'danger',
                        default => 'warning',
                    }),

                TextColumn::make('original_role')
                    ->label('Role ya awali')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('approved_at')
                    ->label('Approved at')
                    ->dateTime()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        Staff::STATUS_PENDING => 'Pending',
                        Staff::STATUS_APPROVED => 'Approved',
                        Staff::STATUS_REJECTED => 'Rejected',
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
