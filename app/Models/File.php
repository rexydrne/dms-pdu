<?php

namespace App\Models;

use App\Traits\HasCreatorAndUpdater;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Kalnoy\Nestedset\NodeTrait;

class File extends Model
{
    use HasFactory, HasCreatorAndUpdater, SoftDeletes, NodeTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'path',
        'storage_path',
        '_lft',
        '_rgt',
        'parent_id',
        'is_folder',
        'mime',
        'size',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_folder' => 'boolean',
        'size' => 'integer',
    ];

    /**
     * Get the user that created the file.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user that last updated the file.
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(File::class, 'parent_id')->withTrashed();
    }

    public function owner(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                return $attributes['created_by'] == Auth::id() ? 'me' : $this->user->fullname;
            }
        );
    }

    public function isOwnedBy($userId): bool
    {
        return $this->created_by == $userId;
    }

    public function isRoot()
    {
        return $this->parent_id === null;
    }

    public function getCumulativeSize(): int
    {
        if (!$this->is_folder) {
            return $this->size ?? 0;
        }

        $totalSize = $this->descendants()
            ->where('is_folder', false)
            ->sum('size');

        return (int) $totalSize;
    }

    public function get_file_size()
    {
        $sizeInBytes = $this->is_folder ? $this->getCumulativeSize() : $this->size;

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $power = $sizeInBytes > 0 ? floor(log($sizeInBytes, 1024)) : 0;

        return number_format($sizeInBytes / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->parent) {
                return;
            }
            $model->path = ( !$model->parent->isRoot() ? $model->parent->path . '/' : '' ) . Str::slug($model->name);
        });
    }

    public function shares()
    {
        return $this->belongsToMany(User::class, 'shareables', 'file_id', 'shared_to')
            ->withPivot('permission_id')
            ->withTimestamps();
    }

    public function shareables()
    {
        return $this->hasMany(Shareable::class, 'file_id');
    }

    public function moveToTrashWithDescendants()
    {
        $nodes = $this->descendants()->with('shareables')->get()->push($this);

        \App\Models\Shareable::whereIn('file_id', $nodes->pluck('id'))->delete();

        foreach ($nodes as $node) {
            $node->delete();
        }
    }

    public function labels()
    {
        return $this->belongsToMany(Label::class, 'file_labels', 'file_id', 'label_id')
            ->withTimestamps();
    }

}
