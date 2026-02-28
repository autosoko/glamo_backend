<?php

namespace App\Models;

use App\Support\PublicFileUrl;
use Illuminate\Database\Eloquent\Model;

class Provider extends Model
{
    protected $guarded = [];
    protected $casts = [
        'is_active' => 'boolean',
        'selected_skills' => 'array',
        'qualification_docs' => 'array',
        'demo_interview_acknowledged' => 'boolean',
        'interview_required' => 'boolean',
        'approved_at' => 'datetime',
        'date_of_birth' => 'date',
        'last_location_at' => 'datetime',
        'interview_scheduled_at' => 'datetime',
        'onboarding_submitted_at' => 'datetime',
        'onboarding_completed_at' => 'datetime',
    ];

public function user(){ return $this->belongsTo(\App\Models\User::class); }

public function getDisplayNameAttribute(): string
{
    $nickname = trim((string) ($this->business_nickname ?? ''));
    if ($nickname !== '') {
        return $nickname;
    }

    $legal = trim(implode(' ', array_filter([
        trim((string) ($this->first_name ?? '')),
        trim((string) ($this->middle_name ?? '')),
        trim((string) ($this->last_name ?? '')),
    ])));
    if ($legal !== '') {
        return $legal;
    }

    $fallback = '';
    if ($this->relationLoaded('user')) {
        $fallback = trim((string) data_get($this->getRelation('user'), 'name'));
    } elseif ($this->user_id) {
        $fallback = trim((string) data_get($this->user()->select('name')->first(), 'name'));
    }

    return $fallback !== '' ? $fallback : 'Mtoa huduma';
}

public function getProfileImageUrlAttribute(): string
{
    $directImagePath = trim((string) ($this->profile_image_path ?? ''));
    $directImageUrl = PublicFileUrl::existingUrl($directImagePath);
    if ($directImageUrl !== null) {
        return $directImageUrl;
    }

    $portfolio = $this->relationLoaded('portfolio')
        ? collect($this->getRelation('portfolio'))
        : $this->portfolio()->where('type', 'image')->orderBy('id')->get();

    $firstImage = $portfolio->first(function ($item): bool {
        return (string) data_get($item, 'type') === 'image'
            && trim((string) data_get($item, 'file_path')) !== '';
    });

    if ($firstImage) {
        $path = trim((string) data_get($firstImage, 'file_path'));
        $url = PublicFileUrl::existingUrl($path);
        if ($url !== null) {
            return $url;
        }
    }

    return asset('images/placeholder.svg');
}

public function portfolio(){ return $this->hasMany(\App\Models\ProviderPortfolio::class); }

public function services()
{
    return $this->belongsToMany(\App\Models\Service::class, 'provider_services')
        ->withPivot(['price_override','is_active'])
        ->withTimestamps();
}

public function orders(){ return $this->hasMany(\App\Models\Order::class); }

public function ledgers(){ return $this->hasMany(\App\Models\ProviderLedger::class); }

public function payments(){ return $this->hasMany(\App\Models\ProviderPayment::class); }

public function walletLedgers(){ return $this->hasMany(\App\Models\ProviderWalletLedger::class); }

public function withdrawals(){ return $this->hasMany(\App\Models\ProviderWithdrawal::class); }

public function reviews(){ return $this->hasMany(\App\Models\Review::class); }

}
