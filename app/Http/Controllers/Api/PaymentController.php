<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Initier un paiement pour une commande
     */
    public function pay(Request $request, Order $order): JsonResponse
    {
        // Vérifier que la commande appartient à l'utilisateur
        if ($order->user_id !== Auth::id()) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        // Vérifier si la commande n'est pas déjà payée ou annulée
        if ($order->status !== 'pending') {
            return response()->json(['message' => 'Cette commande ne peut pas être payée dans son état actuel.'], 400);
        }

        $request->validate([
            'payment_method' => 'required|in:orange_money,wave,card,cash',
        ]);

        $payment = $this->paymentService->initiatePayment($order, $request->payment_method);

        // Simulation de succès immédiat pour le développement
        // Sauf pour 'card' où on pourrait simuler un échec ou un lien Stripe
        if (in_array($request->payment_method, ['orange_money', 'wave', 'cash'])) {
            $this->paymentService->handlePaymentSuccess($payment);
            
            return response()->json([
                'message' => 'Paiement effectué avec succès (Simulation).',
                'payment' => $payment->load('order'),
                'status' => 'completed'
            ]);
        }

        return response()->json([
            'message' => 'Paiement initié.',
            'payment' => $payment,
            'status' => 'pending'
        ]);
    }
}
