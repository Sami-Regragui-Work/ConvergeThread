<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MergeSessionGroup extends Pivot
{
    protected $table = 'merge_session_groups';

    protected $fillable = [];

    public function mergeSession(): BelongsTo
    {
        return $this->belongsTo(MergeSession::class, 'merge_session_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}