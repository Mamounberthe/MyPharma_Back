# Requirements Document

## Introduction

L'application MyPharma est composée d'un backend Laravel 12 déployé sur Render (`https://mypharma-back-1.onrender.com`) et d'un frontend React/Vite déployé sur Vercel (`https://my-pharma-front.vercel.app`), avec une base de données PostgreSQL hébergée sur Render.

Le frontend se charge visuellement mais **toutes les données sont absentes** : catégories, produits et pharmacies affichent « Aucun résultat ». Six problèmes de déploiement ont été identifiés comme causes racines, allant de la mauvaise URL d'API dans le frontend jusqu'à la configuration CORS invalide côté backend. Ce document définit les exigences de correction pour rétablir le fonctionnement complet de l'application en production.

---

## Glossary

- **Frontend** : L'application React/Vite déployée sur Vercel à l'adresse `https://my-pharma-front.vercel.app`.
- **Backend** : L'API Laravel 12 déployée sur Render à l'adresse `https://mypharma-back-1.onrender.com`.
- **VITE_API_BASE_URL** : Variable d'environnement préfixée `VITE_` exposée au bundle Vite/React, contenant l'URL de base de l'API.
- **CorsMiddleware** : Middleware PHP personnalisé (`App\Http\Middleware\CorsMiddleware`) gérant les en-têtes CORS dans le Backend.
- **cors.php** : Fichier de configuration Laravel (`config/cors.php`) gérant le CORS via le package Fruitcake.
- **render.yaml** : Fichier de configuration Infrastructure-as-Code pour Render, définissant les services et variables d'environnement du Backend.
- **Dockerfile** : Fichier de construction de l'image Docker utilisé par Render pour déployer le Backend.
- **Sanctum** : Package Laravel d'authentification par token Bearer utilisé par le Backend.
- **SANCTUM_STATEFUL_DOMAINS** : Variable d'environnement Laravel définissant les domaines autorisés à utiliser l'authentification Sanctum en mode stateful.
- **APP_FRONTEND_URL** : Variable d'environnement du Backend contenant l'URL publique du Frontend.
- **APP_URL** : Variable d'environnement Laravel contenant l'URL publique du Backend.
- **Pre-flight** : Requête HTTP OPTIONS envoyée par le navigateur avant une requête cross-origin pour vérifier les permissions CORS.
- **Migration** : Commande `php artisan migrate --force` créant les tables en base de données.
- **Seed** : Commande `php artisan db:seed --force` insérant les données initiales en base de données.

---

## Requirements

### Requirement 1: Correction de l'URL d'API dans le Frontend

**User Story:** En tant que visiteur du site, je veux que le Frontend appelle l'API correcte en production, afin que les catégories, produits et pharmacies soient affichés avec des données réelles.

#### Acceptance Criteria

1. THE Frontend SHALL lire l'URL de base de l'API exclusivement depuis la variable d'environnement `VITE_API_BASE_URL` injectée au moment du build Vite, pour toutes ses requêtes HTTP vers le Backend — aucune URL de Backend ne doit être codée en dur dans le code source.
2. IF le Frontend est déployé sur Vercel, THEN THE variable d'environnement Vercel `VITE_API_BASE_URL` SHALL être définie avec la valeur `https://mypharma-back-1.onrender.com/api/v1` dans les paramètres du projet Vercel (Environment Variables), de sorte que le bundle de production contienne cette URL.
3. IF `VITE_API_BASE_URL` n'est pas définie au moment du build, THEN THE Frontend SHALL consigner un avertissement visible dans la console du navigateur indiquant que la variable est absente, et toutes les requêtes API SHALL échouer de façon prévisible (URL `undefined/api/v1` facilement détectable dans les DevTools).
4. THE fichier `.env` local du projet Frontend SHALL conserver `VITE_API_BASE_URL=http://localhost:8000/api/v1` pour le développement uniquement ; Vercel ne lira pas ce fichier (il est dans `.gitignore`) et utilisera exclusivement les variables configurées dans son tableau de bord.
5. THE fichier `.env.example` du projet Frontend SHALL contenir `VITE_API_BASE_URL=https://mypharma-back-1.onrender.com/api/v1` comme valeur de référence pour la production, et `VITE_API_BASE_URL=http://localhost:8000/api/v1` commenté pour le développement local.
6. IF `VITE_API_BASE_URL` est absente de l'environnement Vercel au moment du déploiement, THEN THE build Vercel SHALL produire un bundle où toutes les requêtes API échouent immédiatement (pas de fallback silencieux vers localhost).

