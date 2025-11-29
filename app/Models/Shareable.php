<?php

namespace App\Models;

use Illuminate\Database\Eloquent\BroadcastsEvents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class Shareable extends Model
{
    protected $table = 'shareables';

    protected $fillable = [
        'file_id',
        'shared_to',
        'role_id',
        'shared_by',
        'token'
    ];

    public function file()
    {
        return $this->belongsTo(File::class, 'file_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'shared_to');
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}
