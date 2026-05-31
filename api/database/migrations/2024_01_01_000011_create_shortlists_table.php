<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shortlists', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('shortlist_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shortlist_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('listing_id')->constrained()->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['shortlist_id', 'listing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shortlist_listings');
        Schema::dropIfExists('shortlists');
    }
};
