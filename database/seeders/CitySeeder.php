<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        $cities = [
            'Bamako'
        ];
        foreach ($cities as $city) {
            City::firstOrCreate(['name' => $city]);
        }
    }
}
