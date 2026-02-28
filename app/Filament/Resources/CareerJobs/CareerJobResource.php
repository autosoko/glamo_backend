<?php

namespace App\Filament\Resources\CareerJobs;

use App\Filament\Resources\CareerJobs\Pages\CreateCareerJob;
use App\Filament\Resources\CareerJobs\Pages\EditCareerJob;
use App\Filament\Resources\CareerJobs\Pages\ListCareerJobs;
use App\Filament\Resources\CareerJobs\Schemas\CareerJobForm;
use App\Filament\Resources\CareerJobs\Tables\CareerJobsTable;
use App\Models\CareerJob;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class CareerJobResource extends Resource
{
    protected static ?string $model = CareerJob::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static UnitEnum | string | null $navigationGroup = 'Kampuni';

    protected static ?string $navigationLabel = 'Kazi';

    protected static ?string $modelLabel = 'Kazi';

    protected static ?string $pluralModelLabel = 'Kazi';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return CareerJobForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CareerJobsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCareerJobs::route('/'),
            'create' => CreateCareerJob::route('/create'),
            'edit' => EditCareerJob::route('/{record}/edit'),
        ];
    }
}

