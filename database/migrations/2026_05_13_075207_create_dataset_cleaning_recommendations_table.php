<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dataset_cleaning_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50);
            $table->string('model')->nullable();
            $table->string('status', 20)->default('suggested');
            $table->string('rec_id')->nullable();
            $table->string('column_name')->nullable();
            $table->text('issue');
            $table->string('severity', 20)->default('medium');
            $table->decimal('confidence', 4, 3)->default(0.500);
            $table->string('risk', 20)->default('medium');
            $table->json('suggested_steps');
            $table->json('before_examples')->nullable();
            $table->json('after_examples')->nullable();
            $table->text('reason')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();

            $table->index(['dataset_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dataset_cleaning_recommendations');
    }
};
