<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentService
{
    /**
     * Initier un paiement
     */
    public function initiatePayment(Order $order, string $method, array $extraData = []): Payment
    {
        // On crée l'enregistrement du paiement en attente
        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => $order->total_price,
            'payment_method' => $method,
            'status' => 'pending',
            'transaction_id' => 'TRX-' . strtoupper(Str::random(10)),
            'metadata' => $extraData
        ]);

        // Selon la méthode, on appellerait l'API correspondante ici
        // Simulation d'appel API...
        
        return $payment;
    }

    /**
     * Traiter le succès d'un paiement (Webhook ou retour direct)
     */
    public function handlePaymentSuccess(Payment $payment, ?string $externalId = null)
    {
        $payment->update([
            'status' => 'completed',
            'transaction_id' => $externalId ?? $payment->transaction_id,
        ]);

        // Mettre à jour le statut de la commande
        $order = $payment->order;
        $order->update(['status' => 'confirmed']);

        Log::info("Payment successful for order {$order->id}, method: {$payment->payment_method}");
        
        return $payment;
    }

    /**
     * Traiter l'échec d'un paiement
     */
    public function handlePaymentFailure(Payment $payment, string $reason)
    {
        $payment->update([
            'status' => 'failed',
            'metadata' => array_merge($payment->metadata ?? [], ['failure_reason' => $reason])
        ]);

        Log::error("Payment failed for order {$payment->order_id}: {$reason}");
        
        return $payment;
    }
}
