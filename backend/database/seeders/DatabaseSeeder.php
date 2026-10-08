<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $legacyDataPath = base_path('../data/db.json');

        if (! is_file($legacyDataPath) || DB::table('products')->exists()) {
            return;
        }

        $legacyData = json_decode(file_get_contents($legacyDataPath), true, flags: JSON_THROW_ON_ERROR);

        foreach ($legacyData['products'] ?? [] as $product) {
            DB::table('products')->insert([
                'id' => $product['id'],
                'name' => $product['name'],
                'category' => $product['category'],
                'price' => $product['price'],
                'stock' => $product['stock'],
                'sold' => $product['sold'] ?? 0,
                'image' => $product['image'],
                'description' => $product['description'],
                'featured' => (int) ($product['featured'] ?? false),
            ]);
        }
    }
}
