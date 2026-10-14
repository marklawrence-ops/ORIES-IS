<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Target extends Model
{
    public const METRICS = [
        'proposals',
        'ongoing',
        'completed',
        'publications',
        'scopus',
        'commercialization',
        'policy',
        'technology_transfer',
    ];

    protected $fillable = ['year', 'office', 'metric', 'value'];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'value' => 'integer',
        ];
    }
}
