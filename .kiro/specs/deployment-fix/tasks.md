# Implementation Plan: deployment-fix

## Overview

Six correctifs de configuration doivent être appliqués pour rétablir le fonctionnement complet de MyPharma en production : correction du CORS côté backend, création d'un script de démarrage Docker avec migration automatique, mise à jour du Dockerfile et de `render.yaml`, idempotence des seeders, et correction du client Axios frontend.

## Tasks

- [x] 1. Corriger CorsMiddleware
  - [x] 1.1 Réécrire `app/Http/Middleware/CorsMiddleware.php`
    - Ajouter la méthode privée `getAllowedOrigins(): array` qui lit `APP_FRONTEND_URL` via `env('APP_FRONTEND_URL')` et inclut toujours `http://localhost:3001`
    - Supprimer le fallback `$request->header('Origin') ?: '*'` — remplacer par une vérification stricte `in_array($origin, $allowedOrigins, true)`
    - Si l'origin est inconnue et que la méthode est `OPTIONS`, retourner `response('', 204)` sans en-têtes CORS
    - Si l'origin est inconnue et que la méthode n'est pas `OPTIONS`, appeler `$next($request)` sans ajouter d'en-têtes CORS
    - Pour les requêtes `OPTIONS` avec origin autorisée, retourner `response('', 200, $headers)` incluant `Access-Control-Max-Age: 86400`
    - Pour les autres méthodes avec origin autorisée, appeler `$next($request)` puis ajouter les en-têtes CORS sur la réponse
    - _Requirements: 3.1, 3.2, 3.3, 3.5, 3.6, 5.2, 5.3_
  - [ ]* 1.2 Écrire les tests unitaires PHPUnit pour CorsMiddleware (Property 1, 2, 3)
    - Créer `tests/Unit/CorsMiddlewareTest.php`
    - **U1** : Requête `OPTIONS` avec origin autorisée → HTTP 200 + tous les en-têtes CORS présents
    - **U2** : Requête `GET` avec `APP_FRONTEND_URL` défini → `Access-Control-Allow-Origin` === valeur exacte de `APP_FRONTEND_URL`
    - **U3** : Requête `GET` avec `APP_FRONTEND_URL` absent → aucun en-tête `Access-Control-Allow-Origin`
    - **Property 1** : itérer sur `[APP_FRONTEND_URL, 'http://localhost:3001']` et vérifier `Access-Control-Allow-Origin === $origin`
    - **Property 2** : pour chaque origine autorisée, vérifier `Access-Control-Allow-Credentials: true`
    - **Property 3** : tester avec origines arbitraires (ex. `https://evil.com`) → absence de l'en-tête
    - Annoter chaque propriété avec `// Feature: deployment-fix, Property {N}: {texte}`
    - _Requirements: 3.1, 3.2, 3.5, 5.2_

- [x] 2. Corriger `config/cors.php`
  - [x] 2.1 Mettre `allowed_origins` à `[]` dans `config/cors.php`
    - Remplacer `'allowed_origins' => ['*']` par `'allowed_origins' => []`
    - Ajouter un commentaire inline : `// Non utilisé — CorsMiddleware gère le CORS`
    - Aligner `allowed_headers` avec la liste exacte : `['Content-Type', 'Authorization', 'Accept', 'Origin', 'X-Requested-With']`
    - Remplacer `'allowed_methods' => ['*']` par `['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']`
    - Mettre `'max_age' => 86400`
    - _Requirements: 3.4_

- [x] 3. Créer le script de démarrage Docker
  - [x] 3.1 Créer `docker/start.sh`
    - Créer le fichier `docker/start.sh` (créer le dossier `docker/` si absent)
    - Commencer par `#!/bin/sh` puis `set -e`
    - Ajouter `echo "[start.sh] Running migrations..."` puis `php artisan migrate --force`
    - Ajouter le bloc conditionnel `RUN_SEEDS=true` → `php artisan db:seed --force`
    - Ajouter `echo "[start.sh] Starting web server..."` puis `exec /usr/bin/supervisord`
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5_

- [x] 4. Mettre à jour le Dockerfile
  - [x] 4.1 Modifier `Dockerfile` pour utiliser `docker/start.sh`
    - Ajouter `COPY docker/start.sh /usr/local/bin/start.sh`
    - Ajouter `RUN chmod +x /usr/local/bin/start.sh`
    - Remplacer le `CMD` final par `CMD ["/usr/local/bin/start.sh"]`
    - _Requirements: 2.1, 2.3_

