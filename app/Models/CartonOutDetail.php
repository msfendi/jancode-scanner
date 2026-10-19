<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Rincian baris KARTON KELUAR.
 *
 * Menyimpan informasi detail karton yang diambil packing: color, size, dan
 * kode jancode, beserta lokasi pallet asal bila karton sudah ditempatkan.
 * pallet_address_id = null berarti karton diambil dari yang belum di-pallet.
 */
class CartonOutDetail extends Model
{
    protected $table = 'carton_out_details';

    protected $fillable = [
        'carton_out_id',
        'jancode_id',
        'jancode',
        'color',
        'size',
        'qty_carton',
        'pallet_address_id',
        'pallet_code',
    ];

    public function cartonOut()
    {
        return $this->belongsTo(CartonOut::class, 'carton_out_id');
    }

    public function jancode()
    {
        return $this->belongsTo(Jancode::class, 'jancode_id');
    }

    public function palletAddress()
    {
        return $this->belongsTo(PalletAddress::class, 'pallet_address_id');
    }
}
