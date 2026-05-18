<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'pharmacy_id',
        'driver_id',
        'total_price',
        'status',
        'delivery_address',
        'delivered_at'
    ];

    protected $casts = [
        'total_price' => 'decimal:2',
        'delivered_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function trackings()
    {
        return $this->hasMany(OrderTracking::class);
    }

    public function latestTracking()
    {
        return $this->hasOne(OrderTracking::class)->latestOfMany();
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'order_items')
            ->withPivot(['quantity', 'price'])
            ->withTimestamps();
    }

    public function confirm()
    {
        $this->status = 'confirmed';
        $this->save();
    }

    public function startDelivery()
    {
        $this->status = 'delivering';
        $this->save();
    }

    public function deliver()
    {
        $this->status = 'delivered';
        $this->delivered_at = now();
        $this->save();
    }

    public function cancel()
    {
        $this->status = 'cancelled';
        $this->save();
    }
}
