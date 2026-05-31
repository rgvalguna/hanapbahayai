<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_finances', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->integer('version')->default(1);
            $table->boolean('is_current')->default(true);

            // Income
            $table->decimal('gross_monthly_income_php', 12, 2)->nullable();
            $table->string('currency', 3)->default('PHP');
            $table->string('employment_type', 30)->nullable(); // employed/self_employed/ofw/business_owner/retired

            // Co-borrower
            $table->boolean('has_co_borrower')->default(false);
            $table->decimal('co_borrower_income_php', 12, 2)->nullable();

            // Obligations
            $table->decimal('monthly_obligations_php', 12, 2)->default(0);

            // Down payment
            $table->decimal('available_down_payment_php', 12, 2)->nullable();
            $table->decimal('monthly_savings_php', 12, 2)->nullable();

            // Pag-IBIG
            $table->boolean('pagibig_member')->default(false);
            $table->integer('pagibig_contributions_months')->nullable();
            $table->boolean('pagibig_contributions_current')->default(false);

            $table->timestamps();

            $table->index(['user_id', 'is_current']);
            $table->unique(['user_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_finances');
    }
};
