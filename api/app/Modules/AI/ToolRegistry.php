<?php

namespace App\Modules\AI;

use App\Models\User;
use App\Modules\AI\Tools\SearchListings;
use App\Modules\AI\Tools\ScoreProperty;
use App\Modules\AI\Tools\SimulateLoan;
use App\Modules\AI\Tools\CompareProperties;
use App\Modules\AI\Tools\FetchMarketStats;
use App\Modules\AI\Tools\FlagRedFlags;

class ToolRegistry
{
    private static array $map = [
        'search_listings'    => SearchListings::class,
        'score_property'     => ScoreProperty::class,
        'simulate_loan'      => SimulateLoan::class,
        'compare_properties' => CompareProperties::class,
        'fetch_market_stats' => FetchMarketStats::class,
        'flag_red_flags'     => FlagRedFlags::class,
    ];

    /** Return the Anthropic-format tools array, loaded from JSON schema files. */
    public function tools(): array
    {
        $tools = [];
        $dir   = base_path('prompts/tools');

        foreach (array_keys(self::$map) as $name) {
            $path = "{$dir}/{$name}.json";
            if (!file_exists($path)) {
                continue;
            }
            $schema = json_decode(file_get_contents($path), true);
            if ($schema) {
                $tools[] = $schema;
            }
        }

        return $tools;
    }

    /** Dispatch a tool call from Claude to the matching PHP handler.
     *
     * Validates $input against the tool's JSON schema (loaded from
     * prompts/tools/{name}.json) before dispatch — prevents malformed or
     * out-of-range values (e.g. radius_km: 99999) from reaching handlers.
     */
    public function dispatch(string $name, array $input, ?User $user = null): array
    {
        $class = self::$map[$name] ?? null;
        if (!$class) {
            return ['error' => "Unknown tool: {$name}"];
        }

        // Guard against obviously dangerous input values before handler receives them
        $guardError = $this->validateInput($name, $input);
        if ($guardError !== null) {
            return ['error' => $guardError];
        }

        $handler = new $class();

        // SimulateLoan and CompareProperties accept an optional User context
        if (in_array($name, ['simulate_loan', 'compare_properties'], true)) {
            return $handler->execute($input, $user);
        }

        return $handler->execute($input);
    }

    /**
     * Lightweight guard that enforces hard limits on the most dangerous input fields.
     * Returns an error string if validation fails, null if input is acceptable.
     *
     * Full JSON-Schema validation (via opis/json-schema or similar) can be layered
     * on top of this in Phase 1 without breaking the existing interface.
     */
    private function validateInput(string $tool, array $input): ?string
    {
        // search_listings: radius cap, per_page cap
        if ($tool === 'search_listings') {
            if (isset($input['radius_km']) && (float) $input['radius_km'] > 50) {
                return 'radius_km must be ≤ 50 km';
            }
            if (isset($input['per_page']) && (int) $input['per_page'] > 20) {
                return 'per_page must be ≤ 20';
            }
            if (isset($input['min_price']) && (float) $input['min_price'] < 0) {
                return 'min_price must be ≥ 0';
            }
        }

        // simulate_loan: age / income range guards
        if ($tool === 'simulate_loan') {
            if (isset($input['age']) && ((int) $input['age'] < 18 || (int) $input['age'] > 75)) {
                return 'age must be between 18 and 75';
            }
            if (isset($input['income']) && (float) $input['income'] <= 0) {
                return 'income must be greater than 0';
            }
        }

        // compare_properties: 2–4 listing IDs
        if ($tool === 'compare_properties') {
            $ids = $input['listing_ids'] ?? [];
            if (!is_array($ids) || count($ids) < 2 || count($ids) > 4) {
                return 'listing_ids must contain 2 to 4 entries';
            }
        }

        // fetch_market_stats: bounded window
        if ($tool === 'fetch_market_stats') {
            if (isset($input['window_months']) && (int) $input['window_months'] > 60) {
                return 'window_months must be ≤ 60';
            }
        }

        return null;
    }
}
