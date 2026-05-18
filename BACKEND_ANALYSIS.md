# BACKEND_ANALYSIS.md - MyPharma Laravel API

## 📊 AUDIT COMPLET BACKEND LARAVEL

---

## 1. ARCHITECTURE GLOBALE

### Stack Technique
- **Framework**: Laravel 12.0
- **PHP**: 8.2+
- **Authentification**: Laravel Sanctum 4.3
- **Base de données**: MySQL
- **API**: RESTful JSON API
- **Validation**: Form Requests
- **Pagination**: Laravel LengthAwarePaginator
- **Tests**: PHPUnit 11.5

### Structure du Projet
```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/ (12 contrôleurs)
│   ├── Resources/ (5 API Resources)
│   └── Requests/ (10 Form Requests)
├── Models/ (12 modèles Eloquent)
├── Services/ (7 services métier)
└── Helpers/ (GeoHelper)
```

---

## 2. ENDPOINTS API DISPONIBLES

### Base URL
`/api/v1`

### 🔐 Authentification (Public - Rate Limited: 10/min)
| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `/register` | Inscription utilisateur |
| POST | `/login` | Connexion utilisateur |
| GET | `/invitations/validate/{token}` | Validation token invitation |

### 👤 Utilisateur (Protégé - Sanctum + 60/min)
| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/user` | Profil utilisateur connecté |
| POST | `/push-token` | Mise à jour token push notification |
| POST | `/logout` | Déconnexion |

### 🏪 Pharmacies (Protégé)
| Méthode | Endpoint | Description | Paramètres |
|---------|----------|-------------|------------|
| GET | `/pharmacies` | Liste pharmacies | latitude, longitude, radius, min_rating, delivery_available, is_on_call, include_external, sort_by, sort_order, per_page |
| GET | `/pharmacies/{id}` | Détails pharmacie | - |
| GET | `/pharmacies/{id}/reviews` | Avis pharmacie | per_page |

### 💊 Produits (Protégé)
| Méthode | Endpoint | Description | Paramètres |
|---------|----------|-------------|------------|
| GET | `/products` | Liste produits | category_id, search, per_page |
| POST | `/products` | Créer produit | - |
| GET | `/products/{id}` | Détails produit | - |
| PUT | `/products/{id}` | Modifier produit | - |
| DELETE | `/products/{id}` | Supprimer produit | - |
| GET | `/search` | Recherche avancée | query, latitude, longitude, radius, min_rating, min_price, max_price, delivery_available, per_page |
| GET | `/pharmacies/{id}/products` | Produits pharmacie | - |
| GET | `/products/stats` | Statistiques produits | - |
| GET | `/products/popular` | Produits populaires | - |

### 📦 Commandes (Protégé)
| Méthode | Endpoint | Description | Paramètres |
|---------|----------|-------------|------------|
| GET | `/orders` | Liste commandes utilisateur | per_page |
| POST | `/orders` | Créer commande | pharmacy_id, items[], delivery_address |
| GET | `/orders/{id}` | Détails commande | - |
| PATCH | `/orders/{id}/status` | Mettre à jour statut | status |
| GET | `/orders/{id}/tracking` | Suivi livraison | - |

### 🚚 Livraison (Protégé)
| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/orders/{order}/tracking` | Position livreur |
| POST | `/orders/{order}/tracking` | Mettre à jour position |
| POST | `/orders/{order}/pay` | Payer commande |

### 💳 Paiements (Protégé)
| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `/orders/{order}/pay` | Traitement paiement |

### ⭐ Avis (Protégé)
| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `/reviews` | Créer avis |
| PUT | `/reviews/{id}` | Modifier avis |
| DELETE | `/reviews/{id}` | Supprimer avis |

