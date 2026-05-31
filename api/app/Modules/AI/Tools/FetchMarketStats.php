<?php

namespace App\Modules\AI\Tools;

use Illuminate\Support\Facades\DB;

class FetchMarketStats
{
    public function execute(array $input): array
    {
        $city         = $input['city'] ?? null;
        $propertyType = $input['property_type'] ?? null;
        $barangay     = $input['barangay'] ?? null;
        $sqmBand      = $input['sqm_band'] ?? null;

        if (!$city || !$propertyType) {
            return ['error' => 'city and property_type are required'];
        }

        // Try market_stats table (TimescaleDB hypertable populated by ingestion pipeline)
        try {
            $row = $this->queryMarketStats($city, $propertyType, $barangay, $sqmBand);
            if ($row) {
                return $row;
            }
        } catch (\Exception) {
            // Table may not be populated yet in early deployment
        }

        // Fallback: derive stats from live listings in DB
        return $this->deriveLiveStats($city, $propertyType, $barangay, $sqmBand);
    }

    private function queryMarketStats(string $city, string $type, ?string $barangay, ?string $sqmBand): ?array
    {
        $query = DB::table('market_stats')
            ->where('property_type', $type)
            ->where('period', DB::raw("date_trunc('month', now())"))
            ->orderByDesc('period');

        if ($barangay) {
            $query->whereRaw("address_json->>'barangay' ilike ?", [$barangay]);
        }
        if ($sqmBand) {
            $query->where('sqm_band', $sqmBand);
        }

        // City match via PSGC or name
        $query->whereRaw("address_json->>'city' ilike ?", [$city]);

        if ($sqmBand) {
            $query->where('sqm_band', $sqmBand);
        }

        $row = $query->first();
        if (!$row) {
            return null;
        }

        return [
            'source'               => 'market_stats_table',
            'city'                 => $city,
            'barangay'             => $barangay,
            'property_type'        => $type,
            'sqm_band'             => $sqmBand,
            'median_price_per_sqm' => $row->median_psm ?? null,
            'price_trend_3mo_pct'  => $row->trend_3mo_pct ?? null,
            'price_trend_12mo_pct' => $row->trend_12mo_pct ?? null,
            'avg_days_on_market'   => $row->avg_days_on_market ?? null,
            'inventory_count'      => $row->inventory_count ?? null,
            'period'               => $row->period ?? null,
        ];
    }

    private function deriveLiveStats(string $city, string $type, ?string $barangay, ?string $sqmBand): array
    {
        $query = DB::table('listings')
            ->where('status', 'live')
            ->where('property_type', $type)
            ->whereNull('deleted_at')
            ->whereRaw("address->>'city' ilike ?", [$city])
            ->whereNotNull('price_per_sqm')
            ->where('price_per_sqm', '>', 0);

        if ($barangay) {
            $query->whereRaw("address->>'barangay' ilike ?", [$barangay]);
        }

        if ($sqmBand) {
            [$min, $max] = $this->sqmBandToRange($sqmBand);
            if ($min !== null) {
                $query->where('floor_area_sqm', '>=', $min);
            }
            if ($max !== null) {
                $query->where('floor_area_sqm', '<', $max);
            }
        }

        $stats = $query->selectRaw(
            'percentile_cont(0.5) WITHIN GROUP (ORDER BY price_per_sqm) AS median_psm,
             count(*) AS inventory_count,
             avg(EXTRACT(EPOCH FROM (now() - created_at))/86400) AS avg_days_on_market'
        )->first();

        if (!$stats || $stats->inventory_count < 3) {
            return [
                'source'        => 'insufficient_data',
                'city'          => $city,
                'property_type' => $type,
                'note'          => 'Insufficient listings in this area/type to compute reliable statistics. Expand your search area.',
            ];
        }

        return [
            'source'               => 'live_listings_derived',
            'city'                 => $city,
            'barangay'             => $barangay,
            'property_type'        => $type,
            'sqm_band'             => $sqmBand,
            'median_price_per_sqm' => $stats->median_psm ? round((float) $stats->median_psm, 2) : null,
            'price_trend_3mo_pct'  => null,
            'price_trend_12mo_pct' => null,
            'avg_days_on_market'   => $stats->avg_days_on_market ? round((float) $stats->avg_days_on_market) : null,
            'inventory_count'      => (int) $stats->inventory_count,
            'note'                 => 'Trend data not yet available — ingestion pipeline populates historical benchmarks.',
        ];
    }

    private function sqmBandToRange(string $band): array
    {
        return match ($band) {
            'under_30'    => [null, 30],
            '30_to_60'    => [30, 60],
            '60_to_100'   => [60, 100],
            '100_to_150'  => [100, 150],
            'over_150'    => [150, null],
            default       => [null, null],
        };
    }
}
