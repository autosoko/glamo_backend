<?php

namespace App\Filament\Resources\Staffs\Schemas;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StaffForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Taarifa za staff')
                    ->schema([
                        Forms\Components\Radio::make('user_mode')
                            ->label('Njia ya kumuweka staff')
                            ->options([
                                'existing' => 'Chagua user aliyepo',
                                'new' => 'Tengeneza user mpya',
                            ])
                            ->default('existing')
                            ->inline()
                            ->live()
                            ->hiddenOn('edit'),

                        Forms\Components\Select::make('user_id')
                            ->label('Mtumiaji')
                            ->relationship(
                                name: 'user',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query) => $query->orderByDesc('id'),
                            )
                            ->getOptionLabelFromRecordUsing(function ($record): string {
                                $name = trim((string) data_get($record, 'name'));
                                if ($name !== '') {
                                    return $name;
                                }

                                $phone = trim((string) data_get($record, 'phone'));
                                if ($phone !== '') {
                                    return $phone;
                                }

                                $email = trim((string) data_get($record, 'email'));
                                if ($email !== '') {
                                    return $email;
                                }

                                return 'User #' . (string) data_get($record, 'id', '-');
                            })
                            ->searchable(['name', 'phone', 'email'])
                            ->preload()
                            ->required(fn ($get): bool => (string) $get('user_mode') !== 'new')
                            ->visible(fn ($get): bool => (string) $get('user_mode') !== 'new')
                            ->unique(ignoreRecord: true)
                            ->helperText('Chagua user wa kawaida au provider umbadilishe kuwa staff.'),

                        Forms\Components\TextInput::make('new_user_name')
                            ->label('Jina la user mpya (hiari)')
                            ->maxLength(255)
                            ->visibleOn('create')
                            ->visible(fn ($get): bool => (string) $get('user_mode') === 'new'),

                        Forms\Components\TextInput::make('new_user_phone')
                            ->label('Simu ya login')
                            ->placeholder('07XXXXXXXX au 2557XXXXXXXX')
                            ->tel()
                            ->maxLength(20)
                            ->visibleOn('create')
                            ->visible(fn ($get): bool => (string) $get('user_mode') === 'new'),

                        Forms\Components\TextInput::make('new_user_email')
                            ->label('Email ya login')
                            ->email()
                            ->maxLength(255)
                            ->visibleOn('create')
                            ->visible(fn ($get): bool => (string) $get('user_mode') === 'new'),

                        Forms\Components\TextInput::make('new_user_password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->minLength(6)
                            ->maxLength(255)
                            ->visibleOn('create')
                            ->visible(fn ($get): bool => (string) $get('user_mode') === 'new'),

                        Forms\Components\TextInput::make('new_user_password_confirmation')
                            ->label('Rudia password')
                            ->password()
                            ->revealable()
                            ->minLength(6)
                            ->maxLength(255)
                            ->visibleOn('create')
                            ->visible(fn ($get): bool => (string) $get('user_mode') === 'new')
                            ->helperText('Weka angalau simu au email kwa login ya user mpya.'),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->required()
                            ->options([
                                Staff::STATUS_PENDING => 'Pending',
                                Staff::STATUS_APPROVED => 'Approved',
                                Staff::STATUS_REJECTED => 'Rejected',
                            ])
                            ->default(Staff::STATUS_PENDING),

                        Forms\Components\Textarea::make('notes')
                            ->label('Maelezo')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