### 📧 Invitations (Protégé)
| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/invitations` | Liste invitations |
| POST | `/invitations` | Créer invitation |

---

## 3. MODÈLES ET RELATIONS

### User
**Champs**: id, name, email, password, role, expo_push_token
**Rôles**: admin, client, livreur
**Relations**:
- hasMany(Order)
- hasMany(Review)

### Pharmacy
**Champs**: id, name, address, latitude, longitude, phone, rating, delivery_available, google_place_id, osm_id, is_on_call, opening_hours, status, is_partner
**Relations**:
- hasMany(Stock)
- belongsToMany(Product, via stocks)
- hasMany(Order)
- hasMany(Review)

### Product
**Champs**: id, name, description, category_id
**Relations**:
- belongsTo(Category)
- hasMany(Stock)
- belongsToMany(Pharmacy, via stocks)
- hasMany(OrderItem)

### Order
**Champs**: id, user_id, pharmacy_id, driver_id, total_price, status, delivery_address, delivered_at
**Statuts**: pending, confirmed, delivering, delivered, cancelled
**Relations**:
- belongsTo(User)
- belongsTo(Pharmacy)
- belongsTo(User, as driver)
- hasMany(OrderTracking)
- hasMany(OrderItem)
- belongsToMany(Product, via order_items)

### OrderItem
**Champs**: id, order_id, product_id, quantity, price
**Relations**:
- belongsTo(Order)
- belongsTo(Product)

### OrderTracking
**Champs**: id, order_id, latitude, longitude, timestamp
**Relations**:
- belongsTo(Order)

### Stock
**Champs**: id, pharmacy_id, product_id, quantity, price
**Relations**:
- belongsTo(Pharmacy)
- belongsTo(Product)

### Review
**Champs**: id, user_id, pharmacy_id, rating, comment
**Relations**:
- belongsTo(User)
- belongsTo(Pharmacy)

### Payment
**Champs**: id, order_id, amount, method, status, transaction_id
**Relations**:
- belongsTo(Order)

### PharmacyInvitation
**Champs**: id, email, token, status, expires_at
**Relations**:
- - (indépendant)

### Category
**Champs**: id, name
**Relations**:
- hasMany(Product)

### Notification
**Champs**: id, user_id, type, data, read_at
**Relations**:
- belongsTo(User)

---

## 4. SERVICES MÉTIER

### PharmacyService
- getPharmacies(): Liste avec filtres géographiques
- getPharmacyById(): Détails avec relations
- getPharmacyReviews(): Avis paginés
- Intégration ExternalPharmacyService pour Google/OSM

### OrderService
- createOrder(): Création avec gestion stocks transactionnelle
- updateOrderStatus(): Mise à jour statut avec logique métier
- getUserOrders(): Commandes utilisateur
- validateStockAndCalculateTotal(): Validation stocks
- handleOrderCancellation(): Restitution stocks

### ProductService
- Index avec filtres catégorie/recherche
- Search avancée avec géolocalisation
- pharmacyProducts(): Produits par pharmacie
- stats(): Statistiques
- popular(): Produits populaires

### ReviewService
- store(): Création avis
- update(): Modification
- destroy(): Suppression
- Validation propriétaire

### PaymentService
- Traitement paiements
- Gestion méthodes paiement

### NotificationService
- Envoi notifications
- Gestion push tokens

### ExternalPharmacyService
- Intégration Google Places API
- Intégration OpenStreetMap/Overpass
- Fusion pharmacies internes/externes

---

## 5. STRUCTURE RÉPONSES API

### Format Standard Succès
```json
{
  "data": { ... },
  "pagination": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 75,
    "from": 1,
    "to": 15
  }
}
```

### Format Erreur
```json
{
  "error": "Error type",
  "message": "Detailed message"
}
```

### Auth Response
```json
{
  "user": { ... },
  "token": "plain_text_token",
  "token_type": "Bearer"
}
```

---

## 6. SÉCURITÉ

### Authentification
- Laravel Sanctum (Token-based)
- Tokens stockés dans personal_access_tokens
- Rate limiting: 10/min pour auth, 60/min pour routes protégées

### Validation
- Form Requests pour toutes les entrées
- Validation côté serveur stricte
- Messages d'erreur personnalisés

### Autorisations
- Policies basées sur les rôles
- Vérification propriété ressources
- Middleware 'auth:sanctum'

### Protection
- Protection contre brute force (throttle)
- Hashing passwords (bcrypt)
- Mass assignment protection ($fillable)

---

## 7. GÉOLOCALISATION

### Calcul Distance
- Formule Haversine (6371 * acos(...))
- Filtre par rayon en kilomètres
- Tri par distance

### Coordonnées
- Latitude: -90 à 90
- Longitude: -180 à 180
- Précision: decimal(8,8)

### Services Externes
- Google Places API
- OpenStreetMap Overpass API
- Fusion données internes/externes

---

## 8. VALIDATION

### Règles Principales
- email: required|email
- password: required|min:8
- latitude: numeric|between:-90,90
- longitude: numeric|between:-180,180
- radius: numeric|min:1|max:100
- rating: numeric|between:1,5
- per_page: numeric|min:1|max:50

### Form Requests
- RegisterRequest
- GetPharmaciesRequest
- GetPharmacyReviewsRequest
- StoreOrderRequest
- UpdateOrderStatusRequest
- StoreProductRequest
- UpdateProductRequest
- StoreReviewRequest
- UpdateReviewRequest
- SearchProductsRequest

---

## 9. PAGINATION

### Configuration
- Par défaut: 15 items par page
- Maximum: 50 items par page
- Métadonnées complètes incluses

### Endpoints Paginés
- /pharmacies
- /products
- /orders
- /pharmacies/{id}/reviews
- /search

---

## 10. STATUTS COMMANDES

### Cycle de Vie
1. **pending** → Commande créée
2. **confirmed** → Préparation en cours
3. **delivering** → En livraison
4. **delivered** → Livrée
5. **cancelled** → Annulée

### Transitions Autorisées
- pending → confirmed (pharmacie/admin)
- confirmed → delivering (livreur/admin)
- delivering → delivered (livreur/admin)
- pending/confirmed → cancelled (client)
- confirmed → cancelled (admin)

### Tracking Timeline
- pending: "Commande confirmée" (0 min)
- confirmed: "Préparation en cours" (10 min)
- delivering: "En livraison" (30 min)
- delivered: "Livrée" (45 min)

---

## 11. PROBLÈMES DÉTECTÉS

### ⚠️ Mineurs
- Certains contrôleurs ont des versions "Refactored" et originales (duplication potentielle)
- Pas de middleware de rate limiting par rôle
- Pas de logging structuré des erreurs
- Pas de cache Redis configuré

### ℹ️ Améliorations Possibles
- Ajouter WebSocket pour tracking temps réel
- Implémenter refresh token automatique
- Ajouter pagination cursor-based pour grandes listes
- Optimiser N+1 queries avec eager loading systématique
- Ajouter tests d'integration API complets

---

## 12. RECOMMANDATIONS FRONTEND

### Architecture Recommandée
- **Client HTTP**: Axios avec interceptors
- **Gestion État**: Zustand pour état local, React Query pour serveur
- **Routing**: React Router v6
- **Forms**: React Hook Form + Zod
- **UI**: TailwindCSS + shadcn/ui
- **Maps**: React Leaflet (web) / react-native-maps (mobile)

### Services API à Créer
- authService (login, register, logout, refresh)
- pharmacyService (list, show, reviews, search)
- productService (list, show, search, stats)
- orderService (list, show, create, updateStatus, tracking)
- reviewService (create, update, delete)
- paymentService (process)
- notificationService (register, list)

### Types TypeScript
- User, Pharmacy, Product, Order, OrderItem, Review
- ApiResponse<T>, PaginatedResponse<T>
- OrderStatus, UserRole
- FilterParams, SearchParams

### Features Prioritaires
1. Authentification complète avec refresh token
2. Liste pharmacies avec carte interactive
3. Recherche produits avancée
4. Panier et checkout
5. Suivi commande temps réel
6. Avis et notations
7. Géolocalisation utilisateur

---

## 13. CONCLUSION

### ✅ Points Forts
- Architecture Laravel propre et moderne
- API RESTful bien structurée
- Services métier bien séparés
- Validation robuste
- Géolocalisation intégrée
- Authentification Sanctum sécurisée

### 📋 État Actuel
- Backend **PRODUCTION-READY**
- APIs complètes et fonctionnelles
- Modèles et relations bien définis
- Services métier implémentés
- Prêt pour intégration frontend

### 🎯 Prochaine Étape
Créer un frontend React moderne et propre qui consomme ces APIs de manière optimale.
