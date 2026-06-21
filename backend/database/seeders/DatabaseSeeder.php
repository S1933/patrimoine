<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Note: do NOT use WithoutModelEvents — UUID generation relies on the
        // `creating` model event (HasUuid trait).
        $this->call([
            AssetTypeSeeder::class,
            PriceProviderSeeder::class,
            UserSeeder::class,
        ]);
    }
}
