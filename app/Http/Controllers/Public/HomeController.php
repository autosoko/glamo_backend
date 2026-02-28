<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $lat = $request->query('lat');
        $lng = $request->query('lng');
        $radiusKm = (float) $request->query('r', 10);

        // Manual location filter (optional)
        $city = $request->query('city'); // e.g. "Dar es Salaam"
        $region = $request->query('region'); // e.g. "Dar es Salaam"

        $servicesQuery = DB::table('services')
            ->where('services.is_active', 1)
            ->select('services.id', 'services.name', 'services.base_price', 'services.image_url');

        // TOTAL providers count per service (always available)
        $servicesQuery->addSelect([
            'total_providers' => DB::table('provider_service')
                ->join('providers', 'providers.id', '=', 'provider_service.provider_id')
                ->whereColumn('provider_service.service_id', 'services.id')
                ->where('providers.is_active', 1)
                ->selectRaw('COUNT(DISTINCT providers.id)')
        ]);

        $hasLocation = ($lat !== null && $lng !== null);

        // NEARBY providers count if GPS provided
        if ($hasLocation) {
            $lat = (float) $lat;
            $lng = (float) $lng;

            $distanceSql = "(6371 * acos(
                cos(radians(?)) * cos(radians(providers.lat)) *
                cos(radians(providers.lng) - radians(?)) +
                sin(radians(?)) * sin(radians(providers.lat))
            ))";

            $servicesQuery->addSelect([
                'nearby_providers' => DB::table('provider_service')
                    ->join('providers', 'providers.id', '=', 'provider_service.provider_id')
                    ->whereColumn('provider_service.service_id', 'services.id')
                    ->where('providers.is_active', 1)
                    ->whereNotNull('providers.lat')
                    ->whereNotNull('providers.lng')
                    ->whereRaw("$distanceSql <= ?", [$lat, $lng, $lat, $radiusKm])
                    ->selectRaw('COUNT(DISTINCT providers.id)')
            ]);
        } else {
            // If no GPS, try filtering by region/city if you store that in providers table
            // Example columns: providers.region, providers.city
            if ($region || $city) {
                $servicesQuery->addSelect([
                    'nearby_providers' => DB::table('provider_service')
                        ->join('providers', 'providers.id', '=', 'provider_service.provider_id')
                        ->whereColumn('provider_service.service_id', 'services.id')
                        ->where('providers.is_active', 1)
                        ->when($region, fn($q) => $q->where('providers.region', $region))
                        ->when($city, fn($q) => $q->where('providers.city', $city))
                        ->selectRaw('COUNT(DISTINCT providers.id)')
                ]);
            } else {
                $servicesQuery->addSelect(DB::raw('NULL as nearby_providers'));
            }
        }

        // Random / Featured feel
        $services = $servicesQuery
            ->inRandomOrder()
            ->limit(24)
            ->get();

        // Regions list (simple). You can pull from DB later.
        $regions = [
            'Dar es Salaam','Arusha','Mwanza','Dodoma','Mbeya','Morogoro','Tanga','Kilimanjaro','Zanzibar'
        ];

        return view('public.home', [
            'services' => $services,
            'hasLocation' => $hasLocation,
            'radiusKm' => $radiusKm,
            'regions' => $regions,
            'selectedRegion' => $region,
            'selectedCity' => $city,
        ]);
    }
}
