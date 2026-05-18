<?php

namespace App\Services;

use App\Models\User;
use App\Models\Notification as NotificationModel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Envoyer une notification push via Expo
     */
    public function sendPushNotification(User $user, string $title, string $body, string $type, array $data = [])
    {
        // 1. Enregistrer en base de données
        NotificationModel::create([
            'user_id' => $user->id,
            'title' => $title,
            'body' => $body,
            'type' => $type,
            'data' => $data
        ]);

        // 2. Envoyer le push si le token existe
        if (!$user->expo_push_token) {
            Log::info("No push token for user {$user->id}");
            return false;
        }

        try {
            $response = Http::post('https://exp.host/--/api/v2/push/send', [
                'to' => $user->expo_push_token,
                'title' => $title,
                'body' => $body,
                'data' => array_merge($data, ['type' => $type]),
                'sound' => 'default',
            ]);

            if ($response->failed()) {
                Log::error("Expo notification failed: " . $response->body());
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error("Exception sending notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Notifier d'un changement de statut de commande
     */
    public function notifyOrderStatusChange($order)
    {
        $user = $order->user;
        $statusTexts = [
            'confirmed' => 'Votre commande a été confirmée !',
            'delivering' => 'Votre commande est en cours de livraison ! 🚴',
            'delivered' => 'Votre commande a été livrée. Santé ! ✅',
            'cancelled' => 'Votre commande a été annulée.',
        ];

        if (isset($statusTexts[$order->status])) {
            $this->sendPushNotification(
                $user,
                'Mise à jour de commande',
                $statusTexts[$order->status],
                'order_update',
                ['order_id' => $order->id, 'status' => $order->status]
            );
        }
    }
}
