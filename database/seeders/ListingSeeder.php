<?php

namespace Database\Seeders;

use App\Enums\BillingPeriod;
use App\Models\Listing;
use App\Models\Media;
use App\Models\Neighborhood;
use App\Models\User;
use Illuminate\Database\Seeder;

class ListingSeeder extends Seeder
{
    private const TYPES = [
        Listing::TYPE_VILLA,
        Listing::TYPE_APARTMENT,
        Listing::TYPE_STUDIO,
        Listing::TYPE_HOUSE,
        Listing::TYPE_DUPLEX_TRIPLEX,
        Listing::TYPE_BUILDING,
    ];

    /** Sample image URLs (Unsplash / Pexels) for listing galleries. */
    private const SAMPLE_IMAGES = [
        'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?w=800',
        'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?w=800',
        'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=800',
        'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?w=800',
        'https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?w=800',
        'https://images.pexels.com/photos/1396122/pexels-photo-1396122.jpeg?w=800',
        'https://images.pexels.com/photos/1571460/pexels-photo-1571460.jpeg?w=800',
        'https://images.pexels.com/photos/106399/pexels-photo-106399.jpeg?w=800',
    ];

    /** Sample 3D resources used by the mobile viewer tests. */
    private const SAMPLE_3D_RESOURCES = [
        [
            'type' => Media::TYPE_PANORAMA_3D,
            'url' => 'https://raw.githubusercontent.com/aploon/aploon/main/assets/1.jpg',
        ],
        [
            'type' => Media::TYPE_MODEL_3D,
            'url' => 'https://raw.githubusercontent.com/KhronosGroup/glTF-Sample-Assets/main/Models/Duck/glTF-Binary/Duck.glb',
        ],
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
            $listing = Listing::create([
                'owner_id' => $owners->random()->id,
                'neighborhood_id' => $neighborhoods->random()->id,
                'title' => fake()->sentence(4),
                'description' => fake()->optional(0.9)->paragraphs(2, true),
                'type' => fake()->randomElement(self::TYPES),
                'price' => fake()->numberBetween(50_000, 1_500_000),
                'billing_period' => fake()->randomElement(BillingPeriod::cases())->value,
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

            $this->attachMediaToListing($listing);
        }

        Listing::whereDoesntHave('media')->each(fn (Listing $l) => $this->attachMediaToListing($l));
    }

    private function attachMediaToListing(Listing $listing): void
    {
        $imageUrls = fake()->randomElements(self::SAMPLE_IMAGES, fake()->numberBetween(2, 5));
        $sortOrder = 0;
        $captions = [
            ['Façade principale', 'Vue sur la rue avec jardin.'],
            ['Salon', 'Espace lumineux avec accès terrasse.'],
            ['Cuisine équipée', 'Placards et électroménager inclus.'],
            ['Chambre principale', 'Grande chambre avec dressing.'],
            ['Salle de bain', 'Carrelage et douche à l\'italienne.'],
        ];
        foreach ($imageUrls as $index => $url) {
            $caption = $captions[$index % count($captions)] ?? null;
            Media::create([
                'listing_id' => $listing->id,
                'type' => Media::TYPE_IMAGE,
                'url' => $url,
                'title' => $caption ? $caption[0] : (fake()->boolean(40) ? fake()->words(2, true) : null),
                'description' => $caption ? $caption[1] : (fake()->boolean(25) ? fake()->sentence(6) : null),
                'is_primary' => $index === 0,
                'sort_order' => $sortOrder++,
            ]);
        }
        $resource3d = fake()->randomElement(self::SAMPLE_3D_RESOURCES);
        Media::create([
            'listing_id' => $listing->id,
            'type' => $resource3d['type'],
            'url' => $resource3d['url'],
            'is_primary' => false,
            'sort_order' => $sortOrder++,
        ]);
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
                'telephone' => '+2237'.str_pad((string) ($index + 1), 7, '0', STR_PAD_LEFT),
                'whatsapp_number' => '+2237'.str_pad((string) ($index + 1), 7, '0', STR_PAD_LEFT),
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
