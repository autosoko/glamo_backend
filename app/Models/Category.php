<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $guarded = [];

    public function services(){ return $this->hasMany(\App\Models\Service::class); }

}
