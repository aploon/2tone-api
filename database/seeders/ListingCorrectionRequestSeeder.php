<?php

namespace Database\Seeders;

use App\Models\Listing;
use App\Models\ListingCorrectionRequest;
use App\Models\User;
use Illuminate\Database\Seeder;

class ListingCorrectionRequestSeeder extends Seeder
{
    public function run(): void
    {
        $admins = User::query()->where('role', User::ROLE_ADMIN)->get();
        $owners = User::query()->where('role', User::ROLE_OWNER)->pluck('id')->all();

        if (empty($owners)) {
            return;
        }

        $listings = Listing::query()
            ->whereIn('owner_id', $owners)
            ->with('owner')
            ->get();

        if ($listings->isEmpty()) {
            return;
        }

        $openTitles = [
            'Mettre à jour la description',
            'Ajouter des photos plus nettes',
            'Corriger le prix affiché',
            'Préciser la localisation',
            'Ajouter les informations manquantes',
        ];

        foreach ($listings->shuffle()->take(12) as $listing) {
            $status = fake()->randomElement([ListingCorrectionRequest::STATUS_OPEN, ListingCorrectionRequest::STATUS_DONE]);
            ListingCorrectionRequest::create([
                'listing_id' => $listing->id,
                'owner_id' => $listing->owner_id,
                'admin_id' => $admins->isNotEmpty() ? $admins->random()->id : null,
                'title' => fake()->randomElement($openTitles),
                'message' => fake()->sentence(18),
                'status' => $status,
            ]);

            if ($status === ListingCorrectionRequest::STATUS_OPEN) {
                $listing->publication_status = Listing::STATUS_CORRECTION_REQUESTED;
                $listing->save();
            }
        }
    }
}

