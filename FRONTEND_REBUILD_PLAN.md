# FRONTEND_REBUILD_PLAN.md - Plan de Reconstruction Frontend

## 🎯 OBJECTIF

Créer un frontend React moderne, propre et scalable pour MyPharma qui consomme les APIs Laravel existantes.

---

## 1. DÉCISION ARCHITECTURALE

### ❌ POURQUOI PAS RÉPARER L'EXISTANT
- Conflits de versions React bloquants
- Dépendances Expo cassées
- Vulnérabilités sécurité (13 high)
- Plugins Babel dépréciés
- Configuration incorrecte
- Code avec beaucoup de `any`

### ✅ POURQUOI NOUVEAU FRONTEND REACT WEB
- Stack moderne et stable
- TypeScript strict
- Développement plus rapide
- Meilleure maintenabilité
- Compatible avec backend Laravel
- Facile à déployer
- Performance optimale

### 📱 MOBILE FUTUR
Si mobile est nécessaire plus tard:
- Créer projet React Native séparé
- Partager logique API
- Réutiliser composants UI
- Backend Laravel déjà prêt

---

## 2. STACK TECHNIQUE CHOISIE

### Core
```json
{
  "react": "^18.3.1",
  "react-dom": "^18.3.1",
  "typescript": "^5.3.3",
  "vite": "^5.0.12"
}
```

### Routing
```json
{
  "react-router-dom": "^6.22.0"
}
```

### State Management
```json
{
  "@tanstack/react-query": "^5.28.0",
  "zustand": "^4.5.0"
}
```

### HTTP Client
```json
{
  "axios": "^1.6.7"
}
```

### Forms & Validation
```json
{
  "react-hook-form": "^7.50.0",
  "zod": "^3.22.4",
  "@hookform/resolvers": "^3.3.4"
}
```

### UI Framework
```json
{
  "tailwindcss": "^3.4.1",
  "clsx": "^2.1.0",
  "tailwind-merge": "^2.2.1",
  "lucide-react": "^0.344.0"
}
```

### Maps & Geolocation
```json
{
  "leaflet": "^1.9.4",
  "react-leaflet": "^4.2.1"
}
```

### Date Handling
```json
{
  "date-fns": "^3.3.1"
}
```

---

## 3. STRUCTURE DU PROJET

