<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('archetype', 50)->nullable();
            $table->jsonb('modifiers')->default('[]');
            $table->integer('onboarding_step')->default(0);
            $table->integer('family_size')->default(1);
            $table->integer('num_children')->default(0);
            $table->jsonb('children_ages')->default('[]');
            $table->string('purchase_purpose', 30)->nullable();
            $table->string('risk_tolerance', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
