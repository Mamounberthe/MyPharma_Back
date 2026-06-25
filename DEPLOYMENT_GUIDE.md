# Guide de Déploiement MyPharma - Render + Vercel

## Architecture de Déploiement

- **Backend Laravel API**: Render.com (gratuit)
- **Frontend React**: Vercel (gratuit)  
- **Base de données**: PostgreSQL sur Render (gréatuit)
- **Stockage fichiers**: Render Storage (optionnel)

---

## PARTIE 1: DÉPLOIEMENT BACKEND LARAVEL SUR RENDER

### Étape 1: Préparation du Repository

1. **Créer un compte sur Render.com**
   - Allez sur https://render.com
   - Sign up avec GitHub/GitLab/Bitbucket

2. **Préparer le code Laravel**
   ```bash
   cd c:\Users\PC\Laravel\MyPharma
   
   # Commiter les changements
   git add .
   git commit -m "Add render.yaml configuration"
   
   # Push vers GitHub
   git push origin main
   ```

3. **Vérifier le fichier render.yaml**
   - Le fichier `render.yaml` est déjà créé à la racine du projet
   - Il configure automatiquement le service Laravel et la base de données

### Étape 2: Création du Service sur Render

1. **Connecter Render à GitHub**
   - Dashboard → New → Web Service
   - Connectez votre compte GitHub
   - Sélectionnez le repository `MyPharma`

2. **Configuration du service**
   - Name: `mypharma-api`
   - Region: `Oregon (us-west)` (ou la plus proche)
   - Branch: `main`
   - Runtime: `PHP` (détecté automatiquement via render.yaml)
   - Plan: `Free`

3. **Configuration avancée**
   - Build Command: (laissé vide, défini dans render.yaml)
   - Start Command: (laissé vide, défini dans render.yaml)
   - Instance Type: `Free`

4. **Créer la base de données**
   - Dashboard → New → PostgreSQL
   - Name: `mypharma-db`
   - Database: `mypharma`
   - User: `mypharma_user`
   - Region: `Oregon (us-west)` (même région que le service)
   - Plan: `Free`

### Étape 3: Variables d'Environnement

Render va automatiquement configurer les variables définies dans `render.yaml`, mais vous pouvez les ajuster:

**Variables requises (automatiques via render.yaml):**
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_KEY=généré automatiquement`
- `APP_URL=https://mypharma-api.onrender.com`
- `DB_CONNECTION=pgsql`
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (liés à la DB)

**Variables supplémentaires (optionnelles):**
- `MAIL_MAILER=log` (pour les emails en dev)
- `CACHE_DRIVER=file`
- `SESSION_DRIVER=file`

### Étape 4: Déploiement

1. **Lancer le déploiement**
   - Cliquez sur "Create Web Service"
   - Render va automatiquement:
     - Installer les dépendances Composer
     - Générer la clé APP
     - Créer le lien storage
     - Exécuter les migrations
     - Démarrer le serveur

2. **Surveiller le déploiement**
   - Onglet "Logs" pour voir la progression
   - Le déploiement prend ~5-10 minutes

3. **Vérifier le déploiement**
   - Une fois terminé, vous aurez une URL comme: `https://mypharma-api.onrender.com`
   - Testez: `https://mypharma-api.onrender.com/api/v1/products`

### Étape 5: Configuration CORS

Ajoutez l'URL Vercel aux CORS dans Laravel:

**Dans `config/cors.php`:**
```php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'allowed_origins' => [
    'https://mypharma-frontend.vercel.app',
    'http://localhost:3001',
],
```

---

## PARTIE 2: DÉPLOIEMENT FRONTEND REACT SUR VERCEL

### Étape 1: Préparation du Repository

1. **Créer un compte sur Vercel**
   - Allez sur https://vercel.com
   - Sign up avec GitHub

2. **Préparer le code React**
   ```bash
   cd c:\Users\PC\Laravel\mypharma-frontend
   
   # Mettre à jour l'URL API dans .env
   echo "VITE_API_URL=https://mypharma-api.onrender.com" > .env
   
   # Commiter
   git add .
   git commit -m "Add vercel.json and update API URL"
   git push origin main
   ```

3. **Vérifier le fichier vercel.json**
   - Le fichier `vercel.json` est déjà créé
   - Il configure le build et les routes SPA

### Étape 2: Création du Projet sur Vercel

1. **Importer le projet**
   - Dashboard → Add New → Project
   - Sélectionnez le repository `mypharma-frontend`
   - Cliquez "Import"

