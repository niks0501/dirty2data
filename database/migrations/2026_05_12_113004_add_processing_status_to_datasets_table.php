<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('datasets', function (Blueprint $table) {
            $table->string('status')->default('ready')->after('cleaning_log');
            $table->timestamp('processing_started_at')->nullable()->after('status');
            $table->timestamp('processing_finished_at')->nullable()->after('processing_started_at');
            $table->text('processing_error')->nullable()->after('processing_finished_at');
            $table->unsignedInteger('processing_progress')->default(0)->after('processing_error');
            $table->unsignedInteger('processing_rows_processed')->default(0)->after('processing_progress');
        });
    }

    public function down(): void
    {
        Schema::table('datasets', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'processing_started_at',
                'processing_finished_at',
                'processing_error',
                'processing_progress',
                'processing_rows_processed',
            ]);
        });
    }
};
