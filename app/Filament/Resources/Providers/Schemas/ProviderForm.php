<?php

namespace App\Filament\Resources\Providers\Schemas;

use App\Models\Category;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ProviderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Akaunti')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Mtumiaji')
                            ->relationship(
                                name: 'user',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query) => $query->orderByDesc('id'),
                            )
                            ->getOptionLabelFromRecordUsing(function ($record): string {
                                $name = trim((string) data_get($record, 'name'));
                                if ($name !== '') {
                                    return $name;
                                }

                                $phone = trim((string) data_get($record, 'phone'));
                                if ($phone !== '') {
                                    return $phone;
                                }

                                $email = trim((string) data_get($record, 'email'));
                                if ($email !== '') {
                                    return $email;
                                }

                                return 'Mtumiaji #' . (string) data_get($record, 'id', '-');
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabledOn('edit'),

                        Forms\Components\TextInput::make('phone_public')
                            ->label('Simu ya mawasiliano')
                            ->tel()
                            ->maxLength(20),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Akaunti active')
                            ->default(true),
                    ])
                    ->columns(3),

                Section::make('Taarifa binafsi')
                    ->schema([
                        Forms\Components\TextInput::make('first_name')
                            ->label('Jina la kwanza')
                            ->maxLength(80),

                        Forms\Components\TextInput::make('middle_name')
                            ->label('Jina la kati')
                            ->maxLength(80),

                        Forms\Components\TextInput::make('last_name')
                            ->label('Jina la mwisho')
                            ->maxLength(80),

                        Forms\Components\TextInput::make('business_nickname')
                            ->label('Nickname ya biashara')
                            ->maxLength(120),

                        Forms\Components\FileUpload::make('profile_image_path')
                            ->label('Picha ya profile')
                            ->disk('public')
                            ->directory('providers/profile')
                            ->image()
                            ->imageEditor()
                            ->imageEditorAspectRatios(['1:1', '3:4'])
                            ->imageEditorViewportWidth('420')
                            ->imageEditorViewportHeight('420')
                            ->imageEditorEmptyFillColor('#e8c8d7')
                            ->downloadable()
                            ->openable()
                            ->helperText('Mapendekezo: 1:1 (1080x1080+), uso uonekane vizuri. Ukichagua option ya auto-remove, background huwekwa pink kama template ya profile.'),

                        Forms\Components\Toggle::make('profile_image_auto_remove_background')
                            ->label('Toa background kiotomatiki (inapendekezwa)')
                            ->default(true)
                            ->helperText('Zima ikiwa unataka picha ibaki original background.'),

                        Forms\Components\TextInput::make('alt_phone')
                            ->label('Simu mbadala')
                            ->tel()
                            ->maxLength(20),

                        Forms\Components\Select::make('gender')
                            ->label('Jinsia')
                            ->options([
                                'male' => 'Mwanaume',
                                'female' => 'Mwanamke',
                                'other' => 'Nyingine',
                            ]),

                        Forms\Components\DatePicker::make('date_of_birth')
                            ->label('Tarehe ya kuzaliwa')
                            ->native(false),

                        Forms\Components\TextInput::make('years_experience')
                            ->label('Miaka ya uzoefu')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(60),

                        Forms\Components\TextInput::make('emergency_contact_name')
                            ->label('Mtu wa dharura')
                            ->maxLength(120),

                        Forms\Components\TextInput::make('emergency_contact_phone')
                            ->label('Simu ya dharura')
                            ->tel()
                            ->maxLength(20),
                    ])
                    ->columns(3),

                Section::make('Uthibitisho wa nyaraka')
                    ->schema([
                        Forms\Components\Select::make('id_type')
                            ->label('Aina ya kitambulisho')
                            ->options([
                                'nida' => 'NIDA',
                                'voter' => 'Mpiga kura',
                                'passport' => 'Passport',
                                'driver_license' => 'Leseni ya udereva',
                            ]),

                        Forms\Components\TextInput::make('id_number')
                            ->label('Namba ya kitambulisho')
                            ->maxLength(120),

                        Forms\Components\FileUpload::make('id_document_front_path')
                            ->label('Kitambulisho - Front')
                            ->disk('public')
                            ->directory('providers/ids/front')
                            ->acceptedFileTypes(['image/*', 'application/pdf'])
                            ->downloadable()
                            ->openable()
                            ->visible(fn ($get): bool => in_array((string) $get('id_type'), ['nida', 'voter', 'driver_license'], true) || filled($get('id_document_front_path'))),

                        Forms\Components\FileUpload::make('id_document_back_path')
                            ->label('Kitambulisho - Back')
                            ->disk('public')
                            ->directory('providers/ids/back')
                            ->acceptedFileTypes(['image/*', 'application/pdf'])
                            ->downloadable()
                            ->openable()
                            ->visible(fn ($get): bool => in_array((string) $get('id_type'), ['nida', 'voter', 'driver_license'], true) || filled($get('id_document_back_path'))),

                        Forms\Components\FileUpload::make('id_document_path')
                            ->label('Picha/PDF ya kitambulisho')
                            ->disk('public')
                            ->directory('providers/ids')
                            ->acceptedFileTypes(['image/*', 'application/pdf'])
                            ->downloadable()
                            ->openable()
                            ->visible(fn ($get): bool => !in_array((string) $get('id_type'), ['nida', 'voter', 'driver_license'], true) || filled($get('id_document_path'))),

                        Forms\Components\CheckboxList::make('selected_skills')
                            ->label('Category za ujuzi')
                            ->options(fn (): array => Category::query()
                                ->where('is_active', 1)
                                ->orderBy('sort_order')
                                ->pluck('name', 'slug')
                                ->toArray())
                            ->columns(3),

                        Forms\Components\Select::make('education_status')
                            ->label('Amesomea taaluma?')
                            ->options([
                                'trained' => 'Ndio',
                                'not_trained' => 'Hapana',
                            ]),

                        Forms\Components\FileUpload::make('certificate_path')
                            ->label('Cheti cha mafunzo')
                            ->disk('public')
                            ->directory('providers/certificates')
                            ->acceptedFileTypes(['image/*', 'application/pdf'])
                            ->downloadable()
                            ->openable()
                            ->visible(fn ($get): bool => (string) $get('education_status') === 'trained'),

                        Forms\Components\FileUpload::make('qualification_docs')
                            ->label('Nyaraka za ziada')
                            ->disk('public')
                            ->directory('providers/qualifications')
                            ->acceptedFileTypes(['image/*', 'application/pdf'])
                            ->multiple()
                            ->downloadable()
                            ->openable()
                            ->reorderable(),

                        Forms\Components\Textarea::make('references_text')
                            ->label('Referee / maelezo ya rejea')
                            ->rows(3)
                            ->visible(fn ($get): bool => (string) $get('education_status') === 'not_trained'),

                        Forms\Components\Toggle::make('demo_interview_acknowledged')
                            ->label('Ameelewa atafanyiwa interview ya demo')
                            ->default(false),
                    ])
                    ->columns(3),

                Section::make('Uhakiki wa admin')
                    ->description('Chagua status ya mwisho ya mtoa huduma. Approved itawekwa online moja kwa moja.')
                    ->schema([
                        Forms\Components\Placeholder::make('admin_review_guide')
                            ->label('Mwongozo')
                            ->content('Approved => profile online na ready. Partial approved => interview ni lazima. Rejected => profile hubaki offline.'),

                        Forms\Components\Select::make('approval_status')
                            ->label('Hali ya uhakiki')
                            ->required()
                            ->live()
                            ->options([
                                'pending' => 'Inasubiri uhakiki',
                                'approved' => 'Imeidhinishwa',
                                'needs_more_steps' => 'Imeidhinishwa kwa hatua (Partial approved)',
                                'rejected' => 'Imekataliwa',
                            ])
                            ->helperText('Ukichagua "Imeidhinishwa", mfumo utaweka profile online na tayari kupokea oda.'),

                        Forms\Components\Select::make('online_status')
                            ->label('Hali ya online')
                            ->required()
                            ->options([
                                'online' => 'Online',
                                'offline' => 'Offline',
                                'busy' => 'Busy',
                                'blocked_debt' => 'Imefungwa kwa deni',
                            ])
                            ->helperText('Hali hii inasawazishwa kiotomatiki kulingana na approval status.'),

                        Forms\Components\TextInput::make('offline_reason')
                            ->label('Sababu ya offline')
                            ->maxLength(255),

                        Forms\Components\Toggle::make('interview_required')
                            ->label('Interview inahitajika')
                            ->default(false)
                            ->visible(fn ($get): bool => (string) $get('approval_status') === 'needs_more_steps'),

                        Forms\Components\Select::make('interview_status')
                            ->label('Hali ya interview')
                            ->live()
                            ->options([
                                'pending_schedule' => 'Inasubiri ratiba',
                                'scheduled' => 'Imepangwa',
                                'completed' => 'Imekamilika',
                                'passed' => 'Amefaulu',
                                'failed' => 'Hajafaulu',
                            ])
                            ->visible(fn ($get): bool => (string) $get('approval_status') === 'needs_more_steps')
                            ->required(fn ($get): bool => (string) $get('approval_status') === 'needs_more_steps'),

                        Forms\Components\DateTimePicker::make('interview_scheduled_at')
                            ->label('Tarehe ya interview')
                            ->native(false)
                            ->visible(fn ($get): bool => (string) $get('approval_status') === 'needs_more_steps')
                            ->required(fn ($get): bool => (string) $get('approval_status') === 'needs_more_steps'),

                        Forms\Components\TextInput::make('interview_type')
                            ->label('Aina ya interview')
                            ->maxLength(120)
                            ->default('Demo ya kazi')
                            ->visible(fn ($get): bool => (string) $get('approval_status') === 'needs_more_steps')
                            ->required(fn ($get): bool => (string) $get('approval_status') === 'needs_more_steps'),

                        Forms\Components\TextInput::make('interview_location')
                            ->label('Mahali pa interview')
                            ->maxLength(180)
                            ->visible(fn ($get): bool => (string) $get('approval_status') === 'needs_more_steps')
                            ->required(fn ($get): bool => (string) $get('approval_status') === 'needs_more_steps'),

                        Forms\Components\Textarea::make('approval_note')
                            ->label('Maelezo ya admin')
                            ->rows(3)
                            ->helperText('Kwa "Partial approved", andika maelezo mafupi yanayoeleweka kwa mtoa huduma.'),

                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Sababu ya kukataa')
                            ->rows(3)
                            ->visible(fn ($get): bool => (string) $get('approval_status') === 'rejected'),
                    ])
                    ->columns(2),

                Section::make('Anwani')
                    ->schema([
                        Forms\Components\TextInput::make('region')
                            ->label('Mkoa')
                            ->maxLength(80),

                        Forms\Components\TextInput::make('district')
                            ->label('Wilaya')
                            ->maxLength(80),

                        Forms\Components\TextInput::make('ward')
                            ->label('Kata')
                            ->maxLength(80),

                        Forms\Components\TextInput::make('village')
                            ->label('Kijiji/Mtaa')
                            ->maxLength(120),

                        Forms\Components\TextInput::make('house_number')
                            ->label('Namba ya nyumba')
                            ->maxLength(80),

                        Forms\Components\TextInput::make('current_lat')
                            ->label('Latitude')
                            ->numeric(),

                        Forms\Components\TextInput::make('current_lng')
                            ->label('Longitude')
                            ->numeric(),
                    ])
                    ->columns(3),

                Section::make('Fedha')
                    ->schema([
                        Forms\Components\TextInput::make('wallet_balance')
                            ->label('Wallet balance (TZS)')
                            ->numeric()
                            ->disabled(),

                        Forms\Components\TextInput::make('debt_balance')
                            ->label('Debt balance (TZS)')
                            ->numeric()
                            ->disabled(),
                    ])
                    ->columns(2),

                Section::make('Huduma anazoruhusiwa kufanya')
                    ->schema([
                        Forms\Components\Select::make('services')
                            ->label('Chagua huduma')
                            ->relationship(
                                name: 'services',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query) => $query
                                    ->where('services.is_active', 1)
                                    ->orderBy('name'),
                            )
                            ->getOptionLabelFromRecordUsing(function ($record): string {
                                $name = trim((string) data_get($record, 'name'));
                                if ($name !== '') {
                                    return $name;
                                }

                                return 'Huduma #' . (string) data_get($record, 'id', '-');
                            })
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->helperText('Admin anaweza kuongeza au kuondoa huduma alizochagua mtoa huduma.'),
                    ]),
            ]);
    }
}
