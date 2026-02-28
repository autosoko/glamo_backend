<?php

namespace App\Filament\Resources\CareerApplications\Pages;

use App\Filament\Resources\CareerApplications\CareerApplicationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCareerApplication extends EditRecord
{
    protected static string $resource = CareerApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

