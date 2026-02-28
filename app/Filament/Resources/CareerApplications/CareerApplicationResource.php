<?php

namespace App\Filament\Resources\CareerApplications;

use App\Filament\Resources\CareerApplications\Pages\EditCareerApplication;
use App\Filament\Resources\CareerApplications\Pages\ListCareerApplications;
use App\Filament\Resources\CareerApplications\Schemas\CareerApplicationForm;
use App\Filament\Resources\CareerApplications\Tables\CareerApplicationsTable;
use App\Models\CareerJobApplication;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class CareerApplicationResource extends Resource
{
    protected static ?string $model = CareerJobApplication::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static UnitEnum | string | null $navigationGroup = 'Kampuni';

    protected static ?string $navigationLabel = 'Waombaji Kazi';

    protected static ?string $modelLabel = 'Mwombaji kazi';

    protected static ?string $pluralModelLabel = 'Waombaji kazi';

    protected static ?int $navigationSort = 11;

    public static function form(Schema $schema): Schema
    {
        return CareerApplicationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CareerApplicationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCareerApplications::route('/'),
            'edit' => EditCareerApplication::route('/{record}/edit'),
        ];
    }
}

