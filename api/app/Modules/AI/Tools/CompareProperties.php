<?php

namespace App\Modules\AI\Tools;

use App\Models\Listing;
use App\Models\User;

class CompareProperties
{
    public function execute(array $input, ?User $user = null): array
    {
        $listingIds = $input['listing_ids'] ?? [];
        if (count($listingIds) < 2 || count($listingIds) > 4) {
            return ['error' => 'Provide 2–4 listing_ids for comparison'];
        }

        $scorer = new ScoreProperty();
        $focus  = $input['focus'] ?? 'overall';

        $compared = [];
        foreach ($listingIds as $id) {
            $listing = Listing::find($id);
            if (!$listing) {
                $compared[] = ['id' => $id, 'error' => 'Not found'];
                continue;
            }

            $score = $scorer->execute(['listing_id' => $id]);

            $compared[] = [
                'id'             => $listing->id,
                'title'          => $listing->title,
                'price_php'      => $listing->price_php,
                'price_per_sqm'  => $listing->price_per_sqm,
                'property_type'  => $listing->property_type,
                'bedrooms'       => $listing->bedrooms,
                'bathrooms'      => $listing->bathrooms,
                'floor_area_sqm' => $listing->floor_area_sqm,
                'address'        => $listing->address,
                'hoa_monthly'    => $listing->hoa_php_monthly,
                'fraud_flags'    => $listing->fraud_flags ?? [],
                'score'          => $score,
            ];
        }

        // Identify strongest/best-value/highest-risk from available data
        $valid = array_filter($compared, fn ($c) => !isset($c['error']));
        usort($valid, fn ($a, $b) => ($b['score']['score_total'] ?? 0) <=> ($a['score']['score_total'] ?? 0));

        $highestScore = $valid[0] ?? null;
        $lowestPrice  = !empty($valid) ? array_reduce($valid, function ($carry, $item) {
            return (!$carry || $item['price_php'] < $carry['price_php']) ? $item : $carry;
        }) : null;
        $mostFlags    = !empty($valid) ? array_reduce($valid, function ($carry, $item) {
            return (!$carry || count($item['fraud_flags']) > count($carry['fraud_flags'])) ? $item : $carry;
        }) : null;

        return [
            'focus'         => $focus,
            'listings'      => $compared,
            'summary' => [
                'strongest_fit'  => $highestScore ? $highestScore['id'] : null,
                'best_value'     => $lowestPrice  ? $lowestPrice['id']  : null,
                'highest_risk'   => $mostFlags && count($mostFlags['fraud_flags']) > 0
                    ? $mostFlags['id'] : null,
            ],
            'note' => 'Scores are preliminary. Full comparison requires user commute pins and updated scoring engine.',
        ];
    }
}
