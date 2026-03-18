<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Neighborhood;
use Illuminate\Database\Seeder;

class NeighborhoodSeeder extends Seeder
{
    public function run(): void
    {
        $city = City::firstOrCreate(['name' => 'Bamako']);

        $names = [
            // Commune I (Nord-Est)
            'Banconi',
            'Korofina Nord',
            'Korofina Sud',
            'Djélibougou',
            'Boulkassoumbougou',
            'Fadjiguila',
            'Doumanzana',
            'Sotuba',

            // Commune II (Centre-Est)
            'Bagadadji',
            'Médina-Coura',
            'Missira',
            'Hippodrome I',
            'Hippodrome II',
            'Quinzambougou',
            'Zone Industrielle',
            'Niaréla',
            'TSF-Sans Fil',

            // Commune III (Centre-Ville / Administratif)
            'Centre-ville (Grand Marché)',
            'Bamako-Coura',
            'Bolibana',
            'Darsalam',
            'Dibida',
            'N’Tomikorobougou',
            'Ouolofobougou',
            'Badalabougou (Rive gauche)',

            // Commune IV (Ouest - Zone Résidentiel)
            'Hamdallaye (ACI 2000)',
            'Lafiabougou',
            'Djicoroni-Para',
            'Sébénikoro',
            'Taliko',
            'Lassa',
            'Sibiribougou',

            // Commune V (Rive Droite - Sud)
            'Badalabougou',
            'Torokorobougou',
            'Quartier Mali',
            'Baco-Djicoroni (ACI)',
            'Sabalibougou',
            'Daoudabougou',
            'Kalaban-Coura',

            // Commune VI (Sud-Est - Expansion)
            'Sogoniko',
            'Magnambougou',
            'Banankabougou',
            'Faladié',
            'Niamakoro',
            'Missabougou',
            'Yirimadio',
            'Sokorodji',

            // Zones Périphériques à forte demande
            'Kalaban-Coro',
            'Kati',
            'Senou (Aéroport)',
            'Samaya',
            'Kanadjiguila',
        ];

        foreach ($names as $name) {
            Neighborhood::firstOrCreate(['name' => $name, 'city_id' => $city->id]);
        }
    }
}
