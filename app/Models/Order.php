<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    /** États de validation de l'ordonnance par un pharmacien. */
    public const RX_NOT_REQUIRED = 'not_required';
    public const RX_PENDING      = 'pending_review';
    public const RX_APPROVED     = 'approved';
    public const RX_REJECTED     = 'rejected';

    protected $fillable = [
        'user_id',
        'pharmacy_id',
        'driver_id',
        'total_price',
        'status',
        'delivery_address',
        'prescription_url',
        'prescription_status',
        'prescription_reviewed_by',
        'prescription_reviewed_at',
        'prescription_rejection_reason',
        'delivered_at',
    ];

    protected $casts = [
        'total_price' => 'decimal:2',
        'delivered_at' => 'datetime',
        'prescription_reviewed_at' => 'datetime',
    ];

    /**
     * Valeurs par défaut au niveau du modèle : garantit que
     * prescription_status est toujours défini en mémoire (et pas seulement via
     * le défaut SQL), quelle que soit la voie de création.
     */
    protected $attributes = [
        'prescription_status' => self::RX_NOT_REQUIRED,
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

    public function prescriptionReviewer()
    {
        return $this->belongsTo(User::class, 'prescription_reviewed_by');
    }

    /**
     * La commande contient-elle au moins un produit sur ordonnance ?
     */
    public function requiresPrescription(): bool
    {
        return $this->prescription_status !== self::RX_NOT_REQUIRED;
    }

    /**
     * L'ordonnance a-t-elle été validée par un pharmacien ?
     */
    public function prescriptionApproved(): bool
    {
        return $this->prescription_status === self::RX_APPROVED;
    }

    /**
     * La commande est-elle bloquée en attente de validation d'ordonnance ?
     * (Contient un produit sur ordonnance et celle-ci n'est pas encore approuvée.)
     */
    public function isBlockedByPrescription(): bool
    {
        return $this->requiresPrescription() && ! $this->prescriptionApproved();
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
