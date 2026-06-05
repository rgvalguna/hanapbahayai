<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B2: Add hoa_php_monthly to listings (was selected in SearchListings/CompareProperties
 *     but never existed, causing "undefined column" on every AI search call).
 *
 * B4: Add content_blocks JSONB to consultation_messages so the full Anthropic API
 *     content array (text + tool_use + tool_result blocks) can be persisted and
 *     replayed faithfully across HTTP requests, preserving multi-turn tool context.
 */
return new class extends Migration
{
    public function up(): void
    {
        // B2 — listings.hoa_php_monthly
        Schema::table('listings', function (Blueprint $table) {
            $table->decimal('hoa_php_monthly', 10, 2)
                  ->nullable()
                  ->after('price_per_sqm')
                  ->comment('Monthly HOA dues in PHP; null when not applicable or undisclosed');
        });

        // B4 — consultation_messages.content_blocks
        Schema::table('consultation_messages', function (Blueprint $table) {
            $table->jsonb('content_blocks')
                  ->nullable()
                  ->after('content')
                  ->comment('Full Anthropic API content blocks array (text/tool_use/tool_result) for faithful multi-turn replay');
        });
    }

    public function down(): void
    {
        Schema::table('consultation_messages', function (Blueprint $table) {
            $table->dropColumn('content_blocks');
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn('hoa_php_monthly');
        });
    }
};
