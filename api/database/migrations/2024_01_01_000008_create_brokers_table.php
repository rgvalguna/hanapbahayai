<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brokers', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('prc_license_no', 30)->nullable();
            $table->string('status', 20)->default('pending'); // pending/active/suspended/revoked
            $table->string('veriff_session_id')->nullable();
            $table->timestamp('license_expires_at')->nullable();
            $table->jsonb('specializations')->default('[]'); // condo, lot, commercial
            $table->string('service_areas_json')->nullable();
            $table->decimal('avg_rating', 3, 2)->nullable();
            $table->integer('total_reviews')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brokers');
    }
};
