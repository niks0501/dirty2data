<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatasetCleaningRecommendation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'dataset_id',
        'provider',
        'model',
        'status',
        'rec_id',
        'column_name',
        'issue',
        'severity',
        'confidence',
        'risk',
        'suggested_steps',
        'before_examples',
        'after_examples',
        'reason',
        'raw_response',
    ];

    /**
     * @return BelongsTo<Dataset, $this>
     */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'suggested_steps' => 'array',
            'before_examples' => 'array',
            'after_examples' => 'array',
            'raw_response' => 'array',
        ];
    }
}
