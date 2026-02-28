<?php

namespace App\Filament\Resources\Services\Pages;

use App\Filament\Resources\Services\ServiceResource;
use App\Models\Category;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditService extends EditRecord
{
    protected static string $resource = ServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['slug'] = Str::slug((string) ($data['slug'] ?? $data['name'] ?? 'service'));
        $data['category'] = Category::query()
            ->whereKey($data['category_id'] ?? null)
            ->value('slug') ?? (string) ($data['category'] ?? 'other');

        $data['base_price'] = (float) ($data['base_price'] ?? 0);
        $data['materials_price'] = (float) ($data['materials_price'] ?? 0);
        $data['usage_percent'] = (float) ($data['usage_percent'] ?? 5);
        $data['duration_minutes'] = (int) ($data['duration_minutes'] ?? 60);

        return $data;
    }
}
