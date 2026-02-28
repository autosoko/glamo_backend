<?php

namespace App\Models;

use App\Support\PublicFileUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Service extends Model
{
    protected $guarded = [];
    protected $casts = [
        'is_active' => 'boolean',
        'hair_wash_enabled' => 'boolean',
        'hair_wash_default_selected' => 'boolean',
        'hair_wash_price' => 'decimal:2',
    ];

    public function serviceCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function category(): BelongsTo
    {
        return $this->serviceCategory();
    }

    public function media(): HasMany
    {
        return $this->hasMany(ServiceMedia::class)
            ->whereNotNull('file_path')
            ->where('file_path', 'not like', 'livewire-file:%')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function providers(): BelongsToMany
    {
        return $this->belongsToMany(Provider::class, 'provider_services')
            ->withPivot(['price_override', 'is_active'])
            ->withTimestamps();
    }

    public function getPrimaryImageUrlAttribute(): string
    {
        return $this->imageUrls(1)[0];
    }

    public function getGalleryImageUrlsAttribute(): array
    {
        return $this->imageUrls(12);
    }

    public function imageUrls(int $limit = 12): array
    {
        $limit = max(1, $limit);
        $urls = [];
        $mediaItems = $this->relationLoaded('media') ? $this->getRelation('media') : $this->media()->get();

        foreach ($mediaItems as $media) {
            $url = $this->mediaPathToUrl((string) ($media->file_path ?? ''));

            if ($url === null) {
                continue;
            }

            $urls[] = $url;

            if (count($urls) >= $limit) {
                break;
            }
        }

        $urls = array_values(array_unique($urls));

        if ($urls !== []) {
            return $urls;
        }

        $fallback = $this->fallbackImageUrl();

        if ($fallback !== null) {
            return [$fallback];
        }

        return [asset('images/placeholder.svg')];
    }

    private function mediaPathToUrl(string $path): ?string
    {
        return PublicFileUrl::existingUrl($path);
    }

    private function fallbackImageUrl(): ?string
    {
        $imageUrl = trim((string) ($this->image_url ?? ''));

        if ($imageUrl === '' || Str::startsWith($imageUrl, 'livewire-file:')) {
            return null;
        }

        if (Str::startsWith($imageUrl, ['http://', 'https://'])) {
            return $imageUrl;
        }

        $path = PublicFileUrl::normalizePath($imageUrl);
        if ($path !== '' && Storage::disk('public')->exists($path)) {
            return PublicFileUrl::url($path);
        }

        $publicAssetPath = ltrim(str_replace('\\', '/', $imageUrl), '/');
        if (
            $publicAssetPath !== ''
            && ! Str::startsWith($publicAssetPath, ['storage/', 'public/'])
        ) {
            return asset($publicAssetPath);
        }

        return null;
    }
}