### Arborescence Complète
```
mypharma-frontend/
├── public/
│   └── vite.svg
├── src/
│   ├── api/
│   │   ├── client.ts              # Axios configuré avec interceptors
│   │   ├── auth.api.ts           # Endpoints authentification
│   │   ├── pharmacy.api.ts       # Endpoints pharmacies
│   │   ├── product.api.ts        # Endpoints produits
│   │   ├── order.api.ts          # Endpoints commandes
│   │   ├── review.api.ts         # Endpoints avis
│   │   └── index.ts              # Export toutes APIs
│   │
│   ├── services/
│   │   ├── authService.ts        # Logique auth (login, register, logout)
│   │   ├── pharmacyService.ts    # Logique pharmacies
│   │   ├── productService.ts     # Logique produits
│   │   ├── orderService.ts       # Logique commandes
│   │   ├── reviewService.ts      # Logique avis
│   │   ├── paymentService.ts     # Logique paiements
│   │   └── notificationService.ts # Logique notifications
│   │
│   ├── hooks/
│   │   ├── useAuth.ts            # Hook authentification
│   │   ├── usePharmacies.ts      # Hook pharmacies
│   │   ├── useProducts.ts        # Hook produits
│   │   ├── useOrders.ts          # Hook commandes
│   │   ├── useLocation.ts        # Hook géolocalisation
│   │   ├── useCart.ts            # Hook panier
│   │   └── useDebounce.ts        # Hook debounce utilitaire
│   │
│   ├── store/
│   │   ├── authStore.ts          # Store auth (Zustand)
│   │   ├── cartStore.ts          # Store panier
│   │   ├── uiStore.ts            # Store UI (modals, toasts)
│   │   └── index.ts              # Export stores
│   │
│   ├── pages/
│   │   ├── auth/
│   │   │   ├── LoginPage.tsx
│   │   │   ├── RegisterPage.tsx
│   │   │   └── ForgotPasswordPage.tsx
│   │   │
│   │   ├── home/
│   │   │   └── HomePage.tsx
│   │   │
│   │   ├── pharmacies/
│   │   │   ├── PharmaciesListPage.tsx
│   │   │   ├── PharmacyDetailPage.tsx
│   │   │   └── PharmacyMapPage.tsx
│   │   │
│   │   ├── products/
│   │   │   ├── ProductsListPage.tsx
│   │   │   ├── ProductDetailPage.tsx
│   │   │   └── ProductSearchPage.tsx
│   │   │
│   │   ├── orders/
│   │   │   ├── OrdersListPage.tsx
│   │   │   ├── OrderDetailPage.tsx
│   │   │   ├── CartPage.tsx
│   │   │   └── CheckoutPage.tsx
│   │   │
│   │   ├── profile/
│   │   │   ├── ProfilePage.tsx
│   │   │   └── ProfileEditPage.tsx
│   │   │
│   │   └── admin/
│   │       ├── DashboardPage.tsx
│   │       ├── UsersManagePage.tsx
│   │       ├── PharmaciesManagePage.tsx
│   │       └── InvitationsPage.tsx
│   │
│   ├── layouts/
│   │   ├── MainLayout.tsx         # Layout principal avec navbar
│   │   ├── AuthLayout.tsx         # Layout pages auth
│   │   └── AdminLayout.tsx       # Layout admin
│   │
│   ├── components/
│   │   ├── ui/                    # Composants UI réutilisables
│   │   │   ├── Button.tsx
│   │   │   ├── Input.tsx
│   │   │   ├── Card.tsx
│   │   │   ├── Modal.tsx
│   │   │   ├── Badge.tsx
│   │   │   ├── Loading.tsx
│   │   │   ├── Toast.tsx
│   │   │   ├── Select.tsx
│   │   │   ├── Textarea.tsx
│   │   │   └── Checkbox.tsx
│   │   │
│   │   ├── forms/                 # Composants formulaires
│   │   │   ├── LoginForm.tsx
│   │   │   ├── RegisterForm.tsx
│   │   │   ├── SearchForm.tsx
│   │   │   └── ReviewForm.tsx
│   │   │
│   │   ├── pharmacy/              # Composants pharmacies
│   │   │   ├── PharmacyCard.tsx
│   │   │   ├── PharmacyList.tsx
│   │   │   ├── PharmacyMap.tsx
│   │   │   ├── PharmacyFilters.tsx
│   │   │   └── PharmacyReviews.tsx
│   │   │
│   │   ├── products/              # Composants produits
│   │   │   ├── ProductCard.tsx
│   │   │   ├── ProductList.tsx
│   │   │   ├── ProductSearch.tsx
│   │   │   └── ProductFilters.tsx
│   │   │
│   │   ├── orders/                # Composants commandes
│   │   │   ├── OrderCard.tsx
│   │   │   ├── OrderList.tsx
│   │   │   ├── OrderTimeline.tsx
│   │   │   ├── CartItem.tsx
│   │   │   └── CartSummary.tsx
│   │   │
│   │   ├── maps/                  # Composants cartes
│   │   │   ├── MapContainer.tsx
│   │   │   ├── PharmacyMarker.tsx
│   │   │   └── UserLocationMarker.tsx
│   │   │
│   │   └── common/                # Composants communs
│   │       ├── Header.tsx
│   │       ├── Footer.tsx
│   │       ├── Navbar.tsx
│   │       ├── Sidebar.tsx
│   │       ├── Breadcrumb.tsx
│   │       ├── Pagination.tsx
│   │       └── EmptyState.tsx
│   │
│   ├── features/
│   │   ├── auth/                  # Feature auth
│   │   │   ├── AuthGuard.tsx
│   │   │   ├── RequireAuth.tsx
│   │   │   └── RequireRole.tsx
│   │   │
│   │   ├── pharmacies/            # Feature pharmacies
│   │   │   └── PharmacyProvider.tsx
│   │   │
│   │   └── orders/                # Feature commandes
│   │       └── OrderProvider.tsx
│   │
│   ├── types/
│   │   ├── api.types.ts           # Types API responses
│   │   ├── models.types.ts        # Types modèles
│   │   ├── forms.types.ts         # Types formulaires
│   │   └── index.ts               # Export types
│   │
│   ├── utils/
│   │   ├── cn.ts                  # Classname merger
│   │   ├── validation.ts          # Fonctions validation
│   │   ├── formatters.ts          # Formateurs (date, currency)
│   │   ├── constants.ts           # Constantes
│   │   └── helpers.ts             # Helpers divers
│   │
│   ├── constants/
│   │   ├── api.constants.ts       # URLs API
│   │   ├── routes.constants.ts     # Routes
│   │   ├── status.constants.ts    # Statuts
│   │   └── roles.constants.ts     # Rôles
│   │
│   ├── contexts/
│   │   ├── AuthContext.tsx        # Context auth
│   │   ├── ThemeContext.tsx       # Context thème
│   │   └── NotificationContext.tsx # Context notifications
│   │
│   ├── assets/
│   │   ├── images/
│   │   ├── icons/
│   │   └── fonts/
│   │
│   ├── styles/
│   │   └── globals.css            # Styles globaux
│   │
│   ├── App.tsx                    # Composant racine
│   ├── main.tsx                   # Point d'entrée
│   └── vite-env.d.ts              # Types Vite
│
├── index.html
├── package.json
├── tsconfig.json
├── tsconfig.node.json
├── vite.config.ts
├── tailwind.config.js
├── postcss.config.js
├── .env.example
├── .env
├── .gitignore
└── README.md
```

