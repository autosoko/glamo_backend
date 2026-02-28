<?php

namespace App\Filament\Resources\Providers\Tables;

use App\Filament\Resources\Providers\ProviderResource;
use App\Models\Provider;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProvidersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['user'])
                ->withCount(['orders', 'services']))
            ->columns([
                ImageColumn::make('profile_image_url')
                    ->label('Picha')
                    ->getStateUsing(fn (Provider $record): string => (string) ($record->profile_image_url ?? asset('images/placeholder.svg')))
                    ->defaultImageUrl(asset('images/placeholder.svg'))
                    ->circular()
                    ->imageSize(44),

                TextColumn::make('user.name')
                    ->label('Jina')
                    ->searchable(),

                TextColumn::make('user.phone')
                    ->label('Simu')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('approval_status')
                    ->label('Uhakiki')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'approved' => 'Imeidhinishwa',
                        'pending' => 'Inasubiri',
                        'needs_more_steps' => 'Imeidhinishwa kwa hatua (Partial)',
                        'rejected' => 'Imekataliwa',
                        default => ucfirst(str_replace('_', ' ', $state)),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'needs_more_steps' => 'danger',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('online_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'online' => 'Online',
                        'offline' => 'Offline',
                        'busy' => 'Busy',
                        'blocked_debt' => 'Imefungwa kwa deni',
                        default => ucfirst(str_replace('_', ' ', $state)),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'online' => 'success',
                        'busy' => 'warning',
                        'blocked_debt' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('orders_count')
                    ->label('Order')
                    ->sortable(),

                TextColumn::make('services_count')
                    ->label('Huduma')
                    ->sortable(),

                TextColumn::make('wallet_balance')
                    ->label('Wallet')
                    ->money('TZS')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('debt_balance')
                    ->label('Deni')
                    ->money('TZS')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('approval_status')
                    ->label('Uhakiki')
                    ->options([
                        'pending' => 'Inasubiri',
                        'approved' => 'Imeidhinishwa',
                        'needs_more_steps' => 'Imeidhinishwa kwa hatua (Partial)',
                        'rejected' => 'Imekataliwa',
                    ]),

                SelectFilter::make('online_status')
                    ->label('Status')
                    ->options([
                        'online' => 'Online',
                        'offline' => 'Offline',
                        'busy' => 'Busy',
                        'blocked_debt' => 'Blocked debt',
                    ]),

                TernaryFilter::make('is_active')
                    ->label('Akaunti active'),
            ])
            ->recordActions([
                EditAction::make(),

                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Provider $record): bool => (string) $record->approval_status !== 'approved')
                    ->action(function (Provider $record): void {
                        $data = ProviderResource::normalizeApprovalData([
                            'approval_status' => 'approved',
                            'approved_at' => now(),
                            'approval_note' => 'Umeidhinishwa kikamilifu. Profile yako iko tayari kupokea oda.',
                        ]);

                        $record->update([
                            'approval_status' => $data['approval_status'],
                            'approved_at' => $data['approved_at'],
                            'online_status' => $data['online_status'],
                            'offline_reason' => $data['offline_reason'],
                            'rejection_reason' => $data['rejection_reason'],
                            'interview_required' => $data['interview_required'],
                            'interview_status' => $data['interview_status'],
                            'interview_scheduled_at' => $data['interview_scheduled_at'],
                            'interview_type' => $data['interview_type'],
                            'interview_location' => $data['interview_location'],
                            'approval_note' => $data['approval_note'],
                        ]);

                        ProviderResource::ensureProviderUserRole($record);
                        ProviderResource::notifyProviderStatusUpdate($record->fresh(), [
                            'source' => 'providers_table',
                            'action' => 'approve',
                            'changed_fields' => [
                                'approval_status',
                                'approved_at',
                                'online_status',
                                'offline_reason',
                                'rejection_reason',
                                'interview_required',
                                'interview_status',
                                'interview_scheduled_at',
                                'interview_type',
                                'interview_location',
                                'approval_note',
                            ],
                        ]);
                    }),

                Action::make('needs_more_steps')
                    ->label('Partial approve')
                    ->icon('heroicon-o-exclamation-circle')
                    ->color('warning')
                    ->form([
                        DateTimePicker::make('interview_scheduled_at')
                            ->label('Tarehe na saa ya interview')
                            ->native(false)
                            ->required(),
                        TextInput::make('interview_location')
                            ->label('Sehemu ya interview')
                            ->required()
                            ->maxLength(180),
                        TextInput::make('interview_type')
                            ->label('Aina ya interview')
                            ->default('Demo ya kazi')
                            ->required()
                            ->maxLength(120),
                        Textarea::make('approval_note')
                            ->label('Maelezo ya hatua zaidi (hiari)')
                            ->default('Umeidhinishwa kwa hatua (partial approved). Umepangiwa interview ya uthibitisho.')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Provider $record, array $data): void {
                        $normalized = ProviderResource::normalizeApprovalData([
                            'approval_status' => 'needs_more_steps',
                            'approval_note' => $data['approval_note'] ?? null,
                            'interview_scheduled_at' => $data['interview_scheduled_at'] ?? null,
                            'interview_location' => $data['interview_location'] ?? null,
                            'interview_type' => $data['interview_type'] ?? 'Demo ya kazi',
                            'interview_status' => 'scheduled',
                        ]);

                        $record->update([
                            'approval_status' => $normalized['approval_status'],
                            'approved_at' => $normalized['approved_at'],
                            'online_status' => $normalized['online_status'],
                            'interview_required' => $normalized['interview_required'],
                            'interview_status' => $normalized['interview_status'],
                            'interview_scheduled_at' => $normalized['interview_scheduled_at'],
                            'interview_location' => $normalized['interview_location'],
                            'interview_type' => $normalized['interview_type'],
                            'approval_note' => $normalized['approval_note'],
                        ]);

                        ProviderResource::notifyProviderStatusUpdate($record->fresh(), [
                            'source' => 'providers_table',
                            'action' => 'needs_more_steps',
                            'changed_fields' => [
                                'approval_status',
                                'approved_at',
                                'online_status',
                                'interview_required',
                                'interview_status',
                                'interview_scheduled_at',
                                'interview_location',
                                'interview_type',
                                'approval_note',
                            ],
                        ]);
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Textarea::make('rejection_reason')
                            ->label('Sababu ya kukataa')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Provider $record, array $data): void {
                        $record->update([
                            'approval_status' => 'rejected',
                            'approved_at' => null,
                            'online_status' => 'offline',
                            'rejection_reason' => $data['rejection_reason'],
                        ]);

                        ProviderResource::notifyProviderStatusUpdate($record->fresh(), [
                            'source' => 'providers_table',
                            'action' => 'reject',
                            'changed_fields' => ['approval_status', 'approved_at', 'online_status', 'rejection_reason'],
                        ]);
                    }),

                Action::make('set_online')
                    ->label('Weka online')
                    ->icon('heroicon-o-signal')
                    ->color('success')
                    ->visible(fn (Provider $record): bool => (string) $record->online_status !== 'online')
                    ->action(function (Provider $record): void {
                        $debtBlock = (float) config('glamo_pricing.provider_debt_block_threshold', 10000);

                        if ((float) $record->debt_balance > $debtBlock) {
                            $record->update([
                                'online_status' => 'blocked_debt',
                                'offline_reason' => 'Ana deni kubwa kuliko threshold.',
                            ]);

                            ProviderResource::notifyProviderStatusUpdate($record->fresh(), [
                                'source' => 'providers_table',
                                'action' => 'set_online_blocked',
                                'changed_fields' => ['online_status', 'offline_reason'],
                            ]);

                            return;
                        }

                        $record->update([
                            'online_status' => 'online',
                            'offline_reason' => null,
                        ]);

                        ProviderResource::notifyProviderStatusUpdate($record->fresh(), [
                            'source' => 'providers_table',
                            'action' => 'set_online',
                            'changed_fields' => ['online_status', 'offline_reason'],
                        ]);
                    }),

                Action::make('set_offline')
                    ->label('Weka offline')
                    ->icon('heroicon-o-pause-circle')
                    ->color('gray')
                    ->visible(fn (Provider $record): bool => (string) $record->online_status !== 'offline')
                    ->form([
                        TextInput::make('offline_reason')
                            ->label('Sababu (optional)')
                            ->maxLength(255),
                    ])
                    ->action(function (Provider $record, array $data): void {
                        $record->update([
                            'online_status' => 'offline',
                            'offline_reason' => $data['offline_reason'] ?? 'Imewekwa offline na admin.',
                        ]);

                        ProviderResource::notifyProviderStatusUpdate($record->fresh(), [
                            'source' => 'providers_table',
                            'action' => 'set_offline',
                            'changed_fields' => ['online_status', 'offline_reason'],
                        ]);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }
}
