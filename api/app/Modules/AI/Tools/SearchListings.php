<?php

namespace App\Modules\AI\Tools;

use App\Models\Listing;
use Illuminate\Support\Facades\DB;

class SearchListings
{
    public function execute(array $input): array
    {
        $query = Listing::query()->where('status', 'live');

        if (!empty($input['property_type'])) {
            $query->where('property_type', $input['property_type']);
        }

        if (!empty($input['city'])) {
            $query->whereJsonContains('address->city', $input['city']);
        }

        if (!empty($input['min_price'])) {
            $query->where('price_php', '>=', $input['min_price']);
        }

        if (!empty($input['max_price'])) {
            $query->where('price_php', '<=', $input['max_price']);
        }

        if (!empty($input['min_bedrooms'])) {
            $query->where('bedrooms', '>=', $input['min_bedrooms']);
        }

        if (!empty($input['max_bedrooms'])) {
            $query->where('bedrooms', '<=', $input['max_bedrooms']);
        }

        if (!empty($input['min_floor_area_sqm'])) {
            $query->where('floor_area_sqm', '>=', $input['min_floor_area_sqm']);
        }

        if (!empty($input['max_floor_area_sqm'])) {
            $query->where('floor_area_sqm', '<=', $input['max_floor_area_sqm']);
        }

        if (!empty($input['q'])) {
            $keyword = $input['q'];
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'ilike', "%{$keyword}%")
                  ->orWhere('description', 'ilike', "%{$keyword}%");
            });
        }

        // Geo radius filter: lat/lng/radius_km
        if (!empty($input['lat']) && !empty($input['lng'])) {
            $lat    = (float) $input['lat'];
            $lng    = (float) $input['lng'];
            $radius = (float) ($input['radius_km'] ?? 5.0);
            $query->whereRaw(
                "ST_DWithin(location::geography, ST_MakePoint(?, ?)::geography, ?)",
                [$lng, $lat, $radius * 1000]
            );
        }

        $listings = $query
            ->orderByDesc('created_at')
            ->limit(min((int) ($input['per_page'] ?? 10), 20))
            ->get([
                'id', 'title', 'price_php', 'price_per_sqm', 'property_type',
                'bedrooms', 'bathrooms', 'floor_area_sqm', 'lot_area_sqm',
                'address', 'hoa_php_monthly', 'developer_id', 'broker_id',
                'fraud_flags', 'score_cache',
            ]);

        return [
            'count' => $listings->count(),
            'listings' => $listings->map(fn ($l) => [
                'id'              => $l->id,
                'title'           => $l->title,
                'price_php'       => $l->price_php,
                'price_per_sqm'   => $l->price_per_sqm,
                'property_type'   => $l->property_type,
                'bedrooms'        => $l->bedrooms,
                'bathrooms'       => $l->bathrooms,
                'floor_area_sqm'  => $l->floor_area_sqm,
                'lot_area_sqm'    => $l->lot_area_sqm,
                'address'         => $l->address,
                'hoa_php_monthly' => $l->hoa_php_monthly,
                'fraud_flags'     => $l->fraud_flags ?? [],
            ])->values()->toArray(),
        ];
    }
}