---

## 4. CONFIGURATION TYPESCRIPT

### tsconfig.json
```json
{
  "compilerOptions": {
    "target": "ES2020",
    "useDefineForClassFields": true,
    "lib": ["ES2020", "DOM", "DOM.Iterable"],
    "module": "ESNext",
    "skipLibCheck": true,
    "moduleResolution": "bundler",
    "allowImportingTsExtensions": true,
    "resolveJsonModule": true,
    "isolatedModules": true,
    "noEmit": true,
    "jsx": "react-jsx",
    "strict": true,
    "noUnusedLocals": true,
    "noUnusedParameters": true,
    "noFallthroughCasesInSwitch": true,
    "baseUrl": "./src",
    "paths": {
      "@/*": ["*"],
      "@/api/*": ["api/*"],
      "@/components/*": ["components/*"],
      "@/pages/*": ["pages/*"],
      "@/hooks/*": ["hooks/*"],
      "@/store/*": ["store/*"],
      "@/types/*": ["types/*"],
      "@/utils/*": ["utils/*"],
      "@/constants/*": ["constants/*"]
    }
  },
  "include": ["src"],
  "references": [{ "path": "./tsconfig.node.json" }]
}
```

---

## 5. CONFIGURATION TAILWINDCSS

### tailwind.config.js
```javascript
/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        primary: {
          50: '#eff6ff',
          100: '#dbeafe',
          200: '#bfdbfe',
          300: '#93c5fd',
          400: '#60a5fa',
          500: '#3b82f6',
          600: '#2563eb',
          700: '#1d4ed8',
          800: '#1e40af',
          900: '#1e3a8a',
        },
        medical: {
          50: '#f0fdf4',
          100: '#dcfce7',
          200: '#bbf7d0',
          300: '#86efac',
          400: '#4ade80',
          500: '#22c55e',
          600: '#16a34a',
          700: '#15803d',
          800: '#166534',
          900: '#14532d',
        }
      },
      fontFamily: {
        sans: ['Inter', 'sans-serif'],
      }
    },
  },
  plugins: [],
}
```

