<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->jsonb('property_types')->default('[]');
            $table->jsonb('tenure_types')->default('[]');
            $table->integer('min_bedrooms')->nullable();
            $table->integer('max_bedrooms')->nullable();
            $table->decimal('min_price_php', 15, 2)->nullable();
            $table->decimal('max_price_php', 15, 2)->nullable();
            $table->decimal('min_floor_area_sqm', 8, 2)->nullable();
            $table->boolean('near_schools')->default(false);
            $table->boolean('flood_averse')->default(false);
            $table->boolean('near_hospital')->default(false);
            $table->boolean('needs_parking')->default(false);
            $table->jsonb('must_have_tags')->default('[]');
            $table->jsonb('nice_to_have_tags')->default('[]');
            $table->jsonb('score_weights')->nullable(); // override archetype defaults
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
