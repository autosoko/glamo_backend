<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareerJobApplication extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'career_job_id',
        'user_id',
        'status',
        'cover_letter',
        'cv_file_path',
        'application_letter_file_path',
        'admin_note',
        'reviewed_by_admin_id',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function careerJob(): BelongsTo
    {
        return $this->belongsTo(CareerJob::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_admin_id');
    }

    protected static function booted(): void
    {
        static::saving(function (CareerJobApplication $application): void {
            $allowedStatuses = [
                self::STATUS_PENDING,
                self::STATUS_APPROVED,
                self::STATUS_REJECTED,
            ];

            if (! in_array((string) $application->status, $allowedStatuses, true)) {
                $application->status = self::STATUS_PENDING;
            }

            if ($application->status === self::STATUS_PENDING) {
                $application->reviewed_at = null;
                $application->reviewed_by_admin_id = null;
                return;
            }

            if ($application->isDirty('status') || $application->reviewed_at === null) {
                $application->reviewed_at = now();
                if (auth()->check()) {
                    $application->reviewed_by_admin_id = (int) auth()->id();
                }
            }
        });
    }
}
