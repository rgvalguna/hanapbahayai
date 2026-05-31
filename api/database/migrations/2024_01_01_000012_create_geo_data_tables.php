<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        // Flood zones (PAGASA)
        DB::statement('CREATE TABLE IF NOT EXISTS flood_zones (
            id BIGSERIAL PRIMARY KEY,
            name VARCHAR(120),
            return_period_years INTEGER NOT NULL, -- 5,25,100
            source VARCHAR(60) DEFAULT \'pagasa\',
            geom GEOMETRY(MultiPolygon,4326) NOT NULL,
            created_at TIMESTAMPTZ DEFAULT NOW()
        )');
        DB::statement('CREATE INDEX idx_flood_zones_geom ON flood_zones USING GIST(geom)');
        DB::statement('CREATE INDEX idx_flood_zones_return_period ON flood_zones(return_period_years)');

        // Fault lines (PHIVOLCS)
        DB::statement('CREATE TABLE IF NOT EXISTS fault_lines (
            id BIGSERIAL PRIMARY KEY,
            name VARCHAR(120),
            fault_type VARCHAR(60),
            active_category VARCHAR(30), -- active|potentially_active|inactive
            geom GEOMETRY(LineString,4326) NOT NULL,
            created_at TIMESTAMPTZ DEFAULT NOW()
        )');
        DB::statement('CREATE INDEX idx_fault_lines_geom ON fault_lines USING GIST(geom)');

        // Schools
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('deped_id', 20)->nullable()->unique();
            $table->string('name');
            $table->string('level', 20); // elementary|secondary|senior_high|higher_education
            $table->string('type', 20); // public|private
            $table->string('region_code', 10)->nullable();
            $table->string('city_muni_code', 10)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        DB::statement('ALTER TABLE schools ADD COLUMN point GEOMETRY(Point,4326)');
        DB::statement('CREATE INDEX idx_schools_point ON schools USING GIST(point)');

        // Hospitals
        Schema::create('hospitals', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('level', 20); // level_1|level_2|level_3|special
            $table->string('type', 20)->default('government'); // government|private
            $table->string('city_muni_code', 10)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        DB::statement('ALTER TABLE hospitals ADD COLUMN point GEOMETRY(Point,4326)');
        DB::statement('CREATE INDEX idx_hospitals_point ON hospitals USING GIST(point)');

        // Market stats (TimescaleDB hypertable)
        DB::statement('CREATE TABLE IF NOT EXISTS market_stats (
            id BIGSERIAL,
            city_muni_code VARCHAR(12) NOT NULL,
            property_type VARCHAR(30) NOT NULL,
            period_start TIMESTAMPTZ NOT NULL,
            median_price_psm NUMERIC(12,2),
            transaction_count INTEGER DEFAULT 0,
            avg_days_on_market INTEGER,
            PRIMARY KEY (id, period_start)
        )');
        DB::statement("SELECT create_hypertable('market_stats', 'period_start', if_not_exists => TRUE)");
        DB::statement('CREATE INDEX idx_market_stats_location ON market_stats(city_muni_code, property_type, period_start DESC)');

        // Listing price history (TimescaleDB hypertable)
        DB::statement('CREATE TABLE IF NOT EXISTS listing_history (
            id BIGSERIAL,
            listing_id UUID NOT NULL,
            price_php NUMERIC(15,2) NOT NULL,
            status VARCHAR(20),
            recorded_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
            PRIMARY KEY (id, recorded_at)
        )');
        DB::statement("SELECT create_hypertable('listing_history', 'recorded_at', if_not_exists => TRUE)");
        DB::statement('CREATE INDEX idx_listing_history_listing ON listing_history(listing_id, recorded_at DESC)');

        // Listing embeddings (pgvector)
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        DB::statement('CREATE TABLE IF NOT EXISTS listing_embeddings (
            id BIGSERIAL PRIMARY KEY,
            listing_id UUID NOT NULL UNIQUE REFERENCES listings(id) ON DELETE CASCADE,
            embedding vector(1024),
            model_version VARCHAR(60),
            created_at TIMESTAMPTZ DEFAULT NOW(),
            updated_at TIMESTAMPTZ DEFAULT NOW()
        )');
        DB::statement('CREATE INDEX idx_listing_embeddings_ivfflat ON listing_embeddings USING ivfflat(embedding vector_cosine_ops) WITH (lists = 100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('hospitals');
        Schema::dropIfExists('schools');
        DB::statement('DROP TABLE IF EXISTS listing_embeddings');
        DB::statement('DROP TABLE IF EXISTS listing_history');
        DB::statement('DROP TABLE IF EXISTS market_stats');
        DB::statement('DROP TABLE IF EXISTS fault_lines');
        DB::statement('DROP TABLE IF EXISTS flood_zones');
    }
};
