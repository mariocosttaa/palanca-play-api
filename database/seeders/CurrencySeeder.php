<?php

namespace Database\Seeders;

use App\Models\Manager\CurrencyModel;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currencies = [
            [
                'code' => 'eur',
                'symbol' => '€',
                'decimal_separator' => 2,
            ],
            [
                'code' => 'usd',
                'symbol' => '$',
                'decimal_separator' => 2,
            ],
            [
                'code' => 'brl',
                'symbol' => 'R$',
                'decimal_separator' => 2,
            ],
            [
                'code' => 'aoa',
                'symbol' => 'Kz',
                'decimal_separator' => 2,
            ],
        ];

        foreach ($currencies as $currency) {
            CurrencyModel::updateOrCreate(
                ['code' => $currency['code']],
                $currency
            );
        }
    }
}