- [x] 5. Mettre à jour `render.yaml`
  - [x] 5.1 Corriger et compléter les variables d'environnement dans `render.yaml`
    - Changer `APP_URL` vers `https://mypharma-back-1.onrender.com`
    - Ajouter `APP_FRONTEND_URL` avec la valeur `https://my-pharma-front.vercel.app`
    - Ajouter `SANCTUM_STATEFUL_DOMAINS` avec la valeur `my-pharma-front.vercel.app` (sans préfixe de schéma)
    - Ajouter `SESSION_DOMAIN` avec la valeur `.onrender.com`
    - Ajouter un commentaire YAML pour `RUN_SEEDS=true` (à définir manuellement si nécessaire)
    - _Requirements: 4.1, 4.2, 5.1, 6.1, 6.3_

- [x] 6. Rendre les seeders idempotents
  - [x] 6.1 Refactoriser `database/seeders/DatabaseSeeder.php` pour utiliser `firstOrCreate` / `updateOrCreate`
    - Remplacer chaque `Category::create([...])` par `Category::firstOrCreate(['name' => $name], $data)`
    - Remplacer chaque `User::create([...])` par `User::firstOrCreate(['email' => $email], $data)`
    - Remplacer chaque `Pharmacy::create([...])` par `Pharmacy::firstOrCreate(['name' => $name, 'address' => $addr], $data)`
    - Remplacer chaque `Product::create([...])` par `Product::firstOrCreate(['name' => $name, 'category_id' => $catId], $data)`
    - Pour les stocks : `Stock::updateOrCreate(['pharmacy_id' => $pid, 'product_id' => $prodId], ['quantity' => ..., 'price' => ...])`
    - Supprimer la création de commandes/OrderItems du seeder (données transactionnelles non idempotentes)
    - _Requirements: 2.6_
  - [ ]* 6.2 Écrire le test d'idempotence des seeders (Property 4)
    - Créer `tests/Feature/SeederIdempotencyTest.php`
    - Utiliser `RefreshDatabase` pour une base propre
    - Exécuter `db:seed --force` deux fois et vérifier que les counts sont identiques
    - Annoter avec `// Feature: deployment-fix, Property 4: Seeders idempotents — pas de doublons`
    - _Requirements: 2.6_

- [x] 7. Corriger le client Axios frontend
  - [x] 7.1 Mettre à jour le fichier de configuration Axios dans le projet frontend
    - Localiser le fichier de configuration Axios (ex. `src/api/axios.js`, `src/api/client.ts`, `src/lib/api.ts`)
    - Supprimer tout fallback `|| 'http://localhost:8000/api/v1'`
    - Lire exclusivement : `const apiBaseUrl = import.meta.env.VITE_API_BASE_URL;`
    - Ajouter le warning : `if (!apiBaseUrl) { console.warn('[MyPharma] VITE_API_BASE_URL is not defined...'); }`
    - Passer `baseURL: apiBaseUrl` sans fallback
    - _Requirements: 1.1, 1.3, 1.6_
  - [ ]* 7.2 Écrire les tests Vitest pour le client Axios
    - Créer `src/api/__tests__/client.test.ts`
    - **F1** : `VITE_API_BASE_URL` défini → `apiClient.defaults.baseURL` correspond
    - **F2** : `VITE_API_BASE_URL` absent → `console.warn` appelé
    - **F3** : `VITE_API_BASE_URL` absent → `baseURL === undefined`
    - _Requirements: 1.1, 1.3_

- [x] 8. Mettre à jour `.env.example` du Frontend
  - [x] 8.1 Modifier le fichier `.env.example` dans le projet frontend (React/Vite)
    - S'assurer que la valeur par défaut est la valeur de production : `VITE_API_BASE_URL=https://mypharma-back-1.onrender.com/api/v1`
    - Ajouter en dessous la valeur de développement commentée : `# VITE_API_BASE_URL=http://localhost:8000/api/v1`
    - _Requirements: 1.5_

- [ ] 9. Checkpoint final — Ensure all tests pass, ask the user if questions arise.

## Notes

- Les tâches marquées `*` sont optionnelles (tests) et peuvent être sautées pour un déploiement rapide.
- Les tasks 3 et 4 (start.sh + Dockerfile) doivent être faites ensemble — un start.sh sans mise à jour du CMD est sans effet.
- La task 6.1 supprime intentionnellement la création de commandes/OrderItems du seeder (données transactionnelles non idempotentes).
- Le client Axios frontend (task 7) se réfère au projet React/Vite séparé — vérifier le chemin exact du fichier api/axios.

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "2.1", "3.1", "5.1", "6.1", "7.1", "8.1"] },
    { "id": 1, "tasks": ["4.1"] },
    { "id": 2, "tasks": ["1.2", "6.2", "7.2"] },
    { "id": 3, "tasks": ["9"] }
  ]
}
```
