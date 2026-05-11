<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TermGenerationLog extends Model
{
    protected $fillable = [
        'term_template_id',
        'asset_id',
        'assigned_user_id',
        'term_type',
        'generated_by_id',
        'generated_file_name',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(TermTemplate::class, 'term_template_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_id');
    }
}