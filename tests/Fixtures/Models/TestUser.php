<?php

namespace N8n\Eloquent\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

class TestUser extends Model
{
    protected $table = 'test_users';

    protected $fillable = [
        'name',
        'email',
        'age',
    ];

    protected $casts = [
        'age' => 'integer',
    ];
} 