<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'pharmacy_id',
        'rating',
        'comment'
    ];

    protected $casts = [
        'rating' => 'integer'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }

    protected static function booted()
    {
        static::created(function ($review) {
            $review->pharmacy->updateRating();
        });

        static::updated(function ($review) {
            $review->pharmacy->updateRating();
        });

        static::deleted(function ($review) {
            $review->pharmacy->updateRating();
        });
    }
}
