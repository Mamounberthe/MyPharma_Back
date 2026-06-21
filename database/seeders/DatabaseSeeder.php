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

        // Création des pharmacies (Bamako, Mali)
        $pharmacies = [
            [
                'name' => 'Pharmacie Centrale',
                'address' => 'Avenue Modibo Keïta, Centre-ville, Bamako',
                'latitude' => 12.6498,
                'longitude' => -8.0003,
                'phone' => '+223 20 22 44 55',
                'rating' => 4.5,
                'delivery_available' => true,
                'is_on_call' => true
            ],
            [
                'name' => 'Pharmacie de l\'ACI 2000',
                'address' => 'ACI 2000, Hamdallaye, Bamako',
                'latitude' => 12.6300,
                'longitude' => -8.0200,
                'phone' => '+223 20 29 31 77',
                'rating' => 4.2,
                'delivery_available' => true
            ],
            [
                'name' => 'Pharmacie de Badalabougou',
                'address' => 'Badalabougou Est, Rive droite, Bamako',
                'latitude' => 12.6100,
                'longitude' => -7.9900,
                'phone' => '+223 20 23 18 42',
                'rating' => 4.8,
                'delivery_available' => false
            ]
        ];

        foreach ($pharmacies as $pharmacy) {
            Pharmacy::create($pharmacy);
        }

        // Création des produits (base_price en FCFA, sert au calcul des stocks)
        $products = [
            ['name' => 'Paracétamol 500mg', 'description' => 'Analgésique et antipyrétique', 'category_id' => 1, 'base_price' => 600],
            ['name' => 'Ibuprofène 400mg', 'description' => 'Anti-inflammatoire', 'category_id' => 1, 'base_price' => 1200],
            ['name' => 'Vitamine C 1000mg', 'description' => 'Complément alimentaire', 'category_id' => 2, 'base_price' => 2000],
            ['name' => 'Crème hydratante', 'description' => 'Pour peau sèche', 'category_id' => 3, 'base_price' => 3500],
            ['name' => 'Thermomètre digital', 'description' => 'Mesure température corporelle', 'category_id' => 4, 'base_price' => 6500],
            ['name' => 'Lait bébé 1er âge', 'description' => 'Alimentation nourrisson', 'category_id' => 5, 'base_price' => 5000]
        ];

        $basePrices = [];
        foreach ($products as $product) {
            $basePrice = $product['base_price'];
            unset($product['base_price']);
            $created = Product::create($product);
            $basePrices[$created->id] = $basePrice;
        }

        // Création des stocks
        $pharmacies = Pharmacy::all();
        $products = Product::all();

        foreach ($pharmacies as $pharmacy) {
            foreach ($products as $product) {
                // Prix en FCFA : base du produit ±15%, arrondi à 25 FCFA
                $base = $basePrices[$product->id] ?? 1000;
                $price = (int) (round($base * (1 + rand(-15, 15) / 100) / 25) * 25);
                Stock::create([
                    'pharmacy_id' => $pharmacy->id,
                    'product_id' => $product->id,
                    'quantity' => rand(10, 100),
                    'price' => $price
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
                // firstOrCreate : un client peut avoir plusieurs commandes livrées
                // dans la même pharmacie → respecte la contrainte unique (user, pharmacy)
                Review::firstOrCreate(
                    [
                        'user_id' => $order->user_id,
                        'pharmacy_id' => $order->pharmacy_id,
                    ],
                    [
                        'rating' => rand(3, 5),
                        'comment' => 'Excellent service, livraison rapide!'
                    ]
                );
            }
        }

        $this->command->info('Database seeded successfully!');
    }
}
