<?php

namespace App\Filament\Resources\Providers;

use App\Filament\Resources\Providers\Pages\CreateProvider;
use App\Filament\Resources\Providers\Pages\EditProvider;
use App\Filament\Resources\Providers\Pages\ListProviders;
use App\Filament\Resources\Providers\Schemas\ProviderForm;
use App\Filament\Resources\Providers\Tables\ProvidersTable;
use App\Models\Provider;
use App\Services\ProviderStatusNotifier;
use App\Services\UserBroadcastService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use UnitEnum;

class ProviderResource extends Resource
{
    protected static ?string $model = Provider::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static UnitEnum | string | null $navigationGroup = 'Usimamizi';

    protected static ?string $navigationLabel = 'Watoa huduma';

    protected static ?string $modelLabel = 'Mtoa huduma';

    protected static ?string $pluralModelLabel = 'Watoa huduma';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return ProviderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProvidersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProviders::route('/'),
            'create' => CreateProvider::route('/create'),
            'edit' => EditProvider::route('/{record}/edit'),
        ];
    }

    public static function ensureProviderUserRole(Provider $provider): void
    {
        $user = $provider->user;

        if (! $user) {
            return;
        }

        if ((string) ($user->role ?? '') !== 'provider') {
            $user->forceFill(['role' => 'provider'])->save();
        }
    }

    public static function normalizeApprovalData(array $data): array
    {
        $approvalStatus = (string) ($data['approval_status'] ?? '');

        if ($approvalStatus === 'approved') {
            $data['approved_at'] = $data['approved_at'] ?? now();
            $data['online_status'] = 'online';
            $data['offline_reason'] = null;
            $data['rejection_reason'] = null;
            $data['interview_required'] = false;
            $data['interview_status'] = null;
            $data['interview_scheduled_at'] = null;
            $data['interview_type'] = null;
            $data['interview_location'] = null;

            $note = trim((string) ($data['approval_note'] ?? ''));
            if ($note === '') {
                $data['approval_note'] = 'Umeidhinishwa kikamilifu. Profile yako iko tayari kupokea oda.';
            }
        } elseif ($approvalStatus === 'needs_more_steps') {
            $data['approved_at'] = null;
            $data['online_status'] = 'offline';
            $data['interview_required'] = true;
            $data['rejection_reason'] = null;

            $interviewStatus = trim((string) ($data['interview_status'] ?? ''));
            if ($interviewStatus === '') {
                $data['interview_status'] = 'scheduled';
            }
        } elseif ($approvalStatus === 'rejected') {
            $data['approved_at'] = null;
            $data['online_status'] = 'offline';
            $data['interview_required'] = false;
        } elseif ($approvalStatus === 'pending') {
            $data['approved_at'] = null;
            $data['online_status'] = 'offline';
        }

        return $data;
    }

    public static function syncLegacyProviderServiceTable(Provider $provider): void
    {
        if (! SchemaFacade::hasTable('provider_service')) {
            return;
        }

        $serviceIds = $provider->services()
            ->pluck('services.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        DB::table('provider_service')
            ->where('provider_id', $provider->id)
            ->delete();

        if (empty($serviceIds)) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($serviceIds as $serviceId) {
            $rows[] = [
                'provider_id' => $provider->id,
                'service_id' => $serviceId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('provider_service')->insert($rows);
    }

    public static function notifyProviderStatusUpdate(Provider $provider, array $context = []): void
    {
        try {
            app(ProviderStatusNotifier::class)->notify($provider, $context);
        } catch (\Throwable $e) {
            Log::warning('Provider status notify exception', [
                'provider_id' => $provider->id,
                'error' => $e->getMessage(),
            ]);
        }

        $changedFields = (array) ($context['changed_fields'] ?? []);
        $approvalStatusChanged = in_array('approval_status', $changedFields, true);

        if (! $approvalStatusChanged || (string) ($provider->approval_status ?? '') !== 'approved') {
            return;
        }

        try {
            app(UserBroadcastService::class)->notifyNearbyClientsForApprovedProvider($provider, 5);
        } catch (\Throwable $e) {
            Log::warning('Nearby clients notify on provider approved failed', [
                'provider_id' => $provider->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
