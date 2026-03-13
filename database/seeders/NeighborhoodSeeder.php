<?php

namespace Database\Seeders;

use App\Models\Neighborhood;
use Illuminate\Database\Seeder;

class NeighborhoodSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'Hippodrome', 'Quinzambougou', 'Magnambougou', 'Sénou', 'Badialan',
            'Baco Djicoroni', 'Hamdallaye', 'Sogoniko', 'Missira', 'Niaréla',
            'Boulkassoumbougou', 'Lafiabougou', 'Badialan I', 'Dravéla', 'Kalaban Coura',
            'Sirakoro', 'Banconi', 'Faladié', 'Aci', 'Djelibougou',
        ];

        foreach ($names as $name) {
            Neighborhood::firstOrCreate(
                ['name' => $name, 'city' => 'Bamako'],
                ['name' => $name, 'city' => 'Bamako']
            );
        }
    }
}
