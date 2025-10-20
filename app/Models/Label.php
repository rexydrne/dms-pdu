<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Label extends Model
{
    protected $fillable = [
        'name',
        'color',
        'created_by',
    ];

    public function files()
    {
        return $this->belongsToMany(File::class, 'file_has_labels', 'label_id', 'file_id')
            ->withTimestamps();
    }
}
