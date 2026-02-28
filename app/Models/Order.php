<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $guarded = [];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'accepted_at' => 'datetime',
        'on_the_way_at' => 'datetime',
        'provider_arrived_at' => 'datetime',
        'client_arrival_confirmed_at' => 'datetime',
        'completed_at' => 'datetime',
        'suspended_at' => 'datetime',
        'suspended_until_at' => 'datetime',
        'resumed_at' => 'datetime',
        'schedule_notified_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(\App\Models\User::class, 'client_id');
    }

    public function provider()
    {
        return $this->belongsTo(\App\Models\Provider::class);
    }

    public function service()
    {
        return $this->belongsTo(\App\Models\Service::class);
    }

    public function items()
    {
        return $this->hasMany(\App\Models\OrderItem::class);
    }

    public function review()
    {
        return $this->hasOne(\App\Models\Review::class);
    }
}