---

## 6. CONFIGURATION AXIOS

### src/api/client.ts
```typescript
import axios from 'axios';
import type { AxiosError, AxiosInstance } from 'axios';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api/v1';

class ApiClient {
  private client: AxiosInstance;

  constructor() {
    this.client = axios.create({
      baseURL: API_BASE_URL,
      timeout: 10000,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    });

    this.setupInterceptors();
  }

  private setupInterceptors() {
    // Request interceptor
    this.client.interceptors.request.use(
      (config) => {
        const token = localStorage.getItem('auth_token');
        if (token) {
          config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
      },
      (error) => Promise.reject(error)
    );

    // Response interceptor
    this.client.interceptors.response.use(
      (response) => response,
      async (error: AxiosError) => {
        const originalRequest = error.config;

        if (error.response?.status === 401 && originalRequest) {
          // Token expired, try refresh
          const refreshToken = localStorage.getItem('refresh_token');
          if (refreshToken) {
            try {
              const response = await axios.post(`${API_BASE_URL}/refresh`, {
                refresh_token: refreshToken,
              });
              const { token } = response.data;
              localStorage.setItem('auth_token', token);
              originalRequest.headers.Authorization = `Bearer ${token}`;
              return this.client(originalRequest);
            } catch (refreshError) {
              // Refresh failed, logout
              localStorage.removeItem('auth_token');
              localStorage.removeItem('refresh_token');
              window.location.href = '/login';
            }
          } else {
            window.location.href = '/login';
          }
        }

        return Promise.reject(error);
      }
    );
  }

  public getInstance(): AxiosInstance {
    return this.client;
  }
}

export const apiClient = new ApiClient().getInstance();
export default apiClient;
```

---

## 7. TYPES TYPESCRIPT

### src/types/models.types.ts
```typescript
// User
export interface User {
  id: number;
  name: string;
  email: string;
  role: 'admin' | 'client' | 'livreur';
  created_at: string;
  updated_at: string;
}

// Pharmacy
export interface Pharmacy {
  id: number;
  name: string;
  address: string;
  location: {
    latitude: number;
    longitude: number;
  };
  phone: string;
  rating: number;
  delivery_available: boolean;
  is_on_call: boolean;
  distance?: number;
  reviews_count?: number;
  created_at: string;
  updated_at: string;
}

// Product
export interface Product {
  id: number;
  name: string;
  description: string;
  category: {
    id: number;
    name: string;
  };
  created_at: string;
  updated_at: string;
}

// Order
export interface Order {
  id: number;
  status: 'pending' | 'confirmed' | 'delivering' | 'delivered' | 'cancelled';
  total_price: number;
  delivery_address: string;
  delivered_at?: string;
  created_at: string;
  updated_at: string;
  pharmacy: {
    id: number;
    name: string;
    address: string;
    phone: string;
  };
  items: OrderItem[];
  items_count?: number;
  can_cancel: boolean;
  can_review: boolean;
}

export interface OrderItem {
  id: number;
  product: {
    id: number;
    name: string;
    category: string;
  };
  quantity: number;
  unit_price: number;
  subtotal: number;
}

// Review
export interface Review {
  id: number;
  rating: number;
  comment: string;
  user: {
    id: number;
    name: string;
  };
  created_at: string;
}

// Cart Item
export interface CartItem {
  product_id: number;
  product: Product;
  quantity: number;
  price: number;
  pharmacy_id: number;
}
```

