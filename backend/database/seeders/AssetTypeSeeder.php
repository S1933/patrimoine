<?php

namespace Database\Seeders;

use App\Models\AssetType;
use Illuminate\Database\Seeder;

class AssetTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'stock',         'label' => 'Action',          'default_unit' => 'action',       'is_priced_externally' => true,  'default_provider' => 'twelve_data'],
            ['code' => 'etf',           'label' => 'ETF',             'default_unit' => 'part',         'is_priced_externally' => true,  'default_provider' => 'twelve_data'],
            ['code' => 'etn_crypto',    'label' => 'ETN Crypto',      'default_unit' => 'part',         'is_priced_externally' => true,  'default_provider' => 'twelve_data'],
            ['code' => 'crypto',        'label' => 'Cryptomonnaie',   'default_unit' => 'unit',         'is_priced_externally' => true,  'default_provider' => 'coingecko'],
            ['code' => 'gold',          'label' => 'Or physique',     'default_unit' => 'gramme',       'is_priced_externally' => true,  'default_provider' => 'goldapi'],
            ['code' => 'real_estate',   'label' => 'Immobilier',      'default_unit' => 'euros',        'is_priced_externally' => false, 'default_provider' => 'manual'],
            ['code' => 'cash',          'label' => 'Cash',            'default_unit' => 'euros',        'is_priced_externally' => false, 'default_provider' => 'manual'],
            ['code' => 'livret_a',      'label' => 'Livret A',        'default_unit' => 'euros',        'is_priced_externally' => false, 'default_provider' => 'manual'],
            ['code' => 'ldds',          'label' => 'LDDS',            'default_unit' => 'euros',        'is_priced_externally' => false, 'default_provider' => 'manual'],
            ['code' => 'other',         'label' => 'Autre',           'default_unit' => 'unit',         'is_priced_externally' => false, 'default_provider' => 'manual'],
        ];

        foreach ($types as $t) {
            AssetType::updateOrCreate(['code' => $t['code']], $t);
        }
    }
}
