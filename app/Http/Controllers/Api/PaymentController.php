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

        // Bloquer le paiement tant qu'une ordonnance requise n'a pas été validée
        // par un pharmacien (sécurité sanitaire et conformité).
        if ($order->isBlockedByPrescription()) {
            return response()->json([
                'message' => $this->prescriptionBlockMessage($order),
                'status'  => 'prescription_required',
                'prescription_status' => $order->prescription_status,
            ], 422);
        }

        $request->validate([
            'payment_method' => 'required|in:orange_money,wave,card,cash',
        ]);

        $method = $request->payment_method;

        // --- Paiement à la livraison -------------------------------------
        // Chemin réel et fonctionnel : la commande est confirmée pour
        // préparation, l'encaissement se fait à la livraison. Le paiement
        // reste "pending" jusqu'à réception effective des espèces.
        if ($method === 'cash') {
            $payment = $this->paymentService->initiatePayment($order, $method);
            $this->paymentService->confirmCashOnDelivery($payment);

            return response()->json([
                'message' => 'Commande confirmée. Vous réglerez à la livraison.',
                'payment' => $payment->fresh()->load('order'),
                'status'  => 'confirmed',
            ]);
        }

        // --- Paiement en ligne -------------------------------------------
        // Désactivé tant que l'intégration réelle (PSP + webhook signé) n'est
        // pas en place : on NE confirme JAMAIS une commande sans encaissement.
        if (! config('payments.online_enabled')) {
            return response()->json([
                'message' => "Le paiement en ligne n'est pas encore disponible. "
                    . "Veuillez choisir le paiement à la livraison.",
                'status'  => 'unavailable',
            ], 422);
        }

        // Intégration réelle (à venir) : initier la transaction chez le
        // prestataire et attendre la confirmation par webhook. La commande
        // reste "pending" en attendant le callback vérifié.
        $payment = $this->paymentService->initiatePayment($order, $method);

        return response()->json([
            'message' => 'Paiement initié. En attente de confirmation du prestataire.',
            'payment' => $payment,
            'status'  => 'pending',
        ], 202);
    }

    /**
     * Message adapté à l'état de l'ordonnance bloquant le paiement.
     */
    private function prescriptionBlockMessage(Order $order): string
    {
        return match ($order->prescription_status) {
            Order::RX_REJECTED => "Votre ordonnance a été refusée par le pharmacien. "
                . "Veuillez en téléverser une nouvelle avant de régler la commande.",
            Order::RX_PENDING => $order->prescription_url
                ? "Votre ordonnance est en cours de validation par un pharmacien. "
                    . "Le paiement sera possible une fois celle-ci approuvée."
                : "Cette commande contient un médicament sur ordonnance. "
                    . "Veuillez téléverser votre ordonnance, elle sera validée par un pharmacien.",
            default => "La validation de l'ordonnance par un pharmacien est requise avant le paiement.",
        };
    }
}
