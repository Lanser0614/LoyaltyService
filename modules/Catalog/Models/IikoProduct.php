<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IikoProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'external_id',
        'group_external_id',
        'name',
        'type',
        'is_active',
        'raw_payload',
    ];

    protected $casts = [
        'is_active' => 'bool',
        'raw_payload' => 'array',
    ];
}
