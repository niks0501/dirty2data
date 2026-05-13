<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the dataset_quality_scores table.
     *
     * Stores rule-based quality scores calculated after profiling.
     * score_type = 'before' for initial uploads; 'after' for post-cleaning comparisons.
     */
    public function up(): void
    {
        Schema::create('dataset_quality_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained('datasets')->cascadeOnDelete();

            // before (initial profile) or after (post-cleaning)
            $table->string('score_type', 20)->default('before')->index();

            // final score and human-readable status band
            $table->unsignedTinyInteger('quality_score');
            $table->string('status', 20)->nullable();

            // weighted sub-scores (0-100)
            $table->decimal('completeness_score', 5, 2)->nullable();
            $table->decimal('uniqueness_score', 5, 2)->nullable();
            $table->decimal('validity_score', 5, 2)->nullable();
            $table->decimal('consistency_score', 5, 2)->nullable();
            $table->decimal('type_accuracy_score', 5, 2)->nullable();

            // issue summary counts
            $table->unsignedInteger('missing_values')->default(0);
            $table->unsignedInteger('duplicate_rows')->default(0);
            $table->unsignedInteger('invalid_values')->default(0);
            $table->unsignedInteger('inconsistent_columns')->default(0);
            $table->unsignedInteger('type_issue_columns')->default(0);

            // structured JSON payloads for full breakdown, issues, and recommendations
            $table->json('breakdown')->nullable();
            $table->json('issues_summary')->nullable();
            $table->json('recommendation_summary')->nullable();

            $table->string('metadata_source', 50)->nullable();

            $table->timestamps();

            // one score_type per dataset for deterministic before/after tracking
            $table->unique(['dataset_id', 'score_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dataset_quality_scores');
    }
};
