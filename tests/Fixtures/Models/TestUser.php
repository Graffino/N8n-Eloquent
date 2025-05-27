<?php

namespace N8n\Eloquent\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use N8n\Eloquent\Traits\HasN8nEvents;

class TestUser extends Model
{
    use HasN8nEvents;

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