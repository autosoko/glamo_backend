<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\ProviderOnboardingController;
use App\Http\Controllers\Web\ProviderDashboardController;
use App\Http\Controllers\Web\ServiceController;
use App\Http\Controllers\Web\AmbassadorController;
use App\Http\Controllers\Web\AboutController;
use App\Http\Controllers\Web\CareerController;
use App\Http\Controllers\Web\SnippeRedirectController;
use App\Http\Controllers\Web\SnippeWebhookController;
use App\Http\Controllers\Public\StorageFileController;

/*
|--------------------------------------------------------------------------
| PUBLIC
|--------------------------------------------------------------------------
| Homepage inapatikana kwa kila mtu (hata bila login).
| Inaweza kupokea ?lat=...&lng=...&r=...
*/
Route::match(['get', 'head'], '/media/{path}', [StorageFileController::class, 'show'])
    ->where('path', '.*')
    ->name('media.public');

Route::get('/', [HomeController::class, 'index'])->name('landing');
Route::get('/huduma-zote', [HomeController::class, 'services'])->name('services.index');
Route::get('/huduma/{category:slug}/{service:slug}', [ServiceController::class, 'show'])
    ->scopeBindings()
    ->name('services.show');

// Snippe webhooks (payment + payout events)
Route::post('/webhooks/snippe', [SnippeWebhookController::class, 'handle'])
    ->name('webhooks.snippe');
Route::get('/malipo/snippe/done', [SnippeRedirectController::class, 'done'])
    ->name('snippe.done');
Route::get('/malipo/snippe/cancel', [SnippeRedirectController::class, 'cancel'])
    ->name('snippe.cancel');

// Checkout (client confirms payment + coupon before creating order)
Route::post('/huduma/{category:slug}/{service:slug}/checkout/{provider}', [ServiceController::class, 'startCheckout'])
    ->middleware('auth')
    ->whereNumber('provider')
    ->scopeBindings()
    ->name('services.checkout.start');
Route::get('/huduma/{category:slug}/{service:slug}/checkout', [ServiceController::class, 'checkout'])
    ->middleware('auth')
    ->scopeBindings()
    ->name('services.checkout');
Route::post('/huduma/{category:slug}/{service:slug}/checkout/apply-coupon', [ServiceController::class, 'applyCheckoutCoupon'])
    ->middleware('auth')
    ->scopeBindings()
    ->name('services.checkout.coupon');
Route::post('/huduma/{category:slug}/{service:slug}/checkout/confirm', [ServiceController::class, 'confirmCheckout'])
    ->middleware('auth')
    ->scopeBindings()
    ->name('services.checkout.confirm');
Route::get('/huduma/{category:slug}/{service:slug}/checkout/confirm', [ServiceController::class, 'checkoutConfirmFallback'])
    ->middleware('auth')
    ->scopeBindings()
    ->name('services.checkout.confirm.fallback');
Route::get('/huduma/{category:slug}/{service:slug}/lipa', [ServiceController::class, 'pay'])
    ->middleware('auth')
    ->scopeBindings()
    ->name('services.pay');
Route::post('/huduma/{category:slug}/{service:slug}/lipa/confirm', [ServiceController::class, 'confirmPay'])
    ->middleware('auth')
    ->scopeBindings()
    ->name('services.pay.confirm');
Route::get('/oda/{order}', [ServiceController::class, 'orderConfirm'])->middleware('auth')->name('orders.show');
Route::get('/oda/{order}/tracking', [ServiceController::class, 'orderTracking'])
    ->middleware('auth')
    ->name('orders.tracking');
Route::post('/oda/{order}/payment/start', [ServiceController::class, 'startOrderPayment'])
    ->middleware('auth')
    ->name('orders.payment.start');
Route::post('/oda/{order}/payment/mode', [ServiceController::class, 'setOrderPaymentMode'])
    ->middleware('auth')
    ->name('orders.payment.mode');
Route::post('/oda/{order}/payment/refresh', [ServiceController::class, 'refreshPayment'])->middleware('auth')->name('orders.payment.refresh');
Route::post('/oda/{order}/cancel', [ServiceController::class, 'cancelOrder'])->middleware('auth')->name('orders.cancel');
Route::post('/oda/{order}/arrival/confirm', [ServiceController::class, 'confirmArrival'])->middleware('auth')->name('orders.arrival.confirm');
Route::post('/oda/{order}/review', [ServiceController::class, 'storeReview'])->middleware('auth')->name('orders.review.store');
Route::post('/oda/{order}/services', [ServiceController::class, 'updateOrderServices'])->middleware('auth')->name('orders.services.update');

// Provider dashboard (wallet + debt + earnings)
Route::get('/mtoa-huduma/kamilisha-taarifa', [ProviderOnboardingController::class, 'show'])
    ->middleware('auth')
    ->name('provider.onboarding');
Route::post('/mtoa-huduma/kamilisha-taarifa', [ProviderOnboardingController::class, 'submit'])
    ->middleware('auth')
    ->name('provider.onboarding.submit');
Route::post('/mtoa-huduma/kamilisha-taarifa/check-nickname', [ProviderOnboardingController::class, 'checkBusinessNickname'])
    ->middleware('auth')
    ->name('provider.onboarding.nickname-check');

Route::get('/mtoa-huduma/dashibodi', [ProviderDashboardController::class, 'index'])
    ->middleware('auth')
    ->name('provider.dashboard');
Route::post('/mtoa-huduma/availability', [ProviderDashboardController::class, 'updateAvailability'])
    ->middleware('auth')
    ->name('provider.availability.update');
Route::post('/mtoa-huduma/withdraw', [ProviderDashboardController::class, 'withdraw'])
    ->middleware('auth')
    ->name('provider.withdraw');