### src/types/api.types.ts
```typescript
import type { User, Pharmacy, Product, Order, Review } from './models.types';

// API Response Wrapper
export interface ApiResponse<T> {
  data: T;
  message?: string;
}

// Paginated Response
export interface PaginatedResponse<T> {
  data: T[];
  pagination: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
  };
}

// Auth Response
export interface AuthResponse {
  user: User;
  token: string;
  token_type: string;
}

// Search Response
export interface SearchResponse {
  query: string;
  results: SearchResult[];
  total_results: number;
  pagination: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface SearchResult {
  product: Product;
  available_pharmacies: PharmacyStock[];
  total_pharmacies: number;
  min_price: number;
  max_price: number;
}

export interface PharmacyStock {
  pharmacy: Pharmacy;
  stock: {
    quantity: number;
    price: number;
  };
}

// Order Tracking Response
export interface OrderTrackingResponse {
  order_id: number;
  current_status: string;
  status_label: string;
  is_terminal: boolean;
  estimated_delivery?: string;
  minutes_remaining: number;
  steps: TrackingStep[];
  pharmacy: {
    id: number;
    name: string;
    address: string;
    phone: string;
  };
  items_count: number;
  last_updated: string;
}

export interface TrackingStep {
  status: string;
  label: string;
  icon: string;
  state: 'done' | 'active' | 'pending';
  completed: boolean;
}
```

---

## 8. PLAN D'IMPLÉMENTATION

