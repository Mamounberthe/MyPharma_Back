<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use App\Models\Pharmacy;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Review;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Création des catégories
        $categories = [
            ['name' => 'Médicaments'],
            ['name' => 'Vitamines'],
            ['name' => 'Produits de soin'],
            ['name' => 'Équipement médical'],
            ['name' => 'Produits bébé']
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }

        // Création des utilisateurs
        $users = [
            [
                'name' => 'Admin User',
                'email' => 'admin@mypharma.com',
                'password' => Hash::make('password'),
                'role' => 'admin'
            ],
            [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'password' => Hash::make('password'),
                'role' => 'client'
            ],
            [
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'password' => Hash::make('password'),
                'role' => 'client'
            ],
            [
                'name' => 'Delivery Driver',
                'email' => 'driver@mypharma.com',
                'password' => Hash::make('password'),
                'role' => 'livreur'
            ]
        ];

        foreach ($users as $user) {
            User::create($user);
        }

        // Création des pharmacies
        $pharmacies = [
            [
                'name' => 'Pharmacie Centrale',
                'address' => '123 Rue Principale, Paris',
                'latitude' => 48.8566,
                'longitude' => 2.3522,
                'phone' => '0123456789',
                'rating' => 4.5,
                'delivery_available' => true
            ],
            [
                'name' => 'Pharmacie du Centre',
                'address' => '45 Avenue des Champs-Élysées, Paris',
                'latitude' => 48.8698,
                'longitude' => 2.3076,
                'phone' => '0145678910',
                'rating' => 4.2,
                'delivery_available' => true
            ],
            [
                'name' => 'Pharmacie de Quartier',
                'address' => '78 Boulevard Saint-Germain, Paris',
                'latitude' => 48.8530,
                'longitude' => 2.3499,
                'phone' => '0134567891',
                'rating' => 4.8,
                'delivery_available' => false
            ]
        ];

        foreach ($pharmacies as $pharmacy) {
            Pharmacy::create($pharmacy);
        }

        // Création des produits
        $products = [
            ['name' => 'Paracétamol 500mg', 'description' => 'Analgésique et antipyrétique', 'category_id' => 1],
            ['name' => 'Ibuprofène 400mg', 'description' => 'Anti-inflammatoire', 'category_id' => 1],
            ['name' => 'Vitamine C 1000mg', 'description' => 'Complément alimentaire', 'category_id' => 2],
            ['name' => 'Crème hydratante', 'description' => 'Pour peau sèche', 'category_id' => 3],
            ['name' => 'Thermomètre digital', 'description' => 'Mesure température corporelle', 'category_id' => 4],
            ['name' => 'Lait bébé 1er âge', 'description' => 'Alimentation nourrisson', 'category_id' => 5]
        ];

        foreach ($products as $product) {
            Product::create($product);
        }

        // Création des stocks
        $pharmacies = Pharmacy::all();
        $products = Product::all();

        foreach ($pharmacies as $pharmacy) {
            foreach ($products as $product) {
                Stock::create([
                    'pharmacy_id' => $pharmacy->id,
                    'product_id' => $product->id,
                    'quantity' => rand(10, 100),
                    'price' => rand(5, 50) + 0.99
                ]);
            }
        }

        // Création de quelques commandes
        $clientUsers = User::where('role', 'client')->get();
        
        foreach ($clientUsers as $user) {
            for ($i = 0; $i < 3; $i++) {
                $pharmacy = Pharmacy::inRandomOrder()->first();
                $selectedProducts = Product::inRandomOrder()->take(rand(1, 3))->get();
                
                $totalPrice = 0;
                $orderItems = [];
                
                foreach ($selectedProducts as $product) {
                    $stock = Stock::where('pharmacy_id', $pharmacy->id)
                        ->where('product_id', $product->id)
                        ->first();
                    
                    $quantity = rand(1, 3);
                    // S'assurer qu'il y a assez de stock
                    $quantity = min($quantity, $stock->quantity);
                    $totalPrice += $stock->price * $quantity;
                    
                    // Décrémenter le stock
                    $stock->decrement('quantity', $quantity);
                    
                    $orderItems[] = [
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'price' => $stock->price
                    ];
                }
                
                $order = Order::create([
                    'user_id' => $user->id,
                    'pharmacy_id' => $pharmacy->id,
                    'total_price' => $totalPrice,
                    'status' => ['pending', 'confirmed', 'delivering', 'delivered'][rand(0, 3)],
                    'delivery_address' => 'Adresse de livraison ' . $user->name
                ]);
                
                foreach ($orderItems as $item) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price']
                    ]);
                }
            }
        }

        // Création de quelques avis
        $deliveredOrders = Order::where('status', 'delivered')->get();
        
        foreach ($deliveredOrders as $order) {
            if (rand(0, 1)) { // 50% de chance d'avoir un avis
                Review::create([
                    'user_id' => $order->user_id,
                    'pharmacy_id' => $order->pharmacy_id,
                    'rating' => rand(3, 5),
                    'comment' => 'Excellent service, livraison rapide!'
                ]);
            }
        }

        $this->command->info('Database seeded successfully!');
    }
}
