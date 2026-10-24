<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'users';

    protected $guarded = ['id'];

    protected $hidden = ['password'];

    protected $casts = [
        'is_active' => 'boolean',
        'is_admin' => 'boolean',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }
}
