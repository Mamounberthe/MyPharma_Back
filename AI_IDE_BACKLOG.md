# MyPharma — Backlog d'implémentation (prêt pour IDE IA)

> Chaque ticket est **autonome** : copie le « Contexte global » ci-dessous **+ un ticket** dans ton IDE IA (Cursor / Windsurf / Claude Code). Implémente **un ticket = une PR**. Ne pas enchaîner plusieurs tickets sans vérifier.

---

## Contexte global (À COLLER EN TÊTE DE CHAQUE PROMPT)

Application de pharmacie en ligne (Mali / Bamako, devise **FCFA/XOF**, mobile money Orange Money + Wave). **Deux repos séparés :**
- **Backend (API)** : `C:\Users\PC\Laravel\MyPharma` — Laravel 12, PHP 8.2, Sanctum, SQLite en dev. Lancer : `php artisan serve` (→ :8000). Reseed : `php artisan migrate:fresh --seed`. Comptes seed : `admin@mypharma.com` / `john@example.com`, mdp `password`.
- **Frontend** : `C:\Users\PC\Laravel\mypharma-frontend` — React 18 + Vite + TS, React Query v5, Zustand, React Router v6, Tailwind, Leaflet. Lancer : `npm run dev` (→ :3001, proxy `/api` → :8000).

**Conventions & garde-fous (NE PAS CASSER) :**
1. **Prix toujours en FCFA via `formatCurrency()`** (`src/utils/formatCurrency.ts`). Jamais `.toFixed(2) €`, jamais montant brut.
2. **Couche `src/services/*.ts` double-enveloppe** : elle renvoie `{ success, data: <réponse api> }`. Les endpoints produits `index/show/pharmacyProducts` renvoient des réponses **brutes** (paginator ou objet, **pas** `{success,data}`) — le front en dépend. Ne pas ajouter d'enveloppe `{success,data}` sur ces routes.
3. **Auth Sanctum** : token dans `localStorage('auth_token')`, injecté par l'interceptor `src/api/client.ts`. Rôles : `client` / `admin` / `livreur`.
4. **Règle des hooks React** : dans les pages à `useQuery`/`useEffect` (ex. `ProductDetailPage`), TOUS les hooks AVANT tout `return` conditionnel.
5. **1 contrôleur par domaine** (`ProductController`, `OrderControllerRefactored`, `PharmacyControllerRefactored`, `ReviewController`…). Routes dans `routes/api.php`, préfixe `/v1`.
6. **Definition of Done** : `php -l` OK + `php artisan route:list` résout + `npx tsc --noEmit` exit 0. Si comportement observable : tester via curl (routes publiques) ou navigateur.

**Choix de périmètre à acter** (impacte les tickets `[MARKETPLACE]`) :
- **Option A — Mono-enseigne** : un admin gère le stock de toutes les officines, pas d'auto-gestion par pharmacie → ignorer les tickets `[MARKETPLACE]`, retirer le décor (invitations, `is_partner`).
- **Option B — Marketplace** : pharmacies partenaires auto-gérées → faire les tickets `[MARKETPLACE]`.

---

## PHASE 0 — Cohérence (quick wins, à faire en premier)

### T0.1 [FRONT] Rendre la navigation publique
**But :** un visiteur doit voir l'accueil, les pharmacies et les produits **sans compte** ; le login n'est exigé qu'au panier/commande/profil/admin.
**Fichiers :** `src/App.tsx` (sortir `/`, `/pharmacies`, `/pharmacies/garde`, `/pharmacies/:id`, `/products`, `/products/:id` de `ProtectedRoute` ; garder `/cart`, `/orders`, `/orders/:id`, `/profile`, `/admin/*` protégés), `src/components/common/Header.tsx` + `BottomNav.tsx` (afficher un CTA « Se connecter » quand invité, masquer Profil/Commandes).
**Critères d'acceptation :** déconnecté, j'accède à `/`, `/products`, `/pharmacies/:id` sans redirection ; cliquer « Ajouter au panier » fonctionne en invité ; aller à `/cart` ou `/orders` redirige vers `/login`.

