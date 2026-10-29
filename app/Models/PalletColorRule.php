<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PalletColorRule extends Model
{
    protected $table = 'pallet_color_rules';

    protected $fillable = [
        'group_key',
        'group_label',
        'buyer',
        'country',
        'status',
        'color',
        'text_color',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
