<?php

namespace Database\Seeders;

use App\Models\Listing;
use App\Models\Neighborhood;
use App\Models\User;
use Illuminate\Database\Seeder;

class ListingSeeder extends Seeder
{
    private const TYPES = [
        'villa', 'house', 'apartment', 'duplex_triplex',
        'building', 'studio', 'office', 'land',
    ];

    public function run(): void
    {
        $this->ensureOwners();
        $this->ensureNeighborhoods();

        $neighborhoods = Neighborhood::all();
        $owners = User::whereIn('role', [User::ROLE_OWNER, User::ROLE_ADMIN])->get();

        if ($neighborhoods->isEmpty() || $owners->isEmpty()) {
            return;
        }

        $count = Listing::count();
        if ($count >= 30) {
            return;
        }

        $toCreate = 30 - $count;

        for ($i = 0; $i < $toCreate; $i++) {
            Listing::create([
                'owner_id' => $owners->random()->id,
                'neighborhood_id' => $neighborhoods->random()->id,
                'title' => fake()->sentence(4),
                'description' => fake()->optional(0.9)->paragraphs(2, true),
                'type' => fake()->randomElement(self::TYPES),
                'price' => fake()->numberBetween(50_000, 1_500_000),
                'publication_status' => fake()->randomElement([
                    Listing::STATUS_DRAFT,
                    Listing::STATUS_PENDING,
                    Listing::STATUS_PAID,
                    Listing::STATUS_PUBLISHED,
                ]),
                'bedrooms' => fake()->numberBetween(0, 5),
                'bathrooms' => fake()->numberBetween(0, 3),
                'surface_sqm' => fake()->optional(0.8)->numberBetween(25, 400),
                'latitude' => fake()->optional(0.7)->latitude(12.5, 12.7),
                'longitude' => fake()->optional(0.7)->longitude(-8.1, -7.9),
            ]);
        }
    }

    private function ensureOwners(): void
    {
        if (User::whereIn('role', [User::ROLE_OWNER, User::ROLE_ADMIN])->exists()) {
            return;
        }

        User::factory()->count(5)->create([
            'role' => User::ROLE_OWNER,
            'telephone' => null,
            'whatsapp_number' => null,
        ]);

        foreach (User::where('role', User::ROLE_OWNER)->get() as $index => $user) {
            $user->update([
                'telephone' => '+2237' . str_pad((string) ($index + 1), 7, '0', STR_PAD_LEFT),
                'whatsapp_number' => '+2237' . str_pad((string) ($index + 1), 7, '0', STR_PAD_LEFT),
            ]);
        }
    }

    private function ensureNeighborhoods(): void
    {
        if (Neighborhood::exists()) {
            return;
        }

        $this->call(NeighborhoodSeeder::class);
    }
}
