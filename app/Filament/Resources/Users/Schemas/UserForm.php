<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Taarifa za mtumiaji')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Jina')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('phone')
                            ->label('Simu')
                            ->tel()
                            ->maxLength(20),

                        Forms\Components\TextInput::make('email')
                            ->label('Barua pepe')
                            ->email()
                            ->maxLength(255),

                        Forms\Components\Select::make('role')
                            ->label('Role')
                            ->required()
                            ->options([
                                'client' => 'Client',
                                'provider' => 'Provider',
                                'staff' => 'Staff',
                                'admin' => 'Admin',
                            ])
                            ->default('client'),

                        Forms\Components\TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create'),

                        Forms\Components\DateTimePicker::make('otp_verified_at')
                            ->label('OTP verified at')
                            ->native(false),
                    ])
                    ->columns(2),
            ]);
    }
}
