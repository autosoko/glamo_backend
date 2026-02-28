<?php

namespace App\Filament\Resources\CareerJobs\Pages;

use App\Filament\Resources\CareerJobs\CareerJobResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCareerJobs extends ListRecords
{
    protected static string $resource = CareerJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