2. **Configuration du projet**
   - Framework Preset: `Vite` (détecté automatiquement)
   - Project Name: `mypharma-frontend`
   - Root Directory: `./`
   - Build Command: `npm run build`
   - Output Directory: `dist`

3. **Variables d'environnement**
   - Ajoutez: `VITE_API_URL=https://mypharma-api.onrender.com`
   - Cliquez "Add"

4. **Déployer**
   - Cliquez "Deploy"
   - Vercel va build et déployer en ~2-3 minutes

### Étape 3: Vérification du Déploiement

1. **URL de production**
   - Vous aurez une URL comme: `https://mypharma-frontend.vercel.app`
   - Testez l'accès à l'application

2. **Domaine personnalisé (optionnel)**
   - Settings → Domains
   - Ajoutez votre domaine (ex: mypharma.com)
   - Configurez les DNS selon les instructions Vercel

---

## PARTIE 3: CONFIGURATION FINALE

### Mise à jour des URLs

1. **Mettre à jour l'URL frontend dans Render**
   - Render Dashboard → mypharma-api → Environment
   - Ajoutez: `APP_FRONTEND_URL=https://mypharma-frontend.vercel.app`

2. **Mettre à jour CORS dans Laravel**
   - Modifiez `config/cors.php` pour inclure l'URL Vercel

### Test de l'Application Complète

1. **Test API**
   ```bash
   curl https://mypharma-api.onrender.com/api/v1/products
   ```

2. **Test Frontend**
   - Ouvrez `https://mypharma-frontend.vercel.app`
   - Testez la navigation, le panier, les commandes

3. **Test Upload Images**
   - Testez l'upload d'image produit dans l'admin
   - Vérifiez que l'image s'affiche correctement

---

## PARTIE 4: MAINTENANCE

### Mises à jour

**Pour mettre à jour le backend:**
```bash
cd c:\Users\PC\Laravel\MyPharma
git add .
git commit -m "Update backend"
git push origin main
# Render déploie automatiquement
```

**Pour mettre à jour le frontend:**
```bash
cd c:\Users\PC\Laravel\mypharma-frontend
git add .
git commit -m "Update frontend"
git push origin main
# Vercel déploie automatiquement
```

### Monitoring

- **Render Logs**: Dashboard → mypharma-api → Logs
- **Vercel Logs**: Dashboard → mypharma-frontend → Logs
- **Base de données**: Render Dashboard → mypharma-db

### Limitations Gratuites

**Render Free Tier:**
- 512MB RAM
- 750 heures/mois
- Sleep après 15 min d'inactivité (réveille en ~30 sec)
- Base de données: 1GB PostgreSQL

**Vercel Free Tier:**
- Bandwidth illimité
- 100GB logs/mois
- Build illimité
- Pas de timeout

---

## DÉPANNAGE

### Problèmes Courants

**1. Erreur 502 sur Render**
- Vérifiez les logs dans Render Dashboard
- Assurez-vous que les migrations ont réussi
- Vérifiez les variables d'environnement

**2. Images ne s'affichent pas**
- Vérifiez que `APP_URL` est correct dans Render
- Assurez-vous que le lien storage existe: `php artisan storage:link`

**3. CORS errors**
- Vérifiez `config/cors.php`
- Assurez-vous que l'URL Vercel est dans `allowed_origins`

**4. Base de données inaccessible**
- Vérifiez que la DB est dans la même région que le service
- Redémarrez le service Render

---

## SÉCURITÉ

### Recommandations

1. **Ne jamais commiter de secrets**
   - Utilisez les variables d'environnement
   - Ajoutez `.env` à `.gitignore`

2. **Activer HTTPS**
   - Render et Vercel fournissent SSL automatiquement

3. **Limiter les taux**
   - Laravel a déjà `throttle` middleware dans `api.php`

4. **Sanctum Tokens**
   - Les tokens sont stockés en base de données PostgreSQL

---

## COÛTS

**Gratuit (0$/mois):**
- Render: Backend + PostgreSQL
- Vercel: Frontend
- Domaine: mypharma-api.onrender.com, mypharma-frontend.vercel.app

**Optionnel (si besoin de plus de ressources):**
- Render Starter: 7$/mois
- Vercel Pro: 20$/mois
- Domaine personnalisé: ~10$/an

---

## SUPPORT

- **Render Docs**: https://render.com/docs
- **Vercel Docs**: https://vercel.com/docs
- **Laravel Deployment**: https://laravel.com/docs/deployment
