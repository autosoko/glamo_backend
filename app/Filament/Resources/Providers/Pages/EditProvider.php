<?php

namespace App\Filament\Resources\Providers\Pages;

use App\Filament\Resources\Providers\ProviderResource;
use App\Models\Provider;
use App\Services\ProfileImageProcessor;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProvider extends EditRecord
{
    protected static string $resource = ProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = ProviderResource::normalizeApprovalData($data);

        $autoRemove = (bool) ($data['profile_image_auto_remove_background'] ?? true);
        unset($data['profile_image_auto_remove_background']);

        $newPath = trim((string) ($data['profile_image_path'] ?? ''));
        $oldPath = trim((string) ($this->record->profile_image_path ?? ''));

        if ($newPath !== '' && $newPath !== $oldPath && $autoRemove) {
            $data['profile_image_path'] = app(ProfileImageProcessor::class)
                ->processStoredProfileImage($newPath, 'auto_remove', 'public');
        }

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var Provider $record */
        $record = $this->record;

        ProviderResource::ensureProviderUserRole($record);
        ProviderResource::syncLegacyProviderServiceTable($record);

        $watchedFields = [
            'approval_status',
            'online_status',
            'interview_status',
            'interview_scheduled_at',
            'interview_type',
            'interview_location',
            'approval_note',
            'rejection_reason',
            'offline_reason',
        ];

        $changedFields = array_values(array_intersect($watchedFields, array_keys($record->getChanges())));
        $approvalStatusChanged = in_array('approval_status', $changedFields, true);
        $isApprovedNow = (string) ($record->approval_status ?? '') === 'approved';

        if ($isApprovedNow && ! $approvalStatusChanged) {
            return;
        }

        if (! empty($changedFields)) {
            ProviderResource::notifyProviderStatusUpdate($record, [
                'source' => 'admin_edit_page',
                'changed_fields' => $changedFields,
            ]);
        }
    }
}
