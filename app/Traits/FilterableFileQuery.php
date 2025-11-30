<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

trait FilterableFileQuery
{
    /**
     * * @param Builder $query
     * @param Request $request
     * @return Builder
     */
    protected function applyDmsFiltering(Builder $query, Request $request): Builder
    {
        $this->applyDateModifiedFilter($query, $request);
        $this->applyOwnerFilter($query, $request);
        $this->applyTypeFilter($query, $request);
        $this->applyLabelFilter($query, $request);

        return $query;
    }

    /**
     * Filter 1: Date Modified (files.updated_at)
     */
    protected function applyDateModifiedFilter(Builder $query, Request $request)
    {
        $dateFilter = $request->input('date_modified');

        if ($dateFilter && $dateFilter !== 'any_time') {
            $startDate = null;

            switch ($dateFilter) {
                case 'today':
                    $startDate = Carbon::today();
                    break;
                case 'last_week':
                    $startDate = Carbon::now()->subWeek();
                    break;
                case 'last_month':
                    $startDate = Carbon::now()->subMonth();
                    break;
                case 'last_year':
                    $startDate = Carbon::now()->subYear();
                    break;
            }

            if ($startDate) {
                $query->where('files.updated_at', '>=', $startDate);
            }
        }
    }

    /**
     * Filter 2: Owner (files.created_by)
     */
    protected function applyOwnerFilter(Builder $query, Request $request)
    {
        $ownerFilter = $request->input('owner');
        $userId = Auth::id();

        if ($ownerFilter && $ownerFilter !== 'anyone' && $userId) {
            switch ($ownerFilter) {
                case 'owned_by_me':
                    $query->where('files.created_by', $userId);
                    break;
                case 'not_owned_by_me':
                    $query->where('files.created_by', '!=', $userId);
                    break;
            }
        }
    }

    /**
     * Filter 3: Type/Extension (files.name)
     */
    protected function applyTypeFilter(Builder $query, Request $request)
    {
        $typeFilter = $request->input('type');

        if ($typeFilter && $typeFilter !== 'any_type') {
            $extension = strtolower($typeFilter);

            $query->where('files.is_folder', false)
                  ->where(DB::raw('LOWER(files.name)'), 'LIKE', '%.'.$extension);
        }
    }

    /**
     * Filter 4: Label
     */
    protected function applyLabelFilter(Builder $query, Request $request)
    {
        $labelFilter = $request->input('label');

        if ($labelFilter && $labelFilter !== 'any') {
            $query->whereHas('labels', function (Builder $q) use ($labelFilter) {
                $q->where('name', $labelFilter);
            });
        }
    }
}
