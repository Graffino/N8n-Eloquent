<?php

namespace N8n\Eloquent\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

class TestUserCounter extends Model
{
    protected $table = 'test_user_counters';

    protected $fillable = [
        'counter_type',
        'count',
    ];

    protected $casts = [
        'count' => 'integer',
    ];
} 