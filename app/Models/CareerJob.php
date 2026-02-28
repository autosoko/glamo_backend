<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CareerJob extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_CLOSED = 'closed';

    public const TYPE_FULL_TIME = 'full_time';
    public const TYPE_PART_TIME = 'part_time';
    public const TYPE_CONTRACT = 'contract';
    public const TYPE_INTERNSHIP = 'internship';

    protected $fillable = [
        'title',
        'slug',
        'employment_type',
        'location',
        'positions_count',
        'status',
        'is_active',
        'application_deadline',
        'summary',
        'description',
        'requirements',
        'created_by_admin_id',
        'published_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'application_deadline' => 'date',
        'published_at' => 'datetime',
    ];

    public function applications(): HasMany
    {
        return $this->hasMany(CareerJobApplication::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_admin_id');
    }

    public function isOpenForApplications(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ((string) $this->status !== self::STATUS_PUBLISHED) {
            return false;
        }

        if (! $this->application_deadline) {
            return true;
        }

        return $this->application_deadline->isToday()
            || $this->application_deadline->isFuture();
    }

    protected static function booted(): void
    {
        static::creating(function (CareerJob $job): void {
            if (! $job->created_by_admin_id && auth()->check()) {
                $job->created_by_admin_id = (int) auth()->id();
            }
        });

        static::saving(function (CareerJob $job): void {
            $allowedStatuses = [
                self::STATUS_DRAFT,
                self::STATUS_PUBLISHED,
                self::STATUS_CLOSED,
            ];

            if (! in_array((string) $job->status, $allowedStatuses, true)) {
                $job->status = self::STATUS_DRAFT;
            }

            $allowedTypes = [
                self::TYPE_FULL_TIME,
                self::TYPE_PART_TIME,
                self::TYPE_CONTRACT,
                self::TYPE_INTERNSHIP,
            ];

            if (! in_array((string) $job->employment_type, $allowedTypes, true)) {
                $job->employment_type = self::TYPE_FULL_TIME;
            }

            $slug = trim((string) ($job->slug ?? ''));
            $baseSlug = $slug === ''
                ? Str::slug((string) $job->title)
                : Str::slug($slug);

            if ($baseSlug === '') {
                $baseSlug = 'kazi';
            }

            $candidate = $baseSlug;
            $suffix = 2;
            while (static::query()
                ->where('slug', $candidate)
                ->when($job->exists, fn ($query) => $query->where('id', '!=', $job->id))
                ->exists()) {
                $candidate = $baseSlug . '-' . $suffix;
                $suffix++;
            }
            $job->slug = $candidate;

            if ($job->status === self::STATUS_PUBLISHED && $job->published_at === null) {
                $job->published_at = now();
            }
        });
    }
}
