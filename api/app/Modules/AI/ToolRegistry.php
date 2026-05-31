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

    /** Dispatch a tool call from Claude to the matching PHP handler. */
    public function dispatch(string $name, array $input, ?User $user = null): array
    {
        $class = self::$map[$name] ?? null;
        if (!$class) {
            return ['error' => "Unknown tool: {$name}"];
        }

        $handler = new $class();

        // SimulateLoan and CompareProperties accept an optional User context
        if (in_array($name, ['simulate_loan', 'compare_properties'], true)) {
            return $handler->execute($input, $user);
        }

        return $handler->execute($input);
    }
}