---

### Requirement 2: Exécution des Migrations et Seeds au Démarrage du Backend

**User Story:** En tant qu'administrateur système, je veux que la base de données PostgreSQL soit initialisée automatiquement lors du déploiement du Backend, afin que l'API retourne des données au lieu d'erreurs.

#### Acceptance Criteria

1. WHEN le script de démarrage du Backend s'exécute sur Render, THE script de démarrage (`CMD` du Dockerfile ou `startCommand` dans render.yaml) SHALL exécuter `php artisan migrate --force` avant de démarrer le serveur Nginx/PHP-FPM — mesurable par l'ordre des lignes dans les logs Render.
2. WHEN `php artisan migrate --force` s'exécute et que toutes les migrations ont déjà été appliquées, THE commande SHALL sortir avec le code 0 et afficher `Nothing to migrate` sans interrompre le démarrage.
3. IF `php artisan migrate --force` sort avec un code non-zéro (erreur de connexion DB, migration corrompue, etc.), THEN THE script de démarrage SHALL s'arrêter immédiatement sans lancer Nginx ni PHP-FPM, ce qui provoquera un redémarrage automatique par Render et rendra l'échec visible dans les logs.
4. IF la variable d'environnement `RUN_SEEDS=true` est définie dans Render, THEN THE script de démarrage SHALL exécuter `php artisan db:seed --force` immédiatement après la migration réussie, et avant le démarrage du serveur web.
5. IF `php artisan db:seed --force` sort avec un code non-zéro, THEN THE script de démarrage SHALL s'arrêter avec un code non-zéro, et Render SHALL journaliser l'erreur de seed comme cause d'échec du démarrage.
6. WHEN `php artisan db:seed --force` est exécuté et que des enregistrements seed existent déjà, THE seeder SHALL utiliser `firstOrCreate` (ou `updateOrCreate` selon les besoins) pour chaque entité seedée — catégories, utilisateurs, pharmacies, produits et stocks — de sorte qu'aucun doublon ne soit créé et que la commande sorte avec le code 0.

---

### Requirement 3: Correction de la Configuration CORS

**User Story:** En tant qu'utilisateur du Frontend, je veux que mes requêtes HTTP vers le Backend soient acceptées par le navigateur, afin que l'authentification et les appels API fonctionnent sans erreur CORS.

#### Acceptance Criteria

