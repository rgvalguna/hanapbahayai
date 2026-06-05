<?php

namespace App\Modules\AI\Tools;

use App\Models\Listing;
use Illuminate\Support\Collection;

class SearchListings
{
    /** Maximum radius a caller may request (km) */
    private const MAX_RADIUS_KM = 50.0;

    /** Maximum results per call */
    private const MAX_PER_PAGE = 20;

    public function execute(array $input): array
    {
        $perPage = min((int) ($input['per_page'] ?? 10), self::MAX_PER_PAGE);

        // Attempt OpenSearch-backed retrieval via Scout (Phase 1 path).
        // Falls back to the SQL path transparently if Scout is not configured or
        // the index is empty, so this is safe to enable before full OS rollout.
        if ($this->shouldUseSearch($input)) {
            try {
                return $this->executeViaSearch($input, $perPage);
            } catch (\Throwable) {
                // Degrade to SQL on any OS/Scout failure
            }
        }

        return $this->executeViaSQL($input, $perPage);
    }

    // ─── OpenSearch / Scout path ────────────────────────────────────────────

    private function shouldUseSearch(array $input): bool
    {
        // Only attempt OS path when a keyword query is present; geo+filter-only
        // queries are served well by the SQL path until indexing is verified.
        return !empty($input['q']) && config('scout.driver') !== 'null';
    }

    private function executeViaSearch(array $input, int $perPage): array
    {
        $builder = Listing::search($input['q'])
            ->query(function ($q) use ($input) {
                $q->where('status', 'active');
                if (!empty($input['property_type'])) {
                    $q->where('property_type', $input['property_type']);
                }
                if (!empty($input['min_price'])) {
                    $q->where('price_php', '>=', $input['min_price']);
                }
                if (!empty($input['max_price'])) {
                    $q->where('price_php', '<=', $input['max_price']);
                }
                if (!empty($input['min_bedrooms'])) {
                    $q->where('bedrooms', '>=', $input['min_bedrooms']);
                }
                if (!empty($input['max_bedrooms'])) {
                    $q->where('bedrooms', '<=', $input['max_bedrooms']);
                }
            });

        $listings = $builder->take($perPage)->get([
            'id', 'title', 'price_php', 'price_per_sqm', 'property_type',
            'bedrooms', 'bathrooms', 'floor_area_sqm', 'lot_area_sqm',
            'address', 'hoa_php_monthly', 'developer_id', 'broker_id',
            'fraud_flags', 'score_cache',
        ]);

        if ($listings->isEmpty()) {
            // OS returned nothing — fall through to SQL path
            throw new \RuntimeException('OpenSearch returned empty result set');
        }

        return $this->formatResults($listings);
    }

    // ─── SQL / PostGIS path (existing, now the safe fallback) ───────────────

    private function executeViaSQL(array $input, int $perPage): array
    {
        $query = Listing::query()->where('status', 'active');

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

        // Geo radius filter — radius capped at MAX_RADIUS_KM
        if (!empty($input['lat']) && !empty($input['lng'])) {
            $lat    = (float) $input['lat'];
            $lng    = (float) $input['lng'];
            $radius = min((float) ($input['radius_km'] ?? 5.0), self::MAX_RADIUS_KM);
            $query->whereRaw(
                "ST_DWithin(location::geography, ST_MakePoint(?, ?)::geography, ?)",
                [$lng, $lat, $radius * 1000]
            );
        }

        $listings = $query
            ->orderByDesc('created_at')
            ->limit($perPage)
            ->get([
                'id', 'title', 'price_php', 'price_per_sqm', 'property_type',
                'bedrooms', 'bathrooms', 'floor_area_sqm', 'lot_area_sqm',
                'address', 'hoa_php_monthly', 'developer_id', 'broker_id',
                'fraud_flags', 'score_cache',
            ]);

        return $this->formatResults($listings);
    }

    // ─── Shared formatter ───────────────────────────────────────────────────

    private function formatResults(Collection $listings): array
    {
        return [
            'count'    => $listings->count(),
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
