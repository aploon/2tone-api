<?php

namespace App\Http\Requests;

use App\Enums\BillingPeriod;
use App\Models\Listing;
use App\Models\Media;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            /**
             * Création : uniquement brouillon. La soumission pour validation se fait via PUT après paiement FedaPay.
             */
            'save_as' => ['required', 'string', Rule::in(['draft'])],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'string', Rule::in(Listing::getTypes())],
            'price' => ['required', 'integer', 'min:0'],
            'billing_period' => ['required', Rule::enum(BillingPeriod::class)],
            'neighborhood_id' => ['required', 'integer', 'exists:neighborhoods,id'],
            'bedrooms' => ['sometimes', 'integer', 'min:0', 'max:255'],
            'bathrooms' => ['sometimes', 'integer', 'min:0', 'max:255'],
            'surface_sqm' => ['nullable', 'integer', 'min:0'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'media' => ['nullable', 'array', 'max:20'],
            'media.*.type' => ['required_with:media', 'string', Rule::in([Media::TYPE_IMAGE, Media::TYPE_VIDEO_3D, Media::TYPE_MODEL_3D])],
            'media.*.url' => ['required_with:media', 'string', 'max:2048'],
            'media.*.is_primary' => ['sometimes', 'boolean'],
            'media.*.sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $media = $this->input('media', []);
            if (! is_array($media)) {
                return;
            }

            $primaryCount = 0;
            $galleryImageCount = 0;
            $video3dCount = 0;

            foreach ($media as $item) {
                $type = $item['type'] ?? null;
                $isPrimary = (bool) ($item['is_primary'] ?? false);

                if ($type === Media::TYPE_IMAGE) {
                    if ($isPrimary) {
                        $primaryCount++;
                    } else {
                        $galleryImageCount++;
                    }
                }
                if (in_array($type, [Media::TYPE_VIDEO_3D, Media::TYPE_MODEL_3D], true)) {
                    $video3dCount++;
                }
            }

            if ($primaryCount < 1) {
                $validator->errors()->add('media', 'Une image de couverture est obligatoire.');
            }
            if ($galleryImageCount < Listing::MIN_GALLERY_IMAGES_PER_LISTING) {
                $validator->errors()->add(
                    'media',
                    'Au moins '.Listing::MIN_GALLERY_IMAGES_PER_LISTING.' photos en galerie (hors couverture) sont obligatoires.',
                );
            }
            if ($video3dCount < 1) {
                $validator->errors()->add('media', 'Une visite 3D ou vidéo est obligatoire.');
            }
            if ($galleryImageCount > Listing::MAX_IMAGES_PER_LISTING) {
                $validator->errors()->add(
                    'media',
                    'Maximum '.Listing::MAX_IMAGES_PER_LISTING.' photos en galerie (hors couverture).',
                );
            }
        });
    }
}
