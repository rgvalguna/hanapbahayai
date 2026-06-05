<?php

namespace App\Modules\AI\Tools;

use App\Models\Listing;
use App\Models\Developer;
use App\Models\Broker;
use Illuminate\Support\Facades\DB;

class FlagRedFlags
{
    private const PRICE_PREMIUM_THRESHOLD = 1.25;    // > 25% above market median
    private const DEVELOPER_COMPLAINT_LIMIT = 3;
    private const AMORT_CAUTION_DTI = 0.30;
    private const AMORT_WARN_DTI = 0.35;
    private const AMORT_REFUSE_DTI = 0.40;

    public function execute(array $input): array
    {
        $listingId = $input['listing_id'] ?? null;
        if (!$listingId) {
            return ['error' => 'listing_id is required'];
        }

        $listing = Listing::with(['developer', 'broker'])->find($listingId);
        if (!$listing) {
            return ['error' => "Listing {$listingId} not found"];
        }

        $flags     = [];
        $runPhotos = $input['include_photo_check'] ?? true;

        // 1. Price vs. market median
        $flags = array_merge($flags, $this->checkPricing($listing));

        // 2. Developer complaint history
        $flags = array_merge($flags, $this->checkDeveloper($listing));

        // 3. Broker verification
        $flags = array_merge($flags, $this->checkBroker($listing));

        // 4. Listing quality / completeness
        $flags = array_merge($flags, $this->checkListingCompleteness($listing));

        // 5. Flood zone proximity (basic check via existing fraud_flags)
        $flags = array_merge($flags, $this->checkFloodAndDisaster($listing));

        // 6. Photo reuse check (perceptual hash)
        if ($runPhotos) {
            $flags = array_merge($flags, $this->checkPhotoDuplication($listing));
        }

        // 7. Existing fraud flags already surfaced by pipeline
        foreach ($listing->fraud_flags ?? [] as $ff) {
            $flags[] = [
                'severity' => 'warning',
                'type'     => 'pipeline_fraud_flag',
                'message'  => $ff,
            ];
        }

        $criticals = count(array_filter($flags, fn ($f) => $f['severity'] === 'critical'));
        $warnings  = count(array_filter($flags, fn ($f) => $f['severity'] === 'warning'));
        $cautions  = count(array_filter($flags, fn ($f) => $f['severity'] === 'caution'));

        return [
            'listing_id'      => $listingId,
            'flags'           => $flags,
            'summary' => [
                'critical' => $criticals,
                'warning'  => $warnings,
                'caution'  => $cautions,
                'info'     => count($flags) - $criticals - $warnings - $cautions,
            ],
            'overall_risk' => $criticals > 0 ? 'high' : ($warnings > 1 ? 'medium' : 'low'),
        ];
    }

    private function checkPricing(Listing $listing): array
    {
        $flags = [];
        if (!$listing->price_per_sqm || !$listing->address) {
            return $flags;
        }

        $city = $listing->address['city'] ?? null;
        $type = $listing->property_type;

        if (!$city) {
            return $flags;
        }

        // Derive local median from live listings
        try {
            $median = DB::table('listings')
                ->where('status', 'active')
                ->where('property_type', $type)
                ->whereNull('deleted_at')
                ->whereRaw("address->>'city' ilike ?", [$city])
                ->whereNotNull('price_per_sqm')
                ->where('price_per_sqm', '>', 0)
                ->selectRaw('percentile_cont(0.5) WITHIN GROUP (ORDER BY price_per_sqm) AS median_psm')
                ->value('median_psm');

            if ($median && $median > 0) {
                $ratio = (float) $listing->price_per_sqm / (float) $median;
                if ($ratio > self::PRICE_PREMIUM_THRESHOLD) {
                    $overPct = round(($ratio - 1) * 100, 1);
                    $flags[] = [
                        'severity' => $ratio > 1.50 ? 'critical' : 'warning',
                        'type'     => 'price_above_market',
                        'message'  => "Listed at PHP {$listing->price_per_sqm}/sqm — {$overPct}% above the {$city} {$type} median (PHP " . round($median, 0) . "/sqm). Verify with the seller or an appraiser.",
                    ];
                }
            }
        } catch (\Exception) {
            // DB may not have enough data
        }

        return $flags;
    }

    private function checkDeveloper(Listing $listing): array
    {
        $flags = [];

        if (!$listing->developer_id) {
            return $flags;
        }

        try {
            $dev = $listing->developer ?? Developer::find($listing->developer_id);
            if (!$dev) {
                return $flags;
            }

            if ((int) $dev->complaints_count >= self::DEVELOPER_COMPLAINT_LIMIT) {
                $severity = $dev->complaints_count >= 6 ? 'critical' : 'warning';
                $flags[] = [
                    'severity' => $severity,
                    'type'     => 'developer_complaints',
                    'message'  => "{$dev->name} has {$dev->complaints_count} recorded complaints in our database (RFO delays, defects, HOA issues). Request copies of turnover records before signing.",
                ];
            }

            if (isset($dev->reputation_score) && $dev->reputation_score < 60) {
                $flags[] = [
                    'severity' => 'caution',
                    'type'     => 'developer_low_reputation',
                    'message'  => "{$dev->name} has a below-average reputation score ({$dev->reputation_score}/100). Review buyer feedback in community forums before committing.",
                ];
            }
        } catch (\Exception) {
            // Developer model may not yet exist
        }

        return $flags;
    }

