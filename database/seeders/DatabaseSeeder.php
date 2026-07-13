<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create Test User
        $user = User::firstOrCreate([
            'email' => 'john@example.com',
        ], [
            'name' => 'John Doe',
            'password' => bcrypt('password123'),
        ]);

        // Create an additional user to write reviews
        $otherUser = User::firstOrCreate([
            'email' => 'alice@example.com',
        ], [
            'name' => 'Alice Smith',
            'password' => bcrypt('password123'),
        ]);

        // 2. Create Categories
        $categoriesData = [
            ['name' => 'Electronics', 'products' => [
                [
                    'name' => 'Smartphone Quantum X',
                    'description' => 'Un smartphone potente con pantalla AMOLED de 6.7 pulgadas, 12GB de RAM, 256GB de almacenamiento y cámara triple de 108MP.',
                    'price' => 799.99,
                    'stock' => 15,
                ],
                [
                    'name' => 'Auriculares Wireless Noise Cancelling',
                    'description' => 'Auriculares premium con cancelación activa de ruido, 30 horas de autonomía y sonido de alta fidelidad.',
                    'price' => 249.50,
                    'stock' => 25,
                ],
                [
                    'name' => 'Reloj Inteligente Horizon Pro',
                    'description' => 'Reloj inteligente con GPS integrado, monitor de ritmo cardíaco continuo, resistencia al agua 5ATM y batería de 14 días.',
                    'price' => 189.99,
                    'stock' => 5, // Stock bajo
                ],
            ]],
            ['name' => 'Books', 'products' => [
                [
                    'name' => 'Clean Code: A Handbook of Agile Software Craftsmanship',
                    'description' => 'El libro clásico de Robert C. Martin que describe las mejores prácticas para escribir software limpio y mantenible.',
                    'price' => 45.00,
                    'stock' => 50,
                ],
                [
                    'name' => 'Designing Data-Intensive Applications',
                    'description' => 'Una guía exhaustiva sobre los principios y tecnologías detrás de los sistemas de datos modernos, por Martin Kleppmann.',
                    'price' => 52.80,
                    'stock' => 30,
                ],
            ]],
            ['name' => 'Clothing', 'products' => [
                [
                    'name' => 'Chaqueta Impermeable TechWear',
                    'description' => 'Chaqueta impermeable de alto rendimiento con costuras selladas y múltiples bolsillos ocultos.',
                    'price' => 120.00,
                    'stock' => 12,
                ],
                [
                    'name' => 'Camiseta de Algodón Orgánico Pack x3',
                    'description' => 'Pack de 3 camisetas básicas de algodón 100% orgánico en colores neutros (blanco, negro, gris).',
                    'price' => 35.00,
                    'stock' => 0, // Fuera de stock para probar fallos
                ],
            ]],
        ];

        foreach ($categoriesData as $catData) {
            $category = Category::create([
                'name' => $catData['name'],
                'slug' => Str::slug($catData['name']),
            ]);

            foreach ($catData['products'] as $prodData) {
                $product = Product::create([
                    'category_id' => $category->id,
                    'name' => $prodData['name'],
                    'slug' => Str::slug($prodData['name']),
                    'description' => $prodData['description'],
                    'price' => $prodData['price'],
                    'stock' => $prodData['stock'],
                ]);

                // Create reviews for the products
                Review::create([
                    'product_id' => $product->id,
                    'user_id' => $user->id,
                    'rating' => 5,
                    'comment' => '¡Excelente producto! Superó mis expectativas por completo.',
                ]);

                Review::create([
                    'product_id' => $product->id,
                    'user_id' => $otherUser->id,
                    'rating' => 4,
                    'comment' => 'Muy bueno y de gran calidad, aunque el envío tardó un poco.',
                ]);
            }
        }
    }
}
