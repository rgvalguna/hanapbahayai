<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('model', 60); // claude model slug
            $table->integer('total_input_tokens')->default(0);
            $table->integer('total_output_tokens')->default(0);
            $table->integer('total_cache_read_tokens')->default(0);
            $table->integer('total_cache_write_tokens')->default(0);
            $table->decimal('estimated_cost_usd', 8, 6)->default(0);
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('consultation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20); // user|assistant|tool
            $table->text('content')->nullable();
            $table->string('tool_name', 60)->nullable();
            $table->jsonb('tool_input')->nullable();
            $table->jsonb('tool_result')->nullable();
            $table->integer('input_tokens')->default(0);
            $table->integer('output_tokens')->default(0);
            $table->integer('cache_read_tokens')->default(0);
            $table->integer('cache_write_tokens')->default(0);
            $table->timestamps();

            $table->index('consultation_id');
        });

        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('listing_id')->constrained()->cascadeOnDelete();
            $table->integer('rank');
            $table->text('rationale')->nullable(); // Claude explanation
            $table->jsonb('score_snapshot')->nullable();
            $table->jsonb('financial_snapshot')->nullable();
            $table->timestamps();

            $table->unique(['consultation_id', 'listing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendations');
        Schema::dropIfExists('consultation_messages');
        Schema::dropIfExists('consultations');
    }
};
