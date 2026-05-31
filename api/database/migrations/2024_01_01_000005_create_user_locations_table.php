<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->unique()->constrained()->cascadeOnDelete();
            // workplace_point stored as GEOMETRY(Point,4326) — added via raw SQL after table creation
            $table->string('commute_mode', 20)->nullable(); // drive/mrt_lrt/bus/jeepney/walk/wfh
            $table->integer('max_commute_minutes')->nullable();
            $table->jsonb('preferred_areas')->default('[]'); // PSGC city codes
            $table->jsonb('excluded_areas')->default('[]');
            $table->jsonb('workplace_address')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE user_locations ADD COLUMN workplace_point GEOMETRY(Point,4326)');
        DB::statement('CREATE INDEX idx_user_locations_workplace_point ON user_locations USING GIST(workplace_point)');
    }

    public function down(): void
    {
        Schema::dropIfExists('user_locations');
    }
};
