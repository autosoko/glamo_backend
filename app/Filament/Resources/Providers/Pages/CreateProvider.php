<?php

namespace App\Filament\Resources\Providers\Pages;

use App\Filament\Resources\Providers\ProviderResource;
use App\Models\Provider;
use App\Services\ProfileImageProcessor;
use Filament\Resources\Pages\CreateRecord;

class CreateProvider extends CreateRecord
{
    protected static string $resource = ProviderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = ProviderResource::normalizeApprovalData($data);

        $autoRemove = (bool) ($data['profile_image_auto_remove_background'] ?? true);
        unset($data['profile_image_auto_remove_background']);

        $profileImagePath = trim((string) ($data['profile_image_path'] ?? ''));
        if ($profileImagePath !== '' && $autoRemove) {
            $data['profile_image_path'] = app(ProfileImageProcessor::class)
                ->processStoredProfileImage($profileImagePath, 'auto_remove', 'public');
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Provider $record */
        $record = $this->record;

        ProviderResource::ensureProviderUserRole($record);
        ProviderResource::syncLegacyProviderServiceTable($record);
    }
}
