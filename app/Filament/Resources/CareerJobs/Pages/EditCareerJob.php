<?php

namespace App\Filament\Resources\CareerJobs\Pages;

use App\Filament\Resources\CareerJobs\CareerJobResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCareerJob extends EditRecord
{
    protected static string $resource = CareerJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

