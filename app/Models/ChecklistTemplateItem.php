<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistTemplateItem extends Model
{
    use HasFactory;

    protected $fillable = ['template_id', 'category', 'name', 'spec', 'sort_no'];

    protected $casts = ['sort_no' => 'integer'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplate::class);
    }
}
