<?php

namespace App\Http\Controllers;

use App\Events\RFIDScanned;
use App\Models\RfidTag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RFIDController extends Controller
{
    /**
     * PAGE
     */
    public function index()
    {
        return view('rfid.index');
    }

    /**
     * RECEIVE RFID FROM ZEBRA
     */
    public function receive(Request $request)
    {
        try {

            /**
             * DEBUG LOG
             */
            Log::info('RFID RAW DATA', $request->all());

            /**
             * DATA DARI FX7500 BERUPA ARRAY
             */
            $payloads = $request->all();

            foreach ($payloads as $item) {

                /**
                 * VALIDASI DATA
                 */
                if (!isset($item['data'])) {
                    continue;
                }

                $data = $item['data'];

                /**
                 * EPC
                 */
                $epc = $data['idHex'] ?? '';

                if ($epc == '') {
                    continue;
                }

                /**
                 * ANTI DUPLICATE EPC
                 */
                $exist = RfidTag::where('epc', $epc)->first();

                if (!$exist) {

                    $rfid = RfidTag::create([

                        'epc'      => $epc,

                        'antenna'  => $data['antenna'] ?? null,

                        'rssi'     => $data['peakRSSI'] ?? null,

                        'read_at'  => now(),
                    ]);

                    /**
                     * WEBSOCKET REALTIME
                     */
                    broadcast(new RFIDScanned($rfid));
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'RFID received successfully'
            ]);
        } catch (\Exception $e) {

            Log::error($e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET ALL RFID
     */
    public function data()
    {
        return RfidTag::orderBy('id', 'desc')->get();
    }

    /**
     * CLEAR
     */
    public function clear()
    {
        RfidTag::truncate();

        return response()->json([
            'success' => true
        ]);
    }
}
