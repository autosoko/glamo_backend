<?php

namespace App\Filament\Resources\CareerJobs\Tables;

use App\Models\CareerJob;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CareerJobsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('applications'))
            ->columns([
                TextColumn::make('title')
                    ->label('Kazi')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        CareerJob::STATUS_PUBLISHED => 'success',
                        CareerJob::STATUS_CLOSED => 'danger',
                        default => 'warning',
                    }),

                TextColumn::make('employment_type')
                    ->label('Aina')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        CareerJob::TYPE_PART_TIME => 'Part-time',
                        CareerJob::TYPE_CONTRACT => 'Contract',
                        CareerJob::TYPE_INTERNSHIP => 'Internship',
                        default => 'Full-time',
                    })
                    ->badge()
                    ->color('gray'),

                TextColumn::make('location')
                    ->label('Eneo')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('application_deadline')
                    ->label('Mwisho')
                    ->date()
                    ->sortable(),

                TextColumn::make('applications_count')
                    ->label('Waombaji')
                    ->sortable(),

                TextColumn::make('is_active')
                    ->label('Inaonekana')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Ndiyo' : 'Hapana')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        CareerJob::STATUS_DRAFT => 'Draft',
                        CareerJob::STATUS_PUBLISHED => 'Published',
                        CareerJob::STATUS_CLOSED => 'Closed',
                    ]),

                SelectFilter::make('employment_type')
                    ->label('Aina')
                    ->options([
                        CareerJob::TYPE_FULL_TIME => 'Full-time',
                        CareerJob::TYPE_PART_TIME => 'Part-time',
                        CareerJob::TYPE_CONTRACT => 'Contract',
                        CareerJob::TYPE_INTERNSHIP => 'Internship',
                    ]),

                TernaryFilter::make('is_active')
                    ->label('Inaonekana'),
            ])
            ->recordActions([
                EditAction::make(),

                Action::make('publish')
                    ->label('Publish')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (CareerJob $record): bool => (string) $record->status !== CareerJob::STATUS_PUBLISHED || ! (bool) $record->is_active)
                    ->action(function (CareerJob $record): void {
                        $record->update([
                            'status' => CareerJob::STATUS_PUBLISHED,
                            'is_active' => true,
                            'published_at' => $record->published_at ?: now(),
                        ]);
                    }),

                Action::make('close')
                    ->label('Funga')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (CareerJob $record): bool => (string) $record->status !== CareerJob::STATUS_CLOSED)
                    ->action(function (CareerJob $record): void {
                        $record->update([
                            'status' => CareerJob::STATUS_CLOSED,
                            'is_active' => false,
                        ]);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}

