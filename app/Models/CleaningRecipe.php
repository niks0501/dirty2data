<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CleaningRecipe extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'steps',
    ];

    protected function casts(): array
    {
        return [
            'steps' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
