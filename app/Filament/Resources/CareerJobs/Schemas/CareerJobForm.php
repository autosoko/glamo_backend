<?php

namespace App\Filament\Resources\CareerJobs\Schemas;

use App\Models\CareerJob;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CareerJobForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Taarifa za kazi')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Jina la kazi')
                            ->required()
                            ->maxLength(180),

                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->maxLength(200)
                            ->unique(ignoreRecord: true)
                            ->helperText('Acha wazi mfumo utaitengeneza kutoka jina la kazi.'),

                        Forms\Components\Select::make('employment_type')
                            ->label('Aina ya ajira')
                            ->required()
                            ->options([
                                CareerJob::TYPE_FULL_TIME => 'Full-time',
                                CareerJob::TYPE_PART_TIME => 'Part-time',
                                CareerJob::TYPE_CONTRACT => 'Contract',
                                CareerJob::TYPE_INTERNSHIP => 'Internship',
                            ])
                            ->default(CareerJob::TYPE_FULL_TIME),

                        Forms\Components\TextInput::make('location')
                            ->label('Mji / eneo')
                            ->maxLength(120),

                        Forms\Components\TextInput::make('positions_count')
                            ->label('Idadi ya nafasi')
                            ->numeric()
                            ->minValue(1),

                        Forms\Components\DatePicker::make('application_deadline')
                            ->label('Mwisho wa maombi')
                            ->native(false),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->required()
                            ->options([
                                CareerJob::STATUS_DRAFT => 'Draft',
                                CareerJob::STATUS_PUBLISHED => 'Published',
                                CareerJob::STATUS_CLOSED => 'Closed',
                            ])
                            ->default(CareerJob::STATUS_DRAFT),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Ionekane kwenye page ya kazi')
                            ->default(true),

                        Forms\Components\Textarea::make('summary')
                            ->label('Muhtasari mfupi')
                            ->rows(3)
                            ->maxLength(600)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('description')
                            ->label('Maelezo ya kazi')
                            ->required()
                            ->rows(6)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('requirements')
                            ->label('Mahitaji (optional)')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
            ]);
    }
}

