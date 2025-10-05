<?php

namespace App\Traits;
use Illuminate\Support\Facades\Auth;

trait HasCreatorAndUpdater
{
    protected static function bootHasCreatorAndUpdater()
    {
        static::creating(function($model) {
            if (empty($model->created_by)) {
                $model->created_by = Auth::id();
            }
            if (empty($model->updated_by)) {
                $model->updated_by = Auth::id();
            }
        });

        static::updating(function($model) {
            $model->updated_by = Auth::id() ?? $model->updated_by;
        });
    }
}




