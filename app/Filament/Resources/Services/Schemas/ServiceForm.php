<?php

namespace App\Filament\Resources\Services\Schemas;

use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Taarifa kuu za huduma')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Jina la huduma')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->maxLength(255)
                            ->helperText('Ukiiacha wazi, mfumo utatengeneza slug kutoka jina la huduma.'),

                        Forms\Components\Select::make('category_id')
                            ->label('Category')
                            ->relationship('serviceCategory', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Textarea::make('short_desc')
                            ->label('Maelezo mafupi')
                            ->rows(3)
                            ->maxLength(500),

                        Forms\Components\TextInput::make('duration_minutes')
                            ->label('Muda (dakika)')
                            ->numeric()
                            ->default(60)
                            ->minValue(1)
                            ->required(),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sort order')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Iko active')
                            ->default(true),
                    ])
                    ->columns(3),

                Section::make('Bei')
                    ->schema([
                        Forms\Components\TextInput::make('base_price')
                            ->label('Base price (TZS)')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->required(),

                        Forms\Components\TextInput::make('materials_price')
                            ->label('Materials price (TZS)')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),

                        Forms\Components\TextInput::make('usage_percent')
                            ->label('Usage percent')
                            ->numeric()
                            ->default(5)
                            ->minValue(0)
                            ->maxValue(100),

                        Forms\Components\Toggle::make('hair_wash_enabled')
                            ->label('Option ya kuosha nywele')
                            ->default(false)
                            ->live(),

                        Forms\Components\TextInput::make('hair_wash_price')
                            ->label('Bei ya kuosha nywele (TZS)')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->visible(fn ($get): bool => (bool) $get('hair_wash_enabled')),

                        Forms\Components\Toggle::make('hair_wash_default_selected')
                            ->label('Ichaguliwe default kwenye booking')
                            ->default(false)
                            ->visible(fn ($get): bool => (bool) $get('hair_wash_enabled')),
                    ])
                    ->columns(3),

                Section::make('Picha za huduma')
                    ->description('Unaweza kuongeza picha zaidi ya moja kutoka kwenye computer.')
                    ->schema([
                        Forms\Components\Repeater::make('media')
                            ->label('Gallery')
                            ->relationship('media')
                            ->schema([
                                Forms\Components\FileUpload::make('file_path')
                                    ->label('Picha')
                                    ->image()
                                    ->imageEditor()
                                    ->disk('public')
                                    ->directory('services')
                                    ->moveFiles()
                                    ->dehydrateStateUsing(fn (?string $state): ?string => str_starts_with((string) $state, 'livewire-file:') ? null : $state)
                                    ->required()
                                    ->downloadable()
                                    ->openable(),

                                Forms\Components\TextInput::make('sort_order')
                                    ->label('Mpangilio')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0),
                            ])
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->defaultItems(1)
                            ->addActionLabel('Ongeza picha nyingine')
                            ->columns(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
