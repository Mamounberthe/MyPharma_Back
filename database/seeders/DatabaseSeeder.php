<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use App\Models\Pharmacy;
use App\Models\Product;
use App\Models\Stock;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     * All operations use firstOrCreate / updateOrCreate to ensure idempotence.
     */
    public function run(): void
    {
        // Création des catégories (idempotent par nom)
        $categories = [
            ['name' => 'Médicaments'],
            ['name' => 'Vitamines'],
            ['name' => 'Produits de soin'],
            ['name' => 'Équipement médical'],
            ['name' => 'Produits bébé'],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(['name' => $category['name']]);
        }

        // Création des utilisateurs (idempotent par email)
        $users = [
            [
                'email'    => 'admin@mypharma.com',
                'name'     => 'Admin User',
                'password' => Hash::make('password'),
                'role'     => 'admin',
            ],
            [
                'email'    => 'john@example.com',
                'name'     => 'John Doe',
                'password' => Hash::make('password'),
                'role'     => 'client',
            ],
            [
                'email'    => 'jane@example.com',
                'name'     => 'Jane Smith',
                'password' => Hash::make('password'),
                'role'     => 'client',
            ],
            [
                'email'    => 'driver@mypharma.com',
                'name'     => 'Delivery Driver',
                'password' => Hash::make('password'),
                'role'     => 'livreur',
            ],
        ];

        foreach ($users as $user) {
            $email = $user['email'];
            $data  = array_diff_key($user, ['email' => null]);
            User::firstOrCreate(['email' => $email], $data);
        }

        // Création des pharmacies (idempotent par nom + adresse)
        $pharmaciesData = [
            [
                'name'               => 'Pharmacie Centrale',
                'address'            => 'Avenue Modibo Keïta, Centre-ville, Bamako',
                'latitude'           => 12.6498,
                'longitude'          => -8.0003,
                'phone'              => '+223 20 22 44 55',
                'rating'             => 4.5,
                'delivery_available' => true,
                'is_on_call'         => true,
            ],
            [
                'name'               => 'Pharmacie de l\'ACI 2000',
                'address'            => 'ACI 2000, Hamdallaye, Bamako',
                'latitude'           => 12.6300,
                'longitude'          => -8.0200,
                'phone'              => '+223 20 29 31 77',
                'rating'             => 4.2,
                'delivery_available' => true,
            ],
            [
                'name'               => 'Pharmacie de Badalabougou',
                'address'            => 'Badalabougou Est, Rive droite, Bamako',
                'latitude'           => 12.6100,
                'longitude'          => -7.9900,
                'phone'              => '+223 20 23 18 42',
                'rating'             => 4.8,
                'delivery_available' => false,
            ],
        ];

        foreach ($pharmaciesData as $pharmacyData) {
            $key  = ['name' => $pharmacyData['name'], 'address' => $pharmacyData['address']];
            $data = array_diff_key($pharmacyData, $key);
            Pharmacy::firstOrCreate($key, $data);
        }

        // Création des produits (idempotent par nom + category_id)
        // base_price sert uniquement au calcul des stocks, non stocké dans products
        $productsData = [
            ['name' => 'Paracétamol 500mg',  'description' => 'Analgésique et antipyrétique',    'category_name' => 'Médicaments',        'base_price' => 600],
            ['name' => 'Ibuprofène 400mg',    'description' => 'Anti-inflammatoire',              'category_name' => 'Médicaments',        'base_price' => 1200],
            ['name' => 'Vitamine C 1000mg',   'description' => 'Complément alimentaire',          'category_name' => 'Vitamines',          'base_price' => 2000],
            ['name' => 'Crème hydratante',    'description' => 'Pour peau sèche',                 'category_name' => 'Produits de soin',   'base_price' => 3500],
            ['name' => 'Thermomètre digital', 'description' => 'Mesure température corporelle',   'category_name' => 'Équipement médical', 'base_price' => 6500],
            ['name' => 'Lait bébé 1er âge',  'description' => 'Alimentation nourrisson',         'category_name' => 'Produits bébé',      'base_price' => 5000],
        ];

        // Map category names → IDs (avoid hard-coded IDs)
        $categoryMap = Category::pluck('id', 'name');

        // Prix fixes par produit pour garantir l'idempotence des stocks
        // (rand() changerait à chaque seed → updateOrCreate remplacerait le prix)
        $fixedPrices = [
            'Paracétamol 500mg'  => 600,
            'Ibuprofène 400mg'   => 1200,
            'Vitamine C 1000mg'  => 2000,
            'Crème hydratante'   => 3500,
            'Thermomètre digital'=> 6500,
            'Lait bébé 1er âge' => 5000,
        ];

        $basePrices = [];
        foreach ($productsData as $productData) {
            $categoryId = $categoryMap[$productData['category_name']] ?? null;
            if ($categoryId === null) {
                continue;
            }
            $key  = ['name' => $productData['name'], 'category_id' => $categoryId];
            $data = ['description' => $productData['description']];
            $product = Product::firstOrCreate($key, $data);
            $basePrices[$product->id] = $productData['base_price'];
        }

        // Création des stocks (idempotent par pharmacy_id + product_id)
        $pharmacies = Pharmacy::all();
        $products   = Product::all();

        foreach ($pharmacies as $pharmacy) {
            foreach ($products as $product) {
                $price = $fixedPrices[$product->name] ?? ($basePrices[$product->id] ?? 1000);

                Stock::updateOrCreate(
                    [
                        'pharmacy_id' => $pharmacy->id,
                        'product_id'  => $product->id,
                    ],
                    [
                        'quantity' => 50,   // valeur stable et idempotente
                        'price'    => $price,
                    ]
                );
            }
        }

        // Les commandes (Order / OrderItem) sont des données transactionnelles :
        // elles NE DOIVENT PAS être seedées — un second seed créerait des doublons.

        $this->command->info('Database seeded successfully!');
    }
}
