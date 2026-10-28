<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Meeting extends Model
{
    protected $fillable = [
        'title',
        'raw_transcript',
        'summary',
        'attendees_json',
        'decisions_json',
    ];

    protected function casts(): array
    {
        return [
            'attendees_json' => 'array',
            'decisions_json' => 'array',
        ];
    }

    /** @return HasMany<ActionItem, $this> */
    public function actionItems(): HasMany
    {
        return $this->hasMany(ActionItem::class);
    }
}