### PHASE 1: Initialisation (30 min)
1. Créer projet Vite + React + TypeScript
2. Installer dépendances core
3. Configurer TypeScript strict
4. Configurer TailwindCSS
5. Configurer Vite
6. Créer structure de dossiers
7. Configurer path aliases (@/*)

### PHASE 2: Configuration API (1h)
1. Créer client Axios avec interceptors
2. Créer types TypeScript
3. Créer constantes API
4. Créer services API de base
5. Tester connexion backend

### PHASE 3: Authentification (2h)
1. Créer store auth (Zustand)
2. Créer hooks useAuth
3. Créer pages login/register
4. Créer layouts auth
5. Implémenter guards
6. Tester flow auth complet

### PHASE 4: Layout & Navigation (1h)
1. Créer MainLayout
2. Créer Navbar
3. Créer Sidebar
4. Configurer React Router
5. Créer routes protégées
6. Créer routes publiques

### PHASE 5: Pharmacies (3h)
1. Créer service pharmacy
2. Créer hooks usePharmacies
3. Créer composants UI pharmacies
4. Créer page liste pharmacies
5. Créer page détail pharmacie
6. Intégrer carte Leaflet
7. Implémenter filtres
8. Tester recherche

### PHASE 6: Produits (2h)
1. Créer service product
2. Créer hooks useProducts
3. Créer composants UI produits
4. Créer page liste produits
5. Créer page détail produit
6. Implémenter recherche avancée
7. Tester filtres

### PHASE 7: Commandes (3h)
1. Créer store cart
2. Créer service order
3. Créer hooks useOrders
4. Créer composants UI commandes
5. Créer page panier
6. Créer page checkout
7. Créer page liste commandes
8. Créer page détail commande
9. Implémenter tracking
10. Tester flow complet

### PHASE 8: Profil & Admin (2h)
1. Créer page profil
2. Créer page édition profil
3. Créer layout admin
4. Créer dashboard admin
5. Créer page gestion utilisateurs
6. Créer page gestion pharmacies
7. Tester permissions

### PHASE 9: Optimisation (1h)
1. Lazy loading routes
2. Code splitting
3. Optimiser images
4. Configurer cache React Query
5. Optimiser bundle size

### PHASE 10: Tests & Déploiement (1h)
1. Tester toutes les features
2. Corriger bugs
3. Optimiser performance
4. Préparer build production
5. Configurer déploiement

**Total estimé**: ~17 heures de développement

---

## 9. PRIORITÉS D'IMPLÉMENTATION

### P0 (Critique - Jour 1)
- ✅ Configuration projet
- ✅ Client Axios
- ✅ Authentification
- ✅ Layout principal
- ✅ Navigation

### P1 (Haute - Jour 2)
- ✅ Pharmacies (liste + détail)
- ✅ Carte interactive
- ✅ Produits (liste + détail)
- ✅ Recherche produits

### P2 (Moyenne - Jour 3)
- ✅ Panier
- ✅ Checkout
- ✅ Commandes (liste + détail)
- ✅ Tracking commande

### P3 (Basse - Jour 4)
- ✅ Profil utilisateur
- ✅ Avis et notations
- ✅ Dashboard admin
- ✅ Gestion utilisateurs

---

## 10. ENVIRONNEMENT

### Variables d'Environnement (.env)
```env
VITE_API_BASE_URL=http://localhost:8000/api/v1
VITE_APP_NAME=MyPharma
VITE_APP_VERSION=1.0.0
```

### .env.example
```env
VITE_API_BASE_URL=http://localhost:8000/api/v1
VITE_APP_NAME=MyPharma
VITE_APP_VERSION=1.0.0
```

---

## 11. DESIGN SYSTEM

### Couleurs
- Primary: Blue (#3b82f6)
- Medical: Green (#22c55e)
- Success: Green
- Warning: Yellow
- Error: Red
- Neutral: Gray scale

### Typography
- Font: Inter
- Sizes: 12px, 14px, 16px, 18px, 20px, 24px, 32px
- Weights: 400, 500, 600, 700

### Spacing
- Base: 4px (0.25rem)
- Scale: 4, 8, 12, 16, 20, 24, 32, 40, 48, 64, 80

### Components
- Buttons: Primary, Secondary, Ghost, Danger
- Cards: Default, Elevated, Bordered
- Inputs: Text, Email, Password, Number, Select
- Modals: Default, Fullscreen
- Toasts: Success, Error, Warning, Info

---

## 12. PERFORMANCE

### Optimisations
- Lazy loading des routes
- Code splitting par feature
- Images optimisées (WebP)
- Cache React Query configuré
- Debounce sur les recherches
- Virtual scroll pour grandes listes
- Memoization des composants

### Monitoring
- React Query DevTools
- Performance monitoring
- Error tracking (Sentry optionnel)

---

## 13. SÉCURITÉ

### Mesures
- Token stocké en localStorage (ou httpOnly cookie)
- Refresh token automatique
- CSRF protection
- XSS protection (React natif)
- Validation côté client
- Sanitization inputs

---

## 14. ACCESSIBILITÉ

### Standards
- WCAG 2.1 AA
- Navigation clavier
- Screen reader support
- Contraste colors
- Focus visible
- Alt text images

---

## 15. TESTING

### Tests Unitaires
- Composants UI
- Hooks personnalisés
- Utils helpers
- Services API

### Tests Integration
- Flows auth
- Flows commandes
- Recherche produits

### Tests E2E (optionnel)
- Playwright ou Cypress

---

## 16. DÉPLOIEMENT

### Options
- Vercel (recommandé)
- Netlify
- Docker
- VPS traditionnel

### Configuration Build
```bash
npm run build
```

### Environment Variables
- Configurer dans plateforme hosting
- Ne jamais commit .env

---

## 17. DOCUMENTATION

### À Créer
- README.md avec instructions setup
- CONTRIBUTING.md
- ARCHITECTURE.md
- API_INTEGRATION.md
- DEPLOYMENT.md

---

## 18. CONCLUSION

Ce plan fournit une roadmap complète pour reconstruire le frontend MyPharma de manière professionnelle, moderne et scalable.

### Points Clés
- ✅ Architecture clean et modulaire
- ✅ TypeScript strict
- ✅ Performance optimisée
- ✅ UI moderne avec Tailwind
- ✅ Intégration backend Laravel
- ✅ Sécurité robuste
- ✅ Accessibilité
- ✅ Maintenabilité long terme

### Prochaine Étape
Commencer l'implémentation selon ce plan, phase par phase.
