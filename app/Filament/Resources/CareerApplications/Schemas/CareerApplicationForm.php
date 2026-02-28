<?php

namespace App\Filament\Resources\CareerApplications\Schemas;

use App\Models\CareerJobApplication;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CareerApplicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Maombi ya kazi')
                    ->schema([
                        Forms\Components\Select::make('career_job_id')
                            ->label('Kazi')
                            ->relationship('careerJob', 'title')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabledOn('edit'),

                        Forms\Components\Select::make('user_id')
                            ->label('Mwombaji')
                            ->relationship('user', 'name')
                            ->searchable(['name', 'phone', 'email'])
                            ->preload()
                            ->required()
                            ->disabledOn('edit'),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->required()
                            ->options([
                                CareerJobApplication::STATUS_PENDING => 'Pending',
                                CareerJobApplication::STATUS_APPROVED => 'Approved',
                                CareerJobApplication::STATUS_REJECTED => 'Rejected',
                            ])
                            ->default(CareerJobApplication::STATUS_PENDING),

                        Forms\Components\FileUpload::make('cv_file_path')
                            ->label('CV ya mwombaji')
                            ->disk('public')
                            ->directory('careers/cv')
                            ->acceptedFileTypes([
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            ])
                            ->downloadable()
                            ->openable()
                            ->columnSpanFull(),

                        Forms\Components\FileUpload::make('application_letter_file_path')
                            ->label('Barua ya maombi')
                            ->disk('public')
                            ->directory('careers/application-letters')
                            ->acceptedFileTypes([
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            ])
                            ->downloadable()
                            ->openable()
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('cover_letter')
                            ->label('Ujumbe wa mwombaji')
                            ->rows(4)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('admin_note')
                            ->label('Maelezo ya admin')
                            ->rows(4)
                            ->columnSpanFull(),

                        Forms\Components\DateTimePicker::make('reviewed_at')
                            ->label('Reviewed at')
                            ->native(false)
                            ->disabled(),
                    ])
                    ->columns(2),
            ]);
    }
}
