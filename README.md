# MyPharma API

API REST complète pour une application mobile de pharmacie géolocalisée construite avec Laravel 12.

## Features

- **Authentification** sécurisée avec Laravel Sanctum
- **Géolocalisation** des pharmacies avec calcul de distance
- **Recherche intelligente** de produits avec filtres avancés
- **Gestion des commandes** avec suivi de livraison
- **Système d'avis** et notation des pharmacies
- **Stock temps réel** avec gestion automatique
- **API REST** optimisée pour mobile

## Tech Stack

- **Backend**: Laravel 12
- **Authentification**: Laravel Sanctum
- **Base de données**: MySQL
- **Tests**: PHPUnit
- **Validation**: Form Requests

## Installation

### Prérequis

- PHP 8.3+
- MySQL 8.0+
- Composer

### Configuration

1. Cloner le projet
```bash
git clone <repository-url>
cd MyPharma
```

2. Installer les dépendances
```bash
composer install
```

3. Configurer l'environnement
```bash
cp .env.example .env
php artisan key:generate
```

4. Configurer la base de données dans `.env`
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mypharma
DB_USERNAME=root
DB_PASSWORD=
```

5. Lancer les migrations et seeder
```bash
php artisan migrate
php artisan db:seed
```

6. Démarrer le serveur
```bash
php artisan serve
```

## Endpoints API

### Authentification

#### Inscription
```http
POST /api/register
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "role": "client"
}
```

#### Connexion
```http
POST /api/login
Content-Type: application/json

{
  "email": "john@example.com",
  "password": "password123"
}
```

#### Déconnexion
```http
POST /api/logout
Authorization: Bearer {token}
```

#### Profil utilisateur
```http
GET /api/user
Authorization: Bearer {token}
```

### Pharmacies

#### Lister les pharmacies
```http
GET /api/pharmacies?latitude=48.8566&longitude=2.3522&radius=10&min_rating=4
Authorization: Bearer {token}
```

#### Détails d'une pharmacie
```http
GET /api/pharmacies/{id}
Authorization: Bearer {token}
```

#### Avis d'une pharmacie
```http
GET /api/pharmacies/{id}/reviews
Authorization: Bearer {token}
```

### Produits

#### Lister les produits
```http
GET /api/products?category_id=1&search=paracetamol
Authorization: Bearer {token}
```

#### Détails d'un produit
```http
GET /api/products/{id}
Authorization: Bearer {token}
```

#### Recherche intelligente
```http
GET /api/search?query=paracetamol&latitude=48.8566&longitude=2.3522&min_price=5&max_price=20&min_rating=4
Authorization: Bearer {token}
```

### Commandes

#### Créer une commande
```http
POST /api/orders
Authorization: Bearer {token}
Content-Type: application/json

{
  "pharmacy_id": 1,
  "items": [
    {
      "product_id": 1,
      "quantity": 2
    }
  ],
  "delivery_address": "123 Rue de la Pharmacie, Paris"
}
```

#### Lister mes commandes
```http
GET /api/orders
Authorization: Bearer {token}
```

#### Détails d'une commande
```http
GET /api/orders/{id}
Authorization: Bearer {token}
```

#### Mettre à jour le statut (Admin/Livreur)
```http
PATCH /api/orders/{id}/status
Authorization: Bearer {token}
Content-Type: application/json

{
  "status": "confirmed"
}
```

### Avis

#### Ajouter un avis
```http
POST /api/reviews
Authorization: Bearer {token}
Content-Type: application/json

{
  "pharmacy_id": 1,
  "rating": 5,
  "comment": "Excellent service!"
}
```

#### Modifier un avis
```http
PUT /api/reviews/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "rating": 4,
  "comment": "Très satisfait"
}
```

#### Supprimer un avis
```http
DELETE /api/reviews/{id}
Authorization: Bearer {token}
```

## Structure de la base de données

### Tables principales

- **users**: Utilisateurs (client, admin, livreur)
- **pharmacies**: Informations des pharmacies avec géolocalisation
- **categories**: Catégories de produits
- **products**: Catalogue des produits
- **stocks**: Stocks par pharmacie et produit
- **orders**: Commandes des utilisateurs
- **order_items**: Détails des commandes
- **reviews**: Avis et notations

### Relations clés

- User hasMany Orders, Reviews
- Pharmacy hasMany Stocks, Orders, Reviews
- Product belongsTo Category, hasMany Stocks
- Stock belongsTo Pharmacy, Product
- Order belongsTo User, Pharmacy, hasMany OrderItems

## Tests

Lancer la suite de tests :
```bash
php artisan test
```

Tests disponibles :
- **AuthApiTest**: Authentification et autorisation
- **ProductSearchTest**: Recherche de produits et filtres
- **OrderTest**: Gestion des commandes

## Sécurité

- **Tokens JWT** avec Laravel Sanctum
- **Validation** des entrées avec Form Requests
- **Protection** contre les injections SQL
- **Autorisations** basées sur les rôles
- **Rate limiting** sur les endpoints sensibles

## Optimisations

- **Eager Loading** pour éviter N+1 queries
- **API Resources** pour des réponses JSON optimisées
- **Pagination** sur les listes
- **Index** base de données sur les champs critiques
- **Caching** configurable

## Données de test

Le seeder crée automatiquement :
- 4 utilisateurs (admin, 2 clients, 1 livreur)
- 3 pharmacies à Paris
- 5 catégories de produits
- 6 produits de test
- Stocks aléatoires
- Commandes de test
- Avis de test

Comptes de test :
- **Admin**: admin@mypharma.com / password
- **Client**: john@example.com / password
- **Livreur**: driver@mypharma.com / password

## Déploiement

### Production

1. Configurer les variables d'environnement
2. Optimiser l'application :
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
3. Lancer les migrations
4. Configurer le serveur web (nginx/Apache)

### Docker

```dockerfile
FROM php:8.3-fpm
# ... configuration Docker
```

## Contributions

1. Forker le projet
2. Créer une branche feature
3. Commiter les changements
4. Pusher vers la branche
5. Créer une Pull Request

## License

Ce projet est sous licence MIT.
