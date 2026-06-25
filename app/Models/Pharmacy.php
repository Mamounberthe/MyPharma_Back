<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pharmacy extends Model
{
    use HasFactory;

    protected $fillable = [
        'is_partner',
        'name',
        'address',
        'latitude',
        'longitude',
        'phone',
        'image_url',
        'rating',
        'delivery_available',
        'google_place_id',
        'osm_id',
        'is_on_call',
        'opening_hours',
        'status'
    ];

    protected $casts = [
        'is_partner' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'rating' => 'decimal:2',
        'delivery_available' => 'boolean',
        'is_on_call' => 'boolean',
        'opening_hours' => 'array',
    ];

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'stocks')
            ->withPivot(['quantity', 'price'])
            ->withTimestamps();
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function updateRating()
    {
        $this->rating = $this->reviews()->avg('rating') ?? 0;
        $this->save();
    }

    public function getImageUrlAttribute($value)
    {
        if (empty($value)) {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        return asset('storage/' . $value);
    }
}
