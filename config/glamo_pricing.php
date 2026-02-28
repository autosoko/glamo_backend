<?php

return [
    // Fuel price per liter (TZS)
    'fuel_price_per_liter' => (float) env('GLAMO_FUEL_PRICE_PER_LITER', 3200),

    // Approx fuel usage for a motorbike per km (liters/km)
    // Example: 0.03 = 1 liter per ~33km
    'motorbike_liters_per_km' => (float) env('GLAMO_MOTORBIKE_LITERS_PER_KM', 0.03),

    // Fixed travel fee added on top of fuel calculation (TZS)
    'travel_fixed_fee' => (float) env('GLAMO_TRAVEL_FIXED_FEE', 2000),

    // Round travel fee up to the nearest amount (e.g. 100 => 12,340 -> 12,400)
    'round_to' => (int) env('GLAMO_TRAVEL_ROUND_TO', 100),

    // Nearby providers radius in km (used for "near you" lists)
    'radius_km' => (int) env('GLAMO_PROVIDER_RADIUS_KM', 10),

    // Usage/operational fee percent (applied on service fee only)
    'usage_percent' => (float) env('GLAMO_USAGE_PERCENT', 5),

    // Provider commission percent (applied on order total)
    'commission_percent' => (float) env('GLAMO_COMMISSION_PERCENT', 10),

    // Provider auto-block threshold for debt (TZS)
    'provider_debt_block_threshold' => (float) env('GLAMO_PROVIDER_DEBT_BLOCK_THRESHOLD', 10000),

    // Cash checkout rounding unit (ceil). Example: 1000 => 111,090 -> 112,000
    'checkout_cash_round_to' => (int) env('GLAMO_CHECKOUT_CASH_ROUND_TO', 1000),

    // Booking window (local timezone): default 05:00 (saa 11 asubuhi) to 20:59 (saa 2 usiku)
    'booking_timezone' => (string) env('GLAMO_BOOKING_TIMEZONE', 'Africa/Dar_es_Salaam'),
    'booking_start_time' => (string) env('GLAMO_BOOKING_START_TIME', '05:00'),
    'booking_end_time' => (string) env('GLAMO_BOOKING_END_TIME', '20:59'),

    // Message shown when trying to book outside allowed hours.
    'booking_closed_message' => (string) env(
        'GLAMO_BOOKING_CLOSED_MESSAGE',
        'Fanya booking mapema kuanzia asubuhi saa 11 hadi usiku saa 2.'
    ),
];
