<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Pharmacy;
use App\Models\PharmacyInvitation;
use App\Models\Product;
use App\Models\Review;
use App\Models\Stock;
use App\Models\User;
use App\Policies\CategoryPolicy;
use App\Policies\NotificationPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PharmacyInvitationPolicy;
use App\Policies\PharmacyPolicy;
use App\Policies\ProductPolicy;
use App\Policies\ReviewPolicy;
use App\Policies\StockPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Category::class => CategoryPolicy::class,
        Notification::class => NotificationPolicy::class,
        Order::class => OrderPolicy::class,
        Payment::class => PaymentPolicy::class,
        Pharmacy::class => PharmacyPolicy::class,
        PharmacyInvitation::class => PharmacyInvitationPolicy::class,
        Product::class => ProductPolicy::class,
        Review::class => ReviewPolicy::class,
        Stock::class => StockPolicy::class,
        User::class => UserPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Gate: Admin a tous les droits
        Gate::before(function ($user, $ability) {
            if ($user->isAdmin()) {
                return true;
            }
        });

        // Gates spécifiques pour les rôles
        Gate::define('is-client', function ($user) {
            return $user->isClient();
        });

        Gate::define('is-livreur', function ($user) {
            return $user->isLivreur();
        });

        Gate::define('is-admin', function ($user) {
            return $user->isAdmin();
        });

        // Gates pour les actions spécifiques
        Gate::define('manage-orders', function ($user) {
            return $user->isAdmin() || $user->isLivreur();
        });

        Gate::define('manage-stock', function ($user) {
            return $user->isAdmin();
        });

        Gate::define('manage-users', function ($user) {
            return $user->isAdmin();
        });

        Gate::define('view-reports', function ($user) {
            return $user->isAdmin();
        });

        Gate::define('manage-settings', function ($user) {
            return $user->isAdmin();
        });
    }
}
