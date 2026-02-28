<?php

namespace App\Filament\Resources\Providers\Pages;

use App\Filament\Resources\Providers\ProviderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class ListProviders extends ListRecords
{
    protected static string $resource = ProviderResource::class;

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

        return match ($scope) {
            'online' => $query->where('providers.online_status', 'online'),
            'offline' => $query->where('providers.online_status', 'offline'),
            'interview_pending' => $query->where('providers.approval_status', 'needs_more_steps'),
            'approval_pending' => $query->where('providers.approval_status', 'pending'),
            default => $query,
        };
    }
}
