<?php

namespace Database\Seeders\Default;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        if (Country::all()->isNotEmpty()) {
            return;
        }

        // Caminho do arquivo JSON
        $filePath = database_path(path: 'seeders/CountrySeeder.json');

        // Lê o conteúdo do arquivo
        $jsonContent = file_get_contents($filePath);

        // Decodifica o JSON para array
        $countries = json_decode($jsonContent, true);

        // Insere os dados no banco de dados
        foreach ($countries as $key => $country) {
            Country::create([
                'name' => $country['name'] ?? null,
                'capital_city' => $country['capital'] ?? null,
                'code' => strtoupper($country['iso_3166_2']) ?? null,
                'calling_code' => $country['calling_code'] ?? null,
            ]);
        }

        // $this->command->info("Seeder executado com sucesso!");
    }
}