### T0.2 [FRONT] Panier mono-pharmacie
**But :** empêcher un panier qui mélange plusieurs pharmacies (le `POST /orders` n'accepte qu'un `pharmacy_id`).
**Fichiers :** `src/store/cartStore.ts` (dans `addItem`, si le panier contient déjà des articles d'une autre `pharmacy_id`, refuser ou proposer de vider), `src/pages/orders/CartPage.tsx` + sites d'ajout (`PharmacyDetailPage`, `ProductDetailPage`, `QuickAddModal`) : afficher une modale « Votre panier contient des articles d'une autre pharmacie. Le vider ? ».
**Critères d'acceptation :** impossible d'avoir 2 `pharmacy_id` dans le panier ; le checkout envoie toujours un seul `pharmacy_id` cohérent avec les items.

### T0.3 [BACK+FRONT] Réinitialisation de mot de passe
**But :** flux « mot de passe oublié » (la table `password_reset_tokens` existe déjà, aucun endpoint).
**Fichiers :** BACK — `routes/api.php` (`POST /v1/forgot-password`, `POST /v1/reset-password`, throttle), `AuthController` (utiliser `Password::sendResetLink` / `Password::reset`), un Mailable. FRONT — `src/pages/auth/ForgotPasswordPage.tsx` + `ResetPasswordPage.tsx`, `src/api/auth.api.ts`.
**Critères d'acceptation :** demande de lien → email (canal `log` en dev) ; reset avec token valide change le mot de passe ; token expiré/invalide → 422.

### T0.4 [BACK] Nettoyage dette
**But :** intégrité + retrait du code mort.
**Fichiers :** migration ajoutant `softDeletes()` à `orders` et `payments` + trait `SoftDeletes` sur `App\Models\Order` et `Payment` ; supprimer `app/Services/ProductService.php` s'il n'est plus référencé (vérifier avant) ; si Option A, retirer la dépendance Expo et `expo_push_token`.
**Critères d'acceptation :** `route:list` OK ; suppression d'une commande = soft delete (récupérable) ; aucun import cassé.

---

## PHASE 1 — Commande de bout en bout réelle

### T1.1 [BACK+FRONT] Authentification par téléphone + OTP
**But :** login par numéro (SMS/WhatsApp), plus naturel au Mali, en plus de l'email.
**Fichiers :** BACK — migration `phone` (unique) sur `users` + table `otp_codes` (phone, code, expires_at) ; `POST /v1/auth/request-otp`, `POST /v1/auth/verify-otp` (renvoie token Sanctum, crée le user si nouveau) ; service d'envoi SMS (interface + driver `log` en dev). FRONT — écran « Entrez votre numéro » → « Entrez le code », `src/api/auth.api.ts`, `authStore`.
**Critères d'acceptation :** numéro → OTP (loggé en dev) → vérif → connecté ; code expiré (>5 min) ou faux → 422 ; rate-limit sur request-otp.

### T1.2 [BACK] Paiement Orange Money + Wave réel + webhook
**But :** remplacer la simulation par une vraie intégration asynchrone.
**Fichiers :** `app/Services/PaymentService.php` (driver par provider derrière une interface), `PaymentController@pay` (initie + renvoie statut `pending`), nouvelle route **publique** `POST /v1/payments/webhook/{provider}` (vérifie signature, passe `Payment.status` à `completed/failed`, déclenche `Order` → `confirmed` via `OrderService`). Idempotence sur `transaction_id`.
**Critères d'acceptation :** un paiement reste `pending` jusqu'au webhook ; webhook `completed` confirme la commande ; double webhook = pas de double effet ; échec → `Order` annulable + stock restitué.

### T1.3 [BACK+FRONT] Ordonnance obligatoire + validation pharmacien
**But :** un produit soumis à prescription exige une ordonnance, validée par un pharmacien avant préparation.
**Fichiers :** BACK — migration `requires_prescription` (bool) sur `products` ; statut commande `pending_validation` ; à la création, si un item `requires_prescription` et pas d'ordonnance → bloquer ou forcer upload (`POST /orders/{order}/prescription` existe déjà) ; endpoint admin/pharmacien `PATCH /orders/{order}/validate-prescription`. FRONT — badge « sur ordonnance » sur les fiches, étape upload obligatoire au checkout, écran de validation côté staff.
**Critères d'acceptation :** commander un produit Rx sans ordonnance est impossible ; après upload, la commande est `pending_validation` jusqu'à validation staff ; produits non-Rx inchangés.

### T1.4 [BACK+FRONT] Notifications SMS/WhatsApp + centre in-app
**But :** prévenir le client à chaque changement de statut (le push web est peu fiable au Mali) ; lister les notifs in-app (table `Notification` déjà présente, sans UI).
**Fichiers :** BACK — `NotificationService` : envoyer SMS/WhatsApp (driver `log` en dev) ET créer une `Notification` à chaque transition dans `OrderService::updateOrderStatus` ; `GET /v1/notifications`, `PATCH /v1/notifications/{id}/read`. FRONT — cloche + page `src/pages/notifications/NotificationsPage.tsx`, badge non-lus dans Header/BottomNav.
**Critères d'acceptation :** changer le statut d'une commande crée une notif + un envoi (loggé) ; la cloche montre le compteur non-lus ; marquer lu fonctionne.

### T1.5 [FRONT] Checkout enrichi : livraison/retrait + adresses + carte
**But :** choix livraison **ou** retrait en pharmacie, carnet d'adresses, point GPS.
**Fichiers :** `src/pages/orders/CartPage.tsx` (toggle livraison/retrait), carnet d'adresses (nouveau store ou backend `addresses`), sélecteur de point sur carte Leaflet pour géocoder `delivery_address` (lat/lng).
**Critères d'acceptation :** je choisis retrait → pas de frais de livraison ; je pose un point sur la carte → coordonnées enregistrées avec la commande ; adresses réutilisables.

---

## PHASE 2 — Logistique & fulfillment

### T2.1 [BACK+FRONT] Affectation livreur + vue livreur (débloque le suivi temps réel)
**But :** aujourd'hui `Order.driver_id` n'est jamais posé → `updateLocation` renvoie toujours 403 et la carte n'a jamais de position. Câbler le rôle livreur.
**Fichiers :** BACK — `PATCH /orders/{order}/assign` (admin affecte un `driver_id`) ou auto-affectation ; autoriser `OrderTrackingController@updateLocation` quand `order.driver_id === Auth::id()`. FRONT — espace livreur (login rôle `livreur`) : `src/pages/driver/MyDeliveriesPage.tsx` (commandes assignées), bouton « Démarrer la livraison » (statut `delivering`), **partage de position** périodique (`POST /orders/{order}/tracking`), preuve de livraison (photo/signature) → statut `delivered`.
**Critères d'acceptation :** un livreur assigné voit ses commandes, partage sa position, et la position apparaît sur `OrderTrackingMap` côté client (polling 5 s déjà en place) ; un non-assigné est refusé (403).

### T2.2 [BACK] Zones & frais de livraison par distance
**But :** remplacer le frais en dur (1500 / gratuit dès 25000) par un calcul réel.
**Fichiers :** `app/Helpers/GeoHelper.php` (distance pharmacie ↔ adresse), barème de frais (config), exposé au checkout via une route d'estimation `POST /v1/delivery/quote`.
**Critères d'acceptation :** le frais varie avec la distance ; hors zone → message clair ; le total commande inclut le frais calculé serveur.

### T2.3 [MARKETPLACE] [BACK+FRONT] Tableau de bord pharmacie (fulfillment)
**But :** une pharmacie partenaire gère ses commandes et son stock (sinon l'admin fait tout).
**Fichiers :** BACK — rôle `pharmacien` (lié à une `pharmacy_id`), policies (ne voir que ses commandes/stocks), endpoints scoping. FRONT — espace pharmacie : commandes entrantes (accepter/préparer), gestion stock de SON officine.
**Critères d'acceptation :** un pharmacien ne voit que sa pharmacie ; il fait avancer `pending → confirmed` ; il gère ses prix/quantités.

### T2.4 [MARKETPLACE] [BACK+FRONT] Onboarding partenaire
**But :** activer le flux d'invitation (déjà modélisé : `PharmacyInvitation`, jamais finalisé).
**Fichiers :** BACK — envoi réel de l'email d'invitation, passage du statut à `accepted` à l'inscription via token, création du compte `pharmacien` + liaison `pharmacy`. FRONT — page « accepter l'invitation » (token → formulaire de création).
**Critères d'acceptation :** invitation envoyée → lien → création de compte pharmacien → `status=accepted`, `pharmacy.is_partner=true`.

---

## PHASE 3 — Croissance & soin

### T3.1 [BACK+FRONT] Favoris
Model + table `favorites` (user, product), endpoints CRUD, bouton cœur sur fiches/listes, page « Mes favoris ». **AC :** ajouter/retirer persiste par utilisateur.

### T3.2 [BACK+FRONT] Rappels de renouvellement + alerte retour en stock
Pour traitements chroniques : opt-in sur un produit → rappel programmé (SMS/notif) ; alerte quand un produit en rupture revient en stock. **AC :** un rappel planifié déclenche une notif à l'échéance.

### T3.3 [BACK+FRONT] Avis produits
Étendre les avis (aujourd'hui pharmacie uniquement) aux produits (après commande livrée). **AC :** un client ayant reçu un produit peut le noter ; moyenne affichée sur la fiche.

### T3.4 [BACK+FRONT] Téléconseil pharmacien (chat)
Canal de discussion client ↔ pharmacien (déjà mis en avant sur la home). **AC :** un client ouvre une conversation, un pharmacien répond.

### T3.5 [FRONT] Back-office admin complet
Pages manquantes : **CRUD produits** (l'API existe : `POST/PUT/DELETE /products`), **CRUD pharmacies**, **gestion utilisateurs** (rôles), **graphes de ventes** (le dashboard n'a que des compteurs). **AC :** un admin crée/édite un produit depuis l'UI.

### T3.6 [FRONT] PWA + performance + i18n
Transformer en **PWA** (manifest, service worker, installable, cache offline, push web) — adapté au Mali ; **lazy-loading des routes** + **code-splitting** + optimisation images ; i18n FR (+ bambara optionnel). **AC :** app installable, écran offline minimal, Lighthouse PWA OK.

### T3.7 [FRONT] Géolocalisation utilisateur (tri proximité)
Demander la position (optionnelle), l'envoyer aux requêtes pharmacies/recherche pour trier par distance et afficher l'ETA. **AC :** pharmacies triables par proximité.

### T3.8 [BOTH] Qualité : tests, monitoring, durcissement prod
Tests front (Vitest/RTL) sur panier/checkout ; monitoring d'erreurs (Sentry) ; config prod (`APP_DEBUG=false`, CORS whitelisté, secrets hors repo). **AC :** suite de tests verte en CI.

---

## Ordre recommandé
**0.1 → 0.2 → 0.3 → 0.4** (cohérence) puis **1.2 → 1.3 → 1.4** (vendre + conformité) puis **2.1 → 2.2** (livrer). Le reste selon le périmètre choisi (A/B) et la priorité business.
