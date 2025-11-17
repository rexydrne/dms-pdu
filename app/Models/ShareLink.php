<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class ShareLink extends Model
{
    protected $guarded = [];

    protected $table = 'share_link';

    protected $fillable = [
        'file_id',
        'user_id',
        'permission_id',
        'path',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($link) {
            $link->token = Str::random(10);
        });
    }

    public function setPathAttribute($value)
    {
       $this->attributes['path'] = Crypt::encryptString($value);
    }

    public function getPathAttribute($value)
    {
        return Crypt::decryptString($value);
    }

    public function getUrl()
    {
        return url('api/s/' . $this->token . '/');
    }

    public function isExpired() {
        return $this->expires_at?->isPast();
    }

    public function file()
    {
        return $this->belongsTo(File::class, 'file_id');
    }
     public function user()
    {
        return $this->belongsTo(User::class, 'shared_to');
    }

    public function permission()
    {
        return $this->belongsTo(Permission::class, 'permission_id');
    }
}
