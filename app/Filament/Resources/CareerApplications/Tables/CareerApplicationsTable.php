<?php

namespace App\Filament\Resources\CareerApplications\Tables;

use App\Models\CareerJobApplication;
use App\Support\PublicFileUrl;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CareerApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['careerJob', 'user']))
            ->columns([
                TextColumn::make('careerJob.title')
                    ->label('Kazi')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Mwombaji')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.phone')
                    ->label('Simu')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        CareerJobApplication::STATUS_APPROVED => 'success',
                        CareerJobApplication::STATUS_REJECTED => 'danger',
                        default => 'warning',
                    }),

                TextColumn::make('cv_file_path')
                    ->label('CV')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? 'Fungua CV' : '-')
                    ->url(fn (CareerJobApplication $record): ?string => filled($record->cv_file_path)
                        ? PublicFileUrl::url((string) $record->cv_file_path)
                        : null)
                    ->openUrlInNewTab()
                    ->color('primary'),

                TextColumn::make('application_letter_file_path')
                    ->label('Barua')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? 'Fungua barua' : '-')
                    ->url(fn (CareerJobApplication $record): ?string => filled($record->application_letter_file_path)
                        ? PublicFileUrl::url((string) $record->application_letter_file_path)
                        : null)
                    ->openUrlInNewTab()
                    ->color('primary'),

                TextColumn::make('reviewed_at')
                    ->label('Reviewed')
                    ->dateTime()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Applied')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        CareerJobApplication::STATUS_PENDING => 'Pending',
                        CareerJobApplication::STATUS_APPROVED => 'Approved',
                        CareerJobApplication::STATUS_REJECTED => 'Rejected',
                    ]),

                SelectFilter::make('career_job_id')
                    ->label('Kazi')
                    ->relationship('careerJob', 'title'),
            ])
            ->recordActions([
                EditAction::make(),

                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (CareerJobApplication $record): bool => (string) $record->status !== CareerJobApplication::STATUS_APPROVED)
                    ->action(function (CareerJobApplication $record): void {
                        $record->update([
                            'status' => CareerJobApplication::STATUS_APPROVED,
                        ]);
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Textarea::make('admin_note')
                            ->label('Sababu (optional)')
                            ->rows(3),
                    ])
                    ->requiresConfirmation()
                    ->visible(fn (CareerJobApplication $record): bool => (string) $record->status !== CareerJobApplication::STATUS_REJECTED)
                    ->action(function (CareerJobApplication $record, array $data): void {
                        $record->update([
                            'status' => CareerJobApplication::STATUS_REJECTED,
                            'admin_note' => (string) ($data['admin_note'] ?? ''),
                        ]);
                    }),

                Action::make('reset_pending')
                    ->label('Rudisha pending')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (CareerJobApplication $record): bool => (string) $record->status !== CareerJobApplication::STATUS_PENDING)
                    ->action(function (CareerJobApplication $record): void {
                        $record->update([
                            'status' => CareerJobApplication::STATUS_PENDING,
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