    private function checkBroker(Listing $listing): array
    {
        $flags = [];

        if (!$listing->broker_id) {
            $flags[] = [
                'severity' => 'caution',
                'type'     => 'broker_unidentified',
                'message'  => 'No broker on record for this listing. Verify the seller\'s identity independently.',
            ];
            return $flags;
        }

        try {
            $broker = $listing->broker ?? Broker::find($listing->broker_id);
            if (!$broker) {
                return $flags;
            }

            if (empty($broker->prc_id) && empty($broker->dhsud_id)) {
                $flags[] = [
                    'severity' => 'warning',
                    'type'     => 'broker_unverified',
                    'message'  => 'Broker has not completed PRC/DHSUD verification. Only transact with licensed real estate brokers (RA 9646).',
                ];
            } elseif ($broker->status !== 'verified') {
                $flags[] = [
                    'severity' => 'caution',
                    'type'     => 'broker_pending_verification',
                    'message'  => 'Broker verification is in progress. Confirm PRC license number at prc.gov.ph before signing any documents.',
                ];
            }
        } catch (\Exception) {
            // Broker model may not be available
        }

        return $flags;
    }

    private function checkListingCompleteness(Listing $listing): array
    {
        $flags = [];

        if (empty($listing->photos) || count($listing->photos) < 3) {
            $flags[] = [
                'severity' => 'caution',
                'type'     => 'few_photos',
                'message'  => 'Listing has fewer than 3 photos. Request a virtual tour or additional images before scheduling a visit.',
            ];
        }

        if (!$listing->floor_area_sqm) {
            $flags[] = [
                'severity' => 'info',
                'type'     => 'missing_floor_area',
                'message'  => 'Floor area is not disclosed. Confirm in the contract-to-sell.',
            ];
        }

        if ($listing->price_per_sqm && $listing->floor_area_sqm) {
            // Price sanity: < PHP 3,000/sqm or > PHP 500,000/sqm is suspicious
            $psm = (float) $listing->price_per_sqm;
            if ($psm < 3000 || $psm > 500000) {
                $flags[] = [
                    'severity' => 'warning',
                    'type'     => 'price_sanity_fail',
                    'message'  => "Price per sqm (PHP {$psm}) is outside typical Philippine residential range. Confirm the price is in Philippine Pesos.",
                ];
            }
        }

        return $flags;
    }

    private function checkFloodAndDisaster(Listing $listing): array
    {
        $flags = [];

        if (!$listing->location) {
            return $flags;
        }

        // Check fraud_flags for any pre-computed flood signal from ingestion pipeline
        foreach ($listing->fraud_flags ?? [] as $ff) {
            if (stripos($ff, 'flood') !== false || stripos($ff, 'fault') !== false || stripos($ff, 'storm') !== false) {
                return $flags; // Already captured in main fraud_flags loop
            }
        }

        // PostGIS flood zone check (requires flood_zones table populated by ingestion)
        try {
            $inFlood = DB::selectOne(
                "SELECT 1 FROM flood_zones WHERE ST_Within(ST_MakePoint(?, ?)::geography::geometry, geom::geometry) AND return_period_years <= 25 LIMIT 1",
                [$listing->location->getLng(), $listing->location->getLat()]
            );

            if ($inFlood) {
                $flags[] = [
                    'severity' => 'critical',
                    'type'     => 'flood_zone_25yr',
                    'message'  => 'This property falls within a 25-year flood return zone per PAGASA hazard maps. Flood insurance is required and may be costly. High-risk buyers should reconsider.',
                ];
            }
        } catch (\Exception) {
            // flood_zones table may not be populated or location column type differs
        }

        return $flags;
    }

    private function checkPhotoDuplication(Listing $listing): array
    {
        $flags = [];

        if (empty($listing->photos)) {
            return $flags;
        }

        // Perceptual hash deduplication: check if any phash matches another listing
        try {
            $photos      = is_array($listing->photos) ? $listing->photos : [];
            $urls        = array_column($photos, 'url') ?: $photos;
            $listingPhashes = DB::table('listings_photos')
                ->where('listing_id', $listing->id)
                ->whereNotNull('phash')
                ->pluck('phash')
                ->toArray();

            if (empty($listingPhashes)) {
                return $flags;
            }

            foreach ($listingPhashes as $phash) {
                $duplicate = DB::table('listings_photos')
                    ->where('listing_id', '!=', $listing->id)
                    ->where('phash', $phash)
                    ->join('listings', 'listings.id', '=', 'listings_photos.listing_id')
                    ->where('listings.status', 'active')
                    ->whereNull('listings.deleted_at')
                    ->first(['listings.id', 'listings.title']);

                if ($duplicate) {
                    $flags[] = [
                        'severity' => 'warning',
                        'type'     => 'photo_reuse',
                        'message'  => "One or more photos match another live listing (ID: {$duplicate->id}). This is a common fraud signal. Request an in-person visit to confirm the property exists as described.",
                    ];
                    break; // One warning is enough
                }
            }
        } catch (\Exception) {
            // listings_photos table or phash column may not exist yet
        }

        return $flags;
    }
}
