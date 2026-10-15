<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Jancode extends Model
{
    use HasFactory;

    protected $table = 'jancode';

    protected $fillable = [
        'import_batch_id',
        'source_row',
        'country',
        'buyer',
        'ocf',
        'style',
        'carton_no_from',
        'carton_no_to',
        'color',
        'size',
        'qty_per_carton',
        'jancode',
        'packing_list_qty_carton',
        'receive_qty_carton',
        'balance',
        'total_qty_pcs',
        'nw_per_carton',
        'ttl_nw',
        'gw_per_carton',
        'ttl_gw',
        'measurement_cm',
        'cbm',
        // Legacy column compatibility
        'qty',
        'destination',
        'cpo',
        'void',
    ];

    public function importBatch()
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    public function logs()
    {
        return $this->hasMany(JancodeLogs::class, 'jancode_id');
    }

    public function palletAssignmentDetails()
    {
        return $this->hasMany(PalletAssignmentDetail::class, 'jancode_id');
    }

    public function scanBatches()
    {
        return $this->hasMany(ScanBatch::class, 'jancode_id');
    }
}
