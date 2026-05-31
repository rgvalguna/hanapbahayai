<?php

namespace App\Modules\AI\Tools;

use App\Models\Listing;

class ScoreProperty
{
    public function execute(array $input): array
    {
        $listingId = $input['listing_id'] ?? null;
        if (!$listingId) {
            return ['error' => 'listing_id is required'];
        }

        $listing = Listing::find($listingId);
        if (!$listing) {
            return ['error' => "Listing {$listingId} not found"];
        }

        // Use cached score if available and fresh (< 24h old)
        $cached = $listing->score_cache;
        if ($cached && isset($cached['scored_at'])) {
            $scoredAt = \Carbon\Carbon::parse($cached['scored_at']);
            if ($scoredAt->diffInHours(now()) < 24) {
                return array_merge($cached, ['from_cache' => true]);
            }
        }

        // Placeholder scoring — real PropertyScorer will replace this once built.
        // Scores are computed deterministically from available listing fields.
        $score = $this->computePlaceholderScore($listing);

        return $score;
    }

    private function computePlaceholderScore(Listing $listing): array
    {
        $price = (float) $listing->price_php;
        $sqm   = (float) ($listing->floor_area_sqm ?? 1);
        $psm   = $price / max($sqm, 1);

        // Rough affordability proxy: lower PSM = better
        $affordability = max(0, min(100, 100 - (($psm - 50000) / 2000)));

        $fraudPenalty = count($listing->fraud_flags ?? []) * 15;
        $overallScore = max(0, round($affordability * 0.4 + 50 * 0.6 - $fraudPenalty, 1));

        return [
            'listing_id'          => $listing->id,
            'score_total'         => $overallScore,
            'scores_by_dimension' => [
                'affordability'      => round($affordability, 1),
                'commute'            => null,
                'safety'             => null,
                'flood'              => null,
                'education'          => null,
                'healthcare'         => null,
                'internet'           => null,
                'investment'         => null,
                'developer'          => null,
                'livability'         => null,
            ],
            'rationale'           => 'Preliminary score based on price-per-sqm and fraud signals. Full scoring requires commute pins and profile weights.',
            'warnings'            => array_map(fn ($f) => ['severity' => 'warning', 'message' => $f], $listing->fraud_flags ?? []),
            'note'                => 'Full PropertyScorer integration pending — scores marked null are not yet computed.',
            'from_cache'          => false,
        ];
    }
}
