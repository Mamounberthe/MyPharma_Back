<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderTracking extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'driver_id',
        'latitude',
        'longitude',
        'heading',
        'speed'
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'heading' => 'integer',
        'speed' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
