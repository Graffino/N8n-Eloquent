<?php

namespace N8n\Eloquent\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use N8n\Eloquent\Traits\HasN8nEvents;

class TestUserCounter extends Model
{
    use HasN8nEvents;

    protected $table = 'test_user_counters';

    protected $fillable = [
        'counter_type',
        'count',
    ];

    protected $casts = [
        'count' => 'integer',
    ];
} 