<?php

namespace App\Filament\Pages;

use App\Services\UserBroadcastService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Validation\Rule;
use UnitEnum;

class BroadcastCenter extends Page
{
    protected static ?string $title = 'Broadcast';

    protected static ?string $navigationLabel = 'Broadcast';

    protected static string | UnitEnum | null $navigationGroup = 'Mawasiliano';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-megaphone';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.broadcast-center';

    public string $segment = UserBroadcastService::SEGMENT_ALL;

    public string $subject = '';

    public string $message = '';

    public bool $sendSms = false;

    public array $segmentOptions = [];

    public array $segmentCounts = [];

    public array $globalCounts = [];

    public array $lastResult = [];

    public static function canAccess(): bool
    {
        return true;
    }

    public function mount(): void
    {
        $service = app(UserBroadcastService::class);

        $this->segmentOptions = $service->segmentOptions();
        if (! array_key_exists($this->segment, $this->segmentOptions)) {
            $this->segment = array_key_first($this->segmentOptions) ?: UserBroadcastService::SEGMENT_ALL;
        }

        $this->refreshCounts();
    }

    public function updatedSegment(): void
    {
        $this->refreshCounts();
    }

    public function dispatchBroadcast(): void
    {
        $this->validate([
            'segment' => ['required', Rule::in(array_keys($this->segmentOptions))],
            'subject' => ['required', 'string', 'max:140'],
            'message' => ['required', 'string', 'max:5000'],
            'sendSms' => ['boolean'],
        ]);

        $this->lastResult = app(UserBroadcastService::class)->sendToSegment(
            segment: $this->segment,
            subject: $this->subject,
            message: $this->message,
            sendSms: $this->sendSms,
            meta: [
                'title' => $this->subject,
                'button_text' => 'Fungua Glamo',
                'button_url' => rtrim((string) config('services.glamo.website_url', 'https://getglamo.com'), '/'),
                'source' => 'admin_broadcast',
            ]
        );

        Notification::make()
            ->title('Broadcast imetumwa')
            ->body('Email + in-app + push zimetumwa kwa walengwa. SMS imetumwa kulingana na chaguo lako.')
            ->success()
            ->send();

        $this->refreshCounts();
    }

    private function refreshCounts(): void
    {
        $service = app(UserBroadcastService::class);

        $this->globalCounts = $service->globalRegistrationCounts();
        $this->segmentCounts = $service->segmentRegistrationCounts($this->segment);
    }
}
