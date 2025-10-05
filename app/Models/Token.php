<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Token extends Model
{
    protected $table = 'password_reset_tokens';
    protected $fillable = ['user_id', 'token', 'expires_at', 'used'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
