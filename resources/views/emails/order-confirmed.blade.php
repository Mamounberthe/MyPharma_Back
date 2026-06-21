<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commande confirmée</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f8fafc; margin: 0; padding: 0; }
        .container { max-width: 560px; margin: 40px auto; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,.06); }
        .header { background: linear-gradient(135deg, #059669, #10b981); padding: 36px 32px; text-align: center; color: #fff; }
        .header h1 { margin: 0 0 6px; font-size: 24px; font-weight: 800; }
        .header p { margin: 0; font-size: 14px; opacity: .85; }
        .body { padding: 32px; }
        .greeting { font-size: 16px; font-weight: 600; color: #111827; margin-bottom: 16px; }
        .info-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 20px; margin-bottom: 20px; }
        .info-box .label { font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: .05em; font-weight: 700; margin-bottom: 4px; }
        .info-box .value { font-size: 15px; font-weight: 700; color: #065f46; }
        table.items { width: 100%; border-collapse: collapse; margin: 20px 0; }
        table.items th { font-size: 11px; color: #9ca3af; text-transform: uppercase; letter-spacing: .05em; text-align: left; padding-bottom: 8px; border-bottom: 1px solid #f3f4f6; }
        table.items td { padding: 10px 0; font-size: 13px; color: #374151; border-bottom: 1px solid #f9fafb; vertical-align: top; }
        table.items td.price { text-align: right; font-weight: 700; color: #111827; }
        .total-row { display: flex; justify-content: space-between; padding: 16px 0 0; border-top: 2px solid #e5e7eb; margin-top: 8px; }
        .total-row span { font-size: 16px; font-weight: 800; color: #059669; }
        .cta { text-align: center; margin: 28px 0 8px; }
        .cta a { background: #059669; color: #fff; text-decoration: none; padding: 14px 32px; border-radius: 12px; font-weight: 700; font-size: 14px; display: inline-block; }
        .footer { padding: 20px 32px; text-align: center; font-size: 12px; color: #9ca3af; border-top: 1px solid #f3f4f6; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>✅ Commande confirmée !</h1>
        <p>MyPharma — Santé & Livraison</p>
    </div>
    <div class="body">
        <p class="greeting">Bonjour {{ $order->user->name }} 👋,</p>
        <p style="color:#6b7280;font-size:14px;line-height:1.6;margin-bottom:24px;">
            Votre commande <strong>#{{ $order->id }}</strong> a bien été confirmée et transmise à
            <strong>{{ $order->pharmacy->name ?? 'votre pharmacie' }}</strong>.
            Le livreur prendra en charge votre colis dès que possible.
        </p>

        <div class="info-box">
            <div class="label">Adresse de livraison</div>
            <div class="value">{{ $order->delivery_address }}</div>
        </div>

        <table class="items">
            <thead>
                <tr>
                    <th>Article</th>
                    <th>Qté</th>
                    <th style="text-align:right">Prix</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->orderItems as $item)
                <tr>
                    <td>{{ $item->product->name ?? 'Produit' }}</td>
                    <td>× {{ $item->quantity }}</td>
                    <td class="price">{{ number_format($item->quantity * $item->price, 0, ',', ' ') }} FCFA</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="total-row">
            <span style="color:#374151">Total payé</span>
            <span>{{ number_format($order->total_price, 0, ',', ' ') }} FCFA</span>
        </div>

        <div class="cta">
            <a href="{{ config('app.url') }}/orders/{{ $order->id }}">Suivre ma commande</a>
        </div>

        <p style="color:#9ca3af;font-size:12px;text-align:center;">
            Des questions ? Contactez-nous via l'application MyPharma.
        </p>
    </div>
    <div class="footer">
        © {{ date('Y') }} MyPharma · Tous droits réservés
    </div>
</div>
</body>
</html>
