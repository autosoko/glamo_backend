<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getTableQuery(): Builder | Relation | null
    {
        $query = parent::getTableQuery();

        if (! $query instanceof Builder) {
            return $query;
        }

        $scope = strtolower(trim((string) request()->query('scope', '')));

        if ($scope === 'clients') {
            $query
                ->where(function (Builder $builder): void {
                    $builder
                        ->where('users.role', 'client')
                        ->orWhereNull('users.role');
                })
                ->whereDoesntHave('provider');
        }

        return $query;
    }
}
