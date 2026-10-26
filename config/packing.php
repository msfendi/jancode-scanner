<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ambang qty pcs karton broken
    |--------------------------------------------------------------------------
    |
    | Jika sebuah jancode muncul lebih dari sekali dalam satu sheet dan salah
    | satu barisnya berisi pcs per karton di bawah nilai ini, baris tersebut
    | dianggap karton broken dan kode jancode-nya diberi akhiran "B" supaya
    | bisa dibedakan (dan tetap bisa discan).
    |
    */

    'broken_pcs_threshold' => (int) env('BROKEN_PCS_THRESHOLD', 60),

];