Route::post('/mtoa-huduma/deni/lipa', [ProviderDashboardController::class, 'payDebt'])
    ->middleware('auth')
    ->name('provider.debt.pay');
Route::post('/mtoa-huduma/profile', [ProviderDashboardController::class, 'updateProfile'])
    ->middleware('auth')
    ->name('provider.profile.update');
Route::post('/mtoa-huduma/order/{order}/approve', [ProviderDashboardController::class, 'approveOrder'])
    ->middleware('auth')
    ->name('provider.orders.approve');
Route::post('/mtoa-huduma/order/{order}/reject', [ProviderDashboardController::class, 'rejectOrder'])
    ->middleware('auth')
    ->name('provider.orders.reject');
Route::post('/mtoa-huduma/order/{order}/complete', [ProviderDashboardController::class, 'completeOrder'])
    ->middleware('auth')
    ->name('provider.orders.complete');
Route::post('/mtoa-huduma/order/{order}/suspend', [ProviderDashboardController::class, 'suspendOrder'])
    ->middleware('auth')
    ->name('provider.orders.suspend');
Route::post('/mtoa-huduma/services', [ProviderDashboardController::class, 'updateServices'])
    ->middleware('auth')
    ->name('provider.services.update');
Route::get('/mtoa-huduma/huduma/{service}', [ProviderDashboardController::class, 'showService'])
    ->middleware('auth')
    ->name('provider.services.show');
Route::post('/mtoa-huduma/huduma/{service}/ongeza', [ProviderDashboardController::class, 'addService'])
    ->middleware('auth')
    ->name('provider.services.add');

/*
|--------------------------------------------------------------------------
| SUPPORT
|--------------------------------------------------------------------------
| Support page (FAQ + guides)
*/
Route::view('/support', 'public.support')->name('support');
Route::get('/about-us', [AboutController::class, 'index'])->name('about');
Route::post('/about-us/join-team', [AboutController::class, 'joinTeam'])
    ->middleware('auth')
    ->name('about.join-team');
Route::get('/careers', [CareerController::class, 'index'])->name('careers');
Route::post('/careers/{careerJob:slug}/apply', [CareerController::class, 'apply'])
    ->middleware('auth')
    ->name('careers.apply');
Route::view('/safari', 'public.safari')->name('safari');
Route::view('/salon-zetu', 'public.salon')->name('salons');
Route::view('/malipo', 'public.malipo')->name('payments');
Route::view('/miji', 'public.miji')->name('cities');
Route::view('/terms', 'public.terms')->name('terms');
Route::view('/privacy', 'public.privacy')->name('privacy');
Route::view('/cookies', 'public.cookies')->name('cookies');
Route::view('/security', 'public.security')->name('security');
Route::get('/ambasador', [AmbassadorController::class, 'create'])->name('ambassador.create');
Route::post('/ambasador', [AmbassadorController::class, 'store'])->name('ambassador.store');

/*
|--------------------------------------------------------------------------
| AUTH
|--------------------------------------------------------------------------
| Login: email+password au phone+password
| Register / Forgot: OTP -> set password
| Tunapitisha ?redirect=/... ili baada ya login arudi alipotoka.
*/
Route::get('/ingia', [AuthController::class, 'showLogin'])->name('login');
Route::post('/ingia', [AuthController::class, 'login'])->name('login.attempt');

Route::get('/jisajili', [AuthController::class, 'showRegister'])->name('register');
Route::post('/jisajili', [AuthController::class, 'sendRegisterOtp'])->name('register.otp');

Route::get('/umesahau-nenosiri', [AuthController::class, 'showForgot'])->name('password.request');
Route::post('/umesahau-nenosiri', [AuthController::class, 'sendResetOtp'])->name('password.otp');

Route::get('/thibitisha', [AuthController::class, 'showVerify'])->name('otp.verify');
Route::post('/thibitisha', [AuthController::class, 'verifyOtp'])->name('otp.verify.submit');

Route::get('/weka-nenosiri', [AuthController::class, 'showSetPassword'])->name('password.set');
Route::post('/weka-nenosiri/simu/otp', [AuthController::class, 'sendSetPasswordPhoneOtp'])->name('password.phone.otp.send');
Route::post('/weka-nenosiri/simu/thibitisha', [AuthController::class, 'verifySetPasswordPhoneOtp'])->name('password.phone.otp.verify');
Route::post('/weka-nenosiri', [AuthController::class, 'storePassword'])->name('password.store');

Route::post('/ondoka', [AuthController::class, 'logout'])->name('logout');

/*
|--------------------------------------------------------------------------
| LOCATION
|--------------------------------------------------------------------------
| Hifadhi location kwenye session (optional) — inaweza kusaidia hata bila query params.
*/
Route::post('/location/set', [HomeController::class, 'setLocation'])->name('location.set');

/*
|--------------------------------------------------------------------------
| AUTHENTICATED AREA (optional)
|--------------------------------------------------------------------------
| Hapa tunaweka "home" route ili kuondoa error ya Route [home] not defined.
| Unaweza kuitumia kama dashboard au tu redirect alias.
*/
Route::middleware('auth')->group(function () {
    Route::get('/home', function () {
        $user = auth()->user();

        if ($user && method_exists($user, 'isApprovedActiveProvider') && $user->isApprovedActiveProvider()) {
            if ((string) ($user->role ?? '') !== 'provider') {
                $user->forceFill(['role' => 'provider'])->save();
            }
            return redirect()->route('provider.dashboard');
        }

        if ((string) ($user->role ?? '') === 'provider') {
            return redirect()->route('provider.dashboard');
        }

        return redirect()->route('landing');
    })->name('home');
});
