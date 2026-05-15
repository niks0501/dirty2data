<?php

namespace App\Models;

use Database\Factories\DatasetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dataset extends Model
{
    /** @use HasFactory<DatasetFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uploaded_by_id',
        'original_name',
        'disk_path',
        'mime_type',
        'extension',
        'size_bytes',
        'row_count',
        'column_count',
        'headers',
        'original_records',
        'cleaned_records',
        'preview',
        'profile',
        'cleaning_log',
        'cleaning_snapshots',
        'status',
        'processing_started_at',
        'processing_finished_at',
        'processing_error',
        'processing_progress',
        'processing_rows_processed',
    ];

    /**
     * Get the user who uploaded this dataset.
     *
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    /**
     * Get the quality scores associated with this dataset.
     *
     * @return HasMany<DatasetQualityScore, $this>
     */
    public function qualityScores(): HasMany
    {
        return $this->hasMany(DatasetQualityScore::class);
    }

    /**
     * Get AI-assisted cleaning recommendations for this dataset.
     *
     * @return HasMany<DatasetCleaningRecommendation, $this>
     */
    public function cleaningRecommendations(): HasMany
    {
        return $this->hasMany(DatasetCleaningRecommendation::class);
    }

    /**
     * Get AI-generated insights for this dataset.
     *
     * @return HasMany<DatasetAiInsight, $this>
     */
    public function aiInsights(): HasMany
    {
        return $this->hasMany(DatasetAiInsight::class);
    }

    /**
     * Get the latest 'before' quality score (pre-cleaning baseline).
     */
    public function latestBeforeQualityScore(): ?DatasetQualityScore
    {
        return $this->qualityScores()
            ->where('score_type', 'before')
            ->latest()
            ->first();
    }

    /**
     * Get the latest 'after' quality score (post-cleaning result).
     */
    public function latestAfterQualityScore(): ?DatasetQualityScore
    {
        return $this->qualityScores()
            ->where('score_type', 'after')
            ->latest()
            ->first();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'row_count' => 'integer',
            'column_count' => 'integer',
            'headers' => 'array',
            'original_records' => 'array',
            'cleaned_records' => 'array',
            'preview' => 'array',
            'profile' => 'array',
            'cleaning_log' => 'array',
            'cleaning_snapshots' => 'array',
            'processing_started_at' => 'datetime',
            'processing_finished_at' => 'datetime',
            'processing_progress' => 'integer',
            'processing_rows_processed' => 'integer',
        ];
    }
}
