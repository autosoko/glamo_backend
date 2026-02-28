<?php

namespace App\Filament\Resources\Services\Pages;

use App\Filament\Resources\Services\ServiceResource;
use App\Models\Category;
use App\Models\Service;
use App\Services\UserBroadcastService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class CreateService extends CreateRecord
{
    protected static string $resource = ServiceResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['slug'] = Str::slug((string) ($data['slug'] ?? $data['name'] ?? 'service'));
        $data['category'] = Category::query()
            ->whereKey($data['category_id'] ?? null)
            ->value('slug') ?? 'other';

        $data['base_price'] = (float) ($data['base_price'] ?? 0);
        $data['materials_price'] = (float) ($data['materials_price'] ?? 0);
        $data['usage_percent'] = (float) ($data['usage_percent'] ?? 5);
        $data['duration_minutes'] = (int) ($data['duration_minutes'] ?? 60);

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;

        if (! $record instanceof Service) {
            return;
        }

        try {
            app(UserBroadcastService::class)->notifyClientsForNewService($record);
        } catch (\Throwable $e) {
            Log::warning('Service created broadcast failed', [
                'service_id' => $record->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
