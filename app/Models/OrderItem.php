<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $guarded = [];

    public function order()
    {
        return $this->belongsTo(\App\Models\Order::class);
    }

    public function service()
    {
        return $this->belongsTo(\App\Models\Service::class);
    }
}

