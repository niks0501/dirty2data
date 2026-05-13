<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatasetQualityScore extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'dataset_id',
        'score_type',
        'quality_score',
        'status',
        'completeness_score',
        'uniqueness_score',
        'validity_score',
        'consistency_score',
        'type_accuracy_score',
        'missing_values',
        'duplicate_rows',
        'invalid_values',
        'inconsistent_columns',
        'type_issue_columns',
        'breakdown',
        'issues_summary',
        'recommendation_summary',
        'metadata_source',
    ];

    /**
     * Get the dataset this quality score belongs to.
     *
     * @return BelongsTo<Dataset, $this>
     */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quality_score' => 'integer',
            'completeness_score' => 'decimal:2',
            'uniqueness_score' => 'decimal:2',
            'validity_score' => 'decimal:2',
            'consistency_score' => 'decimal:2',
            'type_accuracy_score' => 'decimal:2',
            'missing_values' => 'integer',
            'duplicate_rows' => 'integer',
            'invalid_values' => 'integer',
            'inconsistent_columns' => 'integer',
            'type_issue_columns' => 'integer',
            'breakdown' => 'array',
            'issues_summary' => 'array',
            'recommendation_summary' => 'array',
        ];
    }
}