1. WHEN le Backend reçoit une requête HTTP portant un en-tête `Origin: https://my-pharma-front.vercel.app`, THE Backend SHALL retourner un en-tête de réponse `Access-Control-Allow-Origin: https://my-pharma-front.vercel.app` (valeur exacte, pas un wildcard).
2. WHEN le Backend reçoit une requête cross-origin (présence de l'en-tête `Origin`), THE Backend SHALL inclure `Access-Control-Allow-Credentials: true` dans la réponse, afin que le navigateur accepte les requêtes Axios avec `withCredentials` ou les tokens Bearer.
3. WHEN le Backend reçoit une requête HTTP `OPTIONS` (pre-flight CORS), THE Backend SHALL retourner un statut HTTP 200 incluant les en-têtes `Access-Control-Allow-Origin`, `Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS`, `Access-Control-Allow-Headers: Content-Type, Authorization, Accept, Origin, X-Requested-With`, et `Access-Control-Max-Age: 86400`.
4. THE Backend SHALL utiliser exclusivement `CorsMiddleware` (`App\Http\Middleware\CorsMiddleware`) comme mécanisme de gestion CORS ; le middleware `HandleCors` de Laravel (issu du package `fruitcake/laravel-cors` ou du noyau Laravel 10+) NE SHALL PAS être enregistré dans `bootstrap/app.php` ni dans `config/cors.php` — vérifiable par l'absence de doublons d'en-têtes `Access-Control-Allow-Origin` dans les réponses (un seul en-tête par réponse).
5. IF une requête HTTP porte un en-tête `Origin` dont la valeur ne correspond pas à `APP_FRONTEND_URL` ni à `http://localhost:3001`, THEN THE Backend SHALL ne pas inclure l'en-tête `Access-Control-Allow-Origin` dans la réponse, laissant le navigateur appliquer sa politique CORS par défaut (blocage).
6. THE `CorsMiddleware` SHALL lire dynamiquement l'origine autorisée depuis la variable d'environnement `APP_FRONTEND_URL` (via `env('APP_FRONTEND_URL')`), et autoriser également `http://localhost:3001` pour le développement local.

---

### Requirement 4: Correction de APP_URL dans render.yaml

**User Story:** En tant qu'administrateur système, je veux que la variable `APP_URL` du Backend corresponde au nom de service Render réel, afin que les liens de stockage, les emails de réinitialisation de mot de passe et les URL générées soient corrects.

#### Acceptance Criteria

1. THE `render.yaml` SHALL définir la variable d'environnement `APP_URL` avec la valeur exacte `https://mypharma-back-1.onrender.com`, correspondant au nom du service Render tel qu'il apparaît dans l'URL du tableau de bord Render.
2. WHEN le Backend appelle `config('app.url')` ou `url('/')`, THE valeur retournée SHALL être `https://mypharma-back-1.onrender.com`, lue depuis la variable d'environnement `APP_URL` définie dans Render.
3. WHEN le Backend génère un lien de réinitialisation de mot de passe (email de reset), THE lien inclus dans l'email SHALL commencer par `https://mypharma-back-1.onrender.com/` — vérifiable en inspectant le canal de log (`LOG_CHANNEL=stack`) lors d'un test de reset.

---

### Requirement 5: Ajout de APP_FRONTEND_URL dans render.yaml

**User Story:** En tant qu'administrateur système, je veux que la variable `APP_FRONTEND_URL` soit définie dans render.yaml, afin que la configuration CORS du Backend puisse utiliser dynamiquement l'URL du Frontend.

#### Acceptance Criteria

1. THE `render.yaml` SHALL définir la variable d'environnement `APP_FRONTEND_URL` avec la valeur `https://my-pharma-front.vercel.app`.
2. WHEN le Backend reçoit une requête cross-origin depuis `https://my-pharma-front.vercel.app`, THE réponse SHALL inclure `Access-Control-Allow-Origin: https://my-pharma-front.vercel.app` — valeur lue dynamiquement depuis `env('APP_FRONTEND_URL')` dans `CorsMiddleware`.
3. IF la variable d'environnement `APP_FRONTEND_URL` est absente de l'environnement Render, THEN THE Backend SHALL ne retourner aucun en-tête `Access-Control-Allow-Origin` pour les requêtes cross-origin, bloquant ainsi tout accès depuis le Frontend jusqu'à ce que la variable soit correctement configurée.

---

### Requirement 6: Configuration de SANCTUM_STATEFUL_DOMAINS dans render.yaml

**User Story:** En tant qu'utilisateur authentifié du Frontend, je veux que mes tokens Sanctum soient acceptés par le Backend en production, afin que les fonctionnalités nécessitant une authentification (commandes, profil, admin) fonctionnent correctement.

#### Acceptance Criteria

1. THE `render.yaml` SHALL définir la variable d'environnement `SANCTUM_STATEFUL_DOMAINS` avec la valeur `my-pharma-front.vercel.app` (sans préfixe de schéma `https://`), conformément au format attendu par `config/sanctum.php`.
2. WHEN le Frontend envoie une requête HTTP vers `/api/v1/user` avec un en-tête `Authorization: Bearer <token>` valide, THE Backend SHALL retourner un statut HTTP 200 avec les données de l'utilisateur authentifié — sans erreur 401 liée à un domaine non stateful.
3. THE `render.yaml` SHALL définir la variable d'environnement `SESSION_DOMAIN` avec la valeur `.onrender.com`, alignant le domaine de session Laravel avec le domaine du service Backend sur Render.
4. WHEN un utilisateur envoie une requête `POST /api/v1/login` avec des identifiants valides depuis `https://my-pharma-front.vercel.app`, THE Backend SHALL retourner un corps JSON contenant un champ `token` de type string non-vide, utilisable comme valeur d'en-tête `Authorization: Bearer <token>` dans les requêtes authentifiées suivantes sans nécessiter une nouvelle authentification.

