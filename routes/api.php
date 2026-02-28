<?php

use App\Http\Controllers\Api\AuthOtpController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AuthController as V1AuthController;
use App\Http\Controllers\Api\V1\CareerController as V1CareerController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\ClientController as V1ClientController;
use App\Http\Controllers\Api\V1\CommunityController;
use App\Http\Controllers\Api\V1\OrderChatController;
use App\Http\Controllers\Api\V1\ProviderController as V1ProviderController;
use App\Http\Controllers\Api\V1\ProviderOnboardingController as V1ProviderOnboardingController;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Legacy API (kept for backward compatibility)
|--------------------------------------------------------------------------
*/
Route::post('/auth/request-otp', [AuthOtpController::class, 'requestOtp']);
Route::post('/auth/verify-otp', [AuthOtpController::class, 'verifyOtp']);

/*
|--------------------------------------------------------------------------
| Versioned API for FlutterFlow (v1)
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function () {
    // Public
    Route::get('/meta', [CatalogController::class, 'meta']);
    Route::get('/app-links', [CatalogController::class, 'appDownloadLinks']);
    Route::get('/categories', [CatalogController::class, 'categories']);
    Route::get('/services', [CatalogController::class, 'services']);
    Route::get('/services/{service}', [CatalogController::class, 'serviceShow'])->whereNumber('service');
    Route::get('/services/{service}/providers', [CatalogController::class, 'providers'])->whereNumber('service');
    Route::post('/services/{service}/quote', [CatalogController::class, 'quote'])->whereNumber('service');
    Route::post('/coupons/preview', [CatalogController::class, 'couponPreview']);

    Route::get('/careers', [V1CareerController::class, 'index']);
    Route::get('/careers/{careerJob:slug}', [V1CareerController::class, 'show']);
    Route::post('/ambassador/apply', [CommunityController::class, 'ambassadorApply']);

    // Auth
    Route::post('/auth/request-otp', [V1AuthController::class, 'requestOtp']);
    Route::post('/auth/verify-otp', [V1AuthController::class, 'verifyOtp']);
    Route::post('/auth/login', [V1AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [V1AuthController::class, 'me']);
        Route::post('/auth/logout', [V1AuthController::class, 'logout']);

        Route::match(['put', 'patch', 'post'], '/profile-image', [AccountController::class, 'uploadProfileImage']);
        Route::match(['put', 'patch', 'post'], '/me/profile-image', [AccountController::class, 'uploadProfileImage']);
        Route::match(['put', 'patch', 'post'], '/me/profile', [AccountController::class, 'updateProfile']);
        Route::post('/me/phone-change/request-otp', [AccountController::class, 'requestPhoneChangeOtp']);
        Route::post('/me/phone-change/verify-otp', [AccountController::class, 'verifyPhoneChangeOtp']);
        Route::put('/me/location', [AccountController::class, 'updateLocation']);
        Route::get('/me/notifications', [AccountController::class, 'notifications']);
        Route::post('/me/notifications/{id}/read', [AccountController::class, 'markNotificationRead']);
        Route::post('/me/push-tokens', [AccountController::class, 'registerPushToken']);
        Route::delete('/me/push-tokens', [AccountController::class, 'revokePushToken']);
        Route::get('/orders/{order}/messages', [OrderChatController::class, 'index'])->whereNumber('order');
        Route::post('/orders/{order}/messages', [OrderChatController::class, 'store'])->whereNumber('order');

        // Client
        Route::get('/client/orders', [V1ClientController::class, 'orders']);
        Route::get('/client/orders/active', [V1ClientController::class, 'activeOrder']);
        Route::post('/client/orders', [V1ClientController::class, 'createOrder']);
        Route::get('/client/orders/{order}', [V1ClientController::class, 'show'])->whereNumber('order');
        Route::post('/client/orders/{order}/payment/mode', [V1ClientController::class, 'setPaymentMode'])->whereNumber('order');
        Route::post('/client/orders/{order}/payment/start', [V1ClientController::class, 'startPayment'])->whereNumber('order');
        Route::post('/client/orders/{order}/payment/refresh', [V1ClientController::class, 'refreshPayment'])->whereNumber('order');
        Route::post('/client/orders/{order}/cancel', [V1ClientController::class, 'cancelOrder'])->whereNumber('order');
        Route::post('/client/orders/{order}/confirm-arrival', [V1ClientController::class, 'confirmArrival'])->whereNumber('order');
        Route::post('/client/orders/{order}/review', [V1ClientController::class, 'review'])->whereNumber('order');
        Route::put('/client/orders/{order}/services', [V1ClientController::class, 'updateOrderServices'])->whereNumber('order');

        // Provider onboarding
        Route::get('/provider/onboarding/status', [V1ProviderOnboardingController::class, 'status']);
        Route::post('/provider/onboarding/submit', [V1ProviderOnboardingController::class, 'submit']);

        // Provider
        Route::get('/provider/dashboard', [V1ProviderController::class, 'dashboard']);
        Route::post('/provider/availability', [V1ProviderController::class, 'updateAvailability']);
        Route::post('/provider/location', [V1ProviderController::class, 'updateLocation']);
        Route::put('/provider/profile', [V1ProviderController::class, 'updateProfile']);
        Route::get('/provider/services/catalog', [V1ProviderController::class, 'servicesCatalog']);
        Route::put('/provider/services', [V1ProviderController::class, 'updateServices']);
        Route::post('/provider/withdraw', [V1ProviderController::class, 'withdraw']);
        Route::post('/provider/debt/pay', [V1ProviderController::class, 'payDebt']);
        Route::get('/provider/debt/payments', [V1ProviderController::class, 'debtPayments']);
        Route::get('/provider/debt/payments/{providerPayment}', [V1ProviderController::class, 'showDebtPayment'])->whereNumber('providerPayment');
        Route::post('/provider/debt/payments/{providerPayment}/refresh', [V1ProviderController::class, 'refreshDebtPayment'])->whereNumber('providerPayment');
        Route::get('/provider/nearby-customers', [V1ProviderController::class, 'nearbyCustomers']);
        Route::get('/provider/reviews', [V1ProviderController::class, 'reviews']);
        Route::get('/provider/reviews/summary', [V1ProviderController::class, 'reviewsSummary']);
        Route::get('/provider/client-feedback', [V1ProviderController::class, 'clientFeedback']);

        Route::get('/provider/orders', [V1ProviderController::class, 'orders']);
        Route::get('/provider/orders/{order}', [V1ProviderController::class, 'showOrder'])->whereNumber('order');
        Route::post('/provider/orders/{order}/accept', [V1ProviderController::class, 'acceptOrder'])->whereNumber('order');
        Route::post('/provider/orders/{order}/reject', [V1ProviderController::class, 'rejectOrder'])->whereNumber('order');
        Route::post('/provider/orders/{order}/on-the-way', [V1ProviderController::class, 'markOnTheWay'])->whereNumber('order');
        Route::post('/provider/orders/{order}/arrived', [V1ProviderController::class, 'markArrived'])->whereNumber('order');
        Route::post('/provider/orders/{order}/suspend', [V1ProviderController::class, 'suspendOrder'])->whereNumber('order');
        Route::post('/provider/orders/{order}/complete', [V1ProviderController::class, 'completeOrder'])->whereNumber('order');
        Route::post('/provider/orders/{order}/client-feedback', [V1ProviderController::class, 'submitClientFeedback'])->whereNumber('order');

        // Team & careers (auth required actions)
        Route::get('/about/team-status', [CommunityController::class, 'teamStatus']);
        Route::post('/about/join-team', [CommunityController::class, 'joinTeam']);
        Route::post('/careers/{careerJob:slug}/apply', [V1CareerController::class, 'apply']);
    });
});

/*
|--------------------------------------------------------------------------
| Existing non-versioned API routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me/notifications', function (Request $request) {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthorized.');

        $notifications = DatabaseNotification::query()
            ->where('notifiable_type', get_class($user))
            ->where('notifiable_id', (int) $user->id)
            ->latest('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'notifications' => $notifications,
        ]);
    });

    Route::post('/me/notifications/{id}/read', function (Request $request, string $id) {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthorized.');

        $n = DatabaseNotification::query()
            ->where('notifiable_type', get_class($user))
            ->where('notifiable_id', (int) $user->id)
            ->where('id', $id)
            ->firstOrFail();
        $n->markAsRead();

        return response()->json(['message' => 'read']);
    });

    // Client
    Route::post('/client/orders', [OrderController::class, 'create']);
    Route::get('/client/orders', [OrderController::class, 'clientIndex']);
    Route::post('/client/orders/{order}/confirm-arrival', [OrderController::class, 'clientConfirmArrival']);
    Route::post('/client/orders/{order}/review', [OrderController::class, 'review']);

    // Provider
    Route::get('/provider/orders', [OrderController::class, 'providerIndex']);
    Route::post('/provider/orders/{order}/accept', [OrderController::class, 'accept']);
    Route::post('/provider/orders/{order}/reject', [OrderController::class, 'reject']);
    Route::post('/provider/orders/{order}/on-the-way', [OrderController::class, 'markOnTheWay']);
    Route::post('/provider/orders/{order}/arrived', [OrderController::class, 'markArrived']);
    Route::post('/provider/orders/{order}/complete', [OrderController::class, 'complete']);

    // Shared order tracking/location
    Route::get('/orders/{order}/track', [OrderController::class, 'track']);
    Route::post('/orders/{order}/location', [OrderController::class, 'pushLocation']);
});
