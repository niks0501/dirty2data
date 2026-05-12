<?php

namespace App\Models;

use Database\Factories\DatasetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        ];
    }
}
