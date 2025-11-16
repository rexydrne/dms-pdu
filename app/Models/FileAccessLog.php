<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FileAccessLog extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $fillable =
    [
        'user_id',
        'file_id',
        'last_accessed_at'
    ];

    public function file()
    {
        return $this->belongsTo(File::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
