<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Providers\ProviderResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Order;
use App\Models\Provider;
use App\Models\ProviderPayment;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminOverviewStats extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $onlineProviders = Provider::query()
            ->where('online_status', 'online')
            ->count();

        $offlineProviders = Provider::query()
            ->where('online_status', 'offline')
            ->count();

        $pendingApprovals = Provider::query()
            ->where('approval_status', 'pending')
            ->count();

        $clientsCount = User::query()
            ->where('role', 'client')
            ->count();

        $totalOrders = Order::query()->count();

        $completedOrders = Order::query()
            ->where('status', 'completed')
            ->count();

        $platformRevenue = (float) Order::query()
            ->where('status', 'completed')
            ->sum(DB::raw('price_total * 0.10'));

        $providerPaidRevenue = 0.0;
        if (Schema::hasTable('provider_payments')) {
            $providerPaidRevenue = (float) ProviderPayment::query()
                ->where('status', 'paid')
                ->sum('amount');
        }

        return [
            Stat::make('Watoa huduma pending', number_format($pendingApprovals))
                ->description('Wanahitaji uhakiki wa admin')
                ->color('warning')
                ->icon('heroicon-o-clock')
                ->extraAttributes(['class' => 'cursor-pointer'])
                ->url(ProviderResource::getUrl('index', [
                    'tableFilters' => [
                        'approval_status' => ['value' => 'pending'],
                    ],
                ])),

            Stat::make('Watoa huduma online', number_format($onlineProviders))
                ->description('Offline: ' . number_format($offlineProviders))
                ->color('success')
                ->icon('heroicon-o-signal')
                ->extraAttributes(['class' => 'cursor-pointer'])
                ->url(ProviderResource::getUrl('index', [
                    'tableFilters' => [
                        'online_status' => ['value' => 'online'],
                    ],
                ])),

            Stat::make('Wateja waliosajiliwa', number_format($clientsCount))
                ->description('Role: client')
                ->color('info')
                ->icon('heroicon-o-users')
                ->extraAttributes(['class' => 'cursor-pointer'])
                ->url(UserResource::getUrl('index', [
                    'tableFilters' => [
                        'role' => ['value' => 'client'],
                    ],
                ])),

            Stat::make('Order zote', number_format($totalOrders))
                ->description('Zilizokamilika: ' . number_format($completedOrders))
                ->color('gray')
                ->icon('heroicon-o-shopping-bag')
                ->extraAttributes(['class' => 'cursor-pointer'])
                ->url(OrderResource::getUrl('index')),

            Stat::make('Mapato ya platform (10%)', 'TZS ' . number_format($platformRevenue, 0))
                ->description('Kutoka kwenye order zilizokamilika')
                ->color('primary')
                ->icon('heroicon-o-banknotes')
                ->extraAttributes(['class' => 'cursor-pointer'])
                ->url(OrderResource::getUrl('index', [
                    'tableFilters' => [
                        'status' => ['value' => 'completed'],
                    ],
                ])),

            Stat::make('Mapato yaliyolipwa', 'TZS ' . number_format($providerPaidRevenue, 0))
                ->description('Malipo ya deni kutoka kwa watoa huduma')
                ->color('success')
                ->icon('heroicon-o-wallet'),
        ];
    }
}
