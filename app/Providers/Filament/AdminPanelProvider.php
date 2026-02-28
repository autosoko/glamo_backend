<?php

namespace App\Providers\Filament;

use App\Filament\Pages\AppNotificationCenter;
use App\Filament\Pages\BroadcastCenter;
use App\Filament\Resources\Providers\ProviderResource;
use App\Filament\Resources\Users\UserResource;
use Filament\FontProviders\GoogleFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('Glamo')
            ->brandLogo(asset('images/logo.png'))
            ->favicon(asset('images/favicon-64.png'))
            ->font('Raleway', provider: GoogleFontProvider::class)
            ->colors([
                'primary' => Color::hex('#5A0E24'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
                BroadcastCenter::class,
                AppNotificationCenter::class,
            ])
            ->navigationItems([
                NavigationItem::make('Watumiaji wote wa mfumo')
                    ->group('Usimamizi')
                    ->icon('heroicon-o-users')
                    ->sort(1)
                    ->url(fn (): string => UserResource::getUrl('index'))
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.users.index')
                        && blank(request()->query('scope'))),

                NavigationItem::make('Wateja tu')
                    ->group('Usimamizi')
                    ->icon('heroicon-o-user')
                    ->sort(2)
                    ->url(fn (): string => UserResource::getUrl('index', [
                        'scope' => 'clients',
                    ]))
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.users.index')
                        && request()->query('scope') === 'clients'),

                NavigationItem::make('Watoa huduma wote')
                    ->group('Usimamizi')
                    ->icon('heroicon-o-user-group')
                    ->sort(3)
                    ->url(fn (): string => ProviderResource::getUrl('index'))
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.providers.index')
                        && blank(request()->query('scope'))),

                NavigationItem::make('Watoa huduma online')
                    ->group('Usimamizi')
                    ->icon('heroicon-o-signal')
                    ->sort(4)
                    ->url(fn (): string => ProviderResource::getUrl('index', [
                        'scope' => 'online',
                    ]))
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.providers.index')
                        && request()->query('scope') === 'online'),

                NavigationItem::make('Watoa huduma offline')
                    ->group('Usimamizi')
                    ->icon('heroicon-o-pause-circle')
                    ->sort(5)
                    ->url(fn (): string => ProviderResource::getUrl('index', [
                        'scope' => 'offline',
                    ]))
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.providers.index')
                        && request()->query('scope') === 'offline'),

                NavigationItem::make('Wanasubiri interview')
                    ->group('Usimamizi')
                    ->icon('heroicon-o-calendar-days')
                    ->sort(6)
                    ->url(fn (): string => ProviderResource::getUrl('index', [
                        'scope' => 'interview_pending',
                    ]))
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.providers.index')
                        && request()->query('scope') === 'interview_pending'),

                NavigationItem::make('Wanasubiri approval')
                    ->group('Usimamizi')
                    ->icon('heroicon-o-clock')
                    ->sort(7)
                    ->url(fn (): string => ProviderResource::getUrl('index', [
                        'scope' => 'approval_pending',
                    ]))
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.providers.index')
                        && request()->query('scope') === 'approval_pending'),

                NavigationItem::make('Broadcast')
                    ->group('Mawasiliano')
                    ->icon('heroicon-o-megaphone')
                    ->sort(5)
                    ->url(fn (): string => url('/admin/broadcast-center'))
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.pages.broadcast-center')),

                NavigationItem::make('App Notifications')
                    ->group('Mawasiliano')
                    ->icon('heroicon-o-bell-alert')
                    ->sort(6)
                    ->url(fn (): string => url('/admin/app-notification-center'))
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.pages.app-notification-center')),
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                \App\Filament\Widgets\AdminOverviewStats::class,
                \App\Filament\Widgets\TopProvidersByOrders::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
