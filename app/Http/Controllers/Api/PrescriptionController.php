<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PrescriptionController extends Controller
{
    public function __construct(private NotificationService $notificationService) {}

    /**
     * Client : téléverser (ou remplacer) l'ordonnance d'une commande.
     * Si la commande nécessite une ordonnance, elle passe en attente de
     * validation par un pharmacien.
     */
    public function upload(Request $request, Order $order): JsonResponse
    {
        if ($request->user()->id !== $order->user_id) {
            abort(403);
        }

        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $disk = config('filesystems.uploads_disk');
        // On stocke le CHEMIN (et non une URL publique) : l'ordonnance est une
        // donnée de santé, servie ensuite via une URL signée temporaire.
        $path = $request->file('file')->store("prescriptions/{$order->id}", $disk);

        $attributes = ['prescription_url' => $path];

        // Un nouvel envoi relance le cycle de validation (utile après un refus).
        if ($order->requiresPrescription()) {
            $attributes['prescription_status']           = Order::RX_PENDING;
            $attributes['prescription_rejection_reason'] = null;
            $attributes['prescription_reviewed_by']      = null;
            $attributes['prescription_reviewed_at']      = null;
        }

        $order->update($attributes);

        return response()->json([
            'message'             => 'Ordonnance téléversée avec succès.',
            'prescription_url'    => $order->prescriptionDownloadUrl(),
            'prescription_status' => $order->prescription_status,
        ]);
    }

    /**
     * Pharmacien : commandes en attente de validation d'ordonnance.
     */
    public function pending(Request $request): JsonResponse
    {
        $this->ensurePharmacist($request);

        $orders = Order::where('prescription_status', Order::RX_PENDING)
            ->whereNotNull('prescription_url')
            ->with(['user:id,name,email', 'orderItems.product:id,name,requires_prescription'])
            ->latest()
            ->paginate((int) $request->query('per_page', 15));

        // On substitue le chemin brut stocké par une URL signée temporaire,
        // utilisable comme lien cliquable par le pharmacien.
        $data = collect($orders->items())->map(function (Order $order) {
            return array_merge($order->toArray(), [
                'prescription_url' => $order->prescriptionDownloadUrl(),
            ]);
        });

        return response()->json([
            'data' => $data,
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
                'per_page'     => $orders->perPage(),
                'total'        => $orders->total(),
                'from'         => $orders->firstItem(),
                'to'           => $orders->lastItem(),
            ],
        ]);
    }

    /**
     * Pharmacien : approuver l'ordonnance d'une commande.
     */
    public function approve(Request $request, Order $order): JsonResponse
    {
        $this->ensurePharmacist($request);
        $this->ensureReviewable($order);

        $order->update([
            'prescription_status'           => Order::RX_APPROVED,
            'prescription_reviewed_by'      => $request->user()->id,
            'prescription_reviewed_at'      => now(),
            'prescription_rejection_reason' => null,
        ]);

        $this->notifyCustomer(
            $order,
            'Ordonnance validée',
            "Votre ordonnance pour la commande #{$order->id} a été validée. Vous pouvez procéder au paiement."
        );

        return response()->json([
            'message' => 'Ordonnance validée.',
            'order'   => $order->fresh(['prescriptionReviewer:id,name']),
        ]);
    }

    /**
     * Pharmacien : refuser l'ordonnance d'une commande (motif obligatoire).
     */
    public function reject(Request $request, Order $order): JsonResponse
    {
        $this->ensurePharmacist($request);
        $this->ensureReviewable($order);

        $validated = $request->validate([
            'reason' => 'required|string|min:5|max:1000',
        ]);

        $order->update([
            'prescription_status'           => Order::RX_REJECTED,
            'prescription_reviewed_by'      => $request->user()->id,
            'prescription_reviewed_at'      => now(),
            'prescription_rejection_reason' => $validated['reason'],
        ]);

        $this->notifyCustomer(
            $order,
            'Ordonnance refusée',
            "Votre ordonnance pour la commande #{$order->id} a été refusée : {$validated['reason']}. "
                . "Veuillez en téléverser une nouvelle."
        );

        return response()->json([
            'message' => 'Ordonnance refusée.',
            'order'   => $order->fresh(['prescriptionReviewer:id,name']),
        ]);
    }

    /**
     * Seuls un admin (ou, à terme, un rôle pharmacien) peuvent valider.
     */
    private function ensurePharmacist(Request $request): void
    {
        if (! $request->user()->isAdmin()) {
            abort(403, 'Accès réservé au pharmacien.');
        }
    }

    /**
     * Une revue n'a de sens que sur une ordonnance effectivement déposée et
     * en attente. Évite d'approuver une commande sans ordonnance.
     */
    private function ensureReviewable(Order $order): void
    {
        if ($order->prescription_status === Order::RX_NOT_REQUIRED) {
            abort(422, "Cette commande ne nécessite pas d'ordonnance.");
        }

        if (! $order->prescription_url) {
            abort(422, "Aucune ordonnance n'a été téléversée pour cette commande.");
        }
    }

    private function notifyCustomer(Order $order, string $title, string $body): void
    {
        $this->notificationService->sendPushNotification(
            $order->user,
            $title,
            $body,
            'prescription',
            ['order_id' => $order->id, 'prescription_status' => $order->prescription_status]
        );
    }
}
