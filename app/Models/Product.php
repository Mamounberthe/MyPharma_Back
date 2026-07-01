<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'sku',
        'price',
        'description',
        'image_url',
        'category_id',
        'requires_prescription'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'requires_prescription' => 'boolean',
    ];

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

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }

    public function pharmacies()
    {
        return $this->belongsToMany(Pharmacy::class, 'stocks')
            ->withPivot(['quantity', 'price'])
            ->withTimestamps();
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function getAvailablePharmacies()
    {
        return $this->pharmacies()->wherePivot('quantity', '>', 0);
    }

    /**
     * Recherche par nom, insensible à la casse ET aux accents.
     *
     * Ex. « paracetamol », « PARACÉTAMOL » et « Paracétamol » trouvent tous
     * « Paracétamol ». Sur PostgreSQL on utilise unaccent()+ILIKE (si
     * l'extension unaccent est disponible), sinon ILIKE (casse seule). Sur
     * SQLite/MySQL on retombe sur LOWER()+LIKE.
     */
    public function scopeSearch($query, ?string $term)
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        $like   = '%' . $term . '%';
        $driver = $query->getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            return self::supportsUnaccent()
                ? $query->whereRaw('unaccent(name) ILIKE unaccent(?)', [$like])
                : $query->whereRaw('name ILIKE ?', [$like]);
        }

        // SQLite (tests/local) / MySQL : LOWER couvre la casse.
        return $query->whereRaw('LOWER(name) LIKE LOWER(?)', [$like]);
    }

    /**
     * L'extension PostgreSQL "unaccent" est-elle installée ? (résultat mémorisé
     * pour éviter de re-tester à chaque requête). Permet une dégradation propre
     * — recherche insensible à la casse seule — si l'extension manque.
     */
    protected static function supportsUnaccent(): bool
    {
        static $supported = null;

        if ($supported === null) {
            try {
                $supported = DB::selectOne(
                    "SELECT 1 FROM pg_extension WHERE extname = 'unaccent'"
                ) !== null;
            } catch (\Throwable $e) {
                $supported = false;
            }
        }

        return $supported;
    }
}
