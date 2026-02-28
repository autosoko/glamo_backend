<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Laravel\Sanctum\HasApiTokens;
use App\Support\PublicFileUrl;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'role',
        'password',
        'otp_verified_at',
        'last_lat',
        'last_lng',
        'last_location_at',
        'profile_image_path',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'otp_verified_at' => 'datetime',
        'last_location_at' => 'datetime',
    ];

    public function provider()
    {
        return $this->hasOne(\App\Models\Provider::class);
    }

    public function hasProviderProfile(): bool
    {
        if ($this->relationLoaded('provider')) {
            return $this->getRelation('provider') !== null;
        }

        return $this->provider()->exists();
    }

    public function isApprovedActiveProvider(): bool
    {
        $provider = $this->relationLoaded('provider')
            ? $this->getRelation('provider')
            : $this->provider()->first();

        if (!$provider) {
            return false;
        }

        return (bool) ($provider->is_active ?? false)
            && (string) ($provider->approval_status ?? '') === 'approved';
    }

    public function clientOrders(): HasMany
    {
        return $this->hasMany(\App\Models\Order::class, 'client_id');
    }

    public function staff(): HasOne
    {
        return $this->hasOne(\App\Models\Staff::class);
    }

    public function careerJobApplications(): HasMany
    {
        return $this->hasMany(\App\Models\CareerJobApplication::class);
    }

    public function devicePushTokens(): HasMany
    {
        return $this->hasMany(\App\Models\DevicePushToken::class);
    }

    public function getProfileImageUrlAttribute(): string
    {
        return (string) PublicFileUrl::existingUrl(
            (string) ($this->profile_image_path ?? ''),
            asset('images/placeholder.svg'),
        );
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin'
            && (string) ($this->role ?? '') === 'admin';
    }
}
