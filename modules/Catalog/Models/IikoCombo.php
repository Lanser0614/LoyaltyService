<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IikoCombo extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'external_id',
        'name',
        'status',
        'is_active',
        'raw_payload',
    ];

    protected $casts = [
        'is_active' => 'bool',
        'raw_payload' => 'array',
    ];
}
