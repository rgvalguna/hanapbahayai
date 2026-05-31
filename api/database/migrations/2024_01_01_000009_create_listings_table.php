<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('external_id')->nullable();
            $table->string('source', 30); // pagibig_ropa|lamudi|property24|broker_manual|developer_feed|admin_import
            $table->string('status', 20)->default('under_review'); // active|under_review|sold|off_market|archived
            $table->string('property_type', 30); // condo|townhouse|single_detached|lot_only|commercial|warehouse
            $table->string('tenure_type', 30)->nullable(); // freehold|leasehold|rfo|pre_selling
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('slug')->unique();
            $table->decimal('price_php', 15, 2);
            $table->decimal('price_per_sqm', 12, 2)->nullable();
            $table->decimal('floor_area_sqm', 8, 2)->nullable();
            $table->decimal('lot_area_sqm', 8, 2)->nullable();
            $table->unsignedSmallInteger('bedrooms')->default(0);
            $table->unsignedSmallInteger('bathrooms')->default(0);
            $table->unsignedSmallInteger('parking_slots')->default(0);
            // location point added via raw SQL
            $table->jsonb('address'); // PSGCAddress JSON
            $table->foreignId('developer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('broker_id')->nullable()->constrained()->nullOnDelete();
            $table->jsonb('photos')->default('[]'); // [{url, is_primary, order}]
            $table->jsonb('fraud_flags')->default('[]');
            $table->decimal('fraud_score', 5, 2)->default(0); // 0–100
            $table->boolean('is_verified')->default(false);
            $table->jsonb('amenity_tags')->default('[]');
            $table->jsonb('score_cache')->nullable(); // cached scoring result
            $table->timestamp('scored_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('source');
            $table->index('status');
            $table->index('property_type');
            $table->index('price_php');
            $table->index(['external_id', 'source']);
        });

        DB::statement('ALTER TABLE listings ADD COLUMN location GEOMETRY(Point,4326)');
        DB::statement('CREATE INDEX idx_listings_location ON listings USING GIST(location)');
    }

    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
