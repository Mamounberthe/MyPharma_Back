<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Mail\OrderConfirmedMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

        // L'appel réel au prestataire (Orange Money / Wave / carte) doit être
        // implémenté ici, suivi d'une confirmation par webhook signé avant
        // d'appeler handlePaymentSuccess(). Aucune confirmation simulée.

        return $payment;
    }

    /**
     * Confirmer une commande en paiement à la livraison.
     *
     * La commande passe en "confirmed" pour préparation, mais le paiement
     * reste "pending" : les espèces seront encaissées à la livraison.
     */
    public function confirmCashOnDelivery(Payment $payment): Payment
    {
        $order = $payment->order;
        $order->update(['status' => 'confirmed']);

        Log::info("Cash-on-delivery order confirmed: {$order->id}");

        $this->sendOrderConfirmation($order);

        return $payment;
    }

    /**
     * Traiter le succès d'un paiement EN LIGNE (appelé uniquement par un
     * webhook prestataire vérifié — jamais directement depuis une requête).
     */
    public function handlePaymentSuccess(Payment $payment, ?string $externalId = null)
    {
        // Idempotence : un webhook prestataire peut être rejoué. Si le paiement
        // est déjà confirmé, on ne re-confirme pas la commande ni ne renvoie
        // l'email de confirmation.
        if ($payment->status === 'completed') {
            return $payment;
        }

        $payment->update([
            'status' => 'completed',
            'transaction_id' => $externalId ?? $payment->transaction_id,
        ]);

        // Mettre à jour le statut de la commande
        $order = $payment->order;
        $order->update(['status' => 'confirmed']);

        Log::info("Payment successful for order {$order->id}, method: {$payment->payment_method}");

        $this->sendOrderConfirmation($order);

        return $payment;
    }

    /**
     * Envoyer l'email de confirmation de commande (best-effort).
     */
    private function sendOrderConfirmation(Order $order): void
    {
        try {
            Mail::to($order->user->email)
                ->send(new OrderConfirmedMail($order->load(['user', 'pharmacy', 'orderItems.product'])));
        } catch (\Exception $e) {
            Log::error("Order confirmation email failed for order {$order->id}: " . $e->getMessage());
        }
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
