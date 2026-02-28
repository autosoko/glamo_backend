<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Staff extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $table = 'staff';

    protected $fillable = [
        'user_id',
        'status',
        'original_role',
        'approved_by_admin_id',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_admin_id');
    }

    protected static function booted(): void
    {
        static::saving(function (Staff $staff): void {
            $allowed = [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED];
            if (! in_array($staff->status, $allowed, true)) {
                $staff->status = self::STATUS_PENDING;
            }

            $staff->loadMissing('user');

            if (($staff->original_role === null || trim((string) $staff->original_role) === '') && $staff->user) {
                $staff->original_role = (string) ($staff->user->role ?? 'client');
            }

            if ($staff->status !== self::STATUS_APPROVED) {
                $staff->approved_at = null;
                $staff->approved_by_admin_id = null;
                return;
            }

            if ($staff->approved_at === null) {
                $staff->approved_at = now();
            }

            if (auth()->check()) {
                $staff->approved_by_admin_id = (int) auth()->id();
            }
        });

        static::saved(function (Staff $staff): void {
            $staff->loadMissing('user');
            $user = $staff->user;

            if (! $user) {
                return;
            }

            if ($staff->status === self::STATUS_APPROVED) {
                if ((string) ($user->role ?? '') !== 'staff') {
                    $user->forceFill(['role' => 'staff'])->save();
                }

                return;
            }

            $original = trim((string) ($staff->original_role ?? ''));
            if ($original !== '' && (string) ($user->role ?? '') === 'staff') {
                $user->forceFill(['role' => $original])->save();
            }
        });
    }
}
