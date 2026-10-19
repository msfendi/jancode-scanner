<?php

namespace App\Http\Controllers;

use App\Models\ImportSession;
use Illuminate\Http\Request;

/**
 * Session Import = induk sebelum import packing list.
 *
 * Alur: Start Session -> Import (bisa banyak file/revisi) -> Manage -> Scan -> Pallet.
 * Kode session dibuat otomatis: IMP-YYYYMMDD-HHMMSS-001.
 */
class ImportSessionController extends Controller
{
    /**
     * Halaman session: form start session + daftar session.
     */
    public function index()
    {
        $current  = ImportSession::current();
        $sessions = ImportSession::withCount(['importBatches', 'packingLists'])
            ->orderByDesc('id')
            ->get();

        return view('jancode.sessions', compact('current', 'sessions'));
    }

    /**
     * Mulai session baru (notes opsional). Session langsung dipilih sebagai aktif.
     */
    public function start(Request $request)
    {
        $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        $session = ImportSession::create([
            'code'       => ImportSession::generateCode(),
            'notes'      => $request->input('notes'),
            'status'     => 'open',
            'user_id'    => auth()->id(),
            'started_at' => now(),
        ]);

        ImportSession::setCurrent($session->id);

        if ($request->expectsJson()) {
            return response()->json([
                'success'  => true,
                'message'  => "Session {$session->code} dimulai. Silakan import packing list.",
                'id'       => $session->id,
                'code'     => $session->code,
                'redirect' => route('jancode.index'),
            ]);
        }

        return redirect()
            ->route('jancode.index')
            ->with('success', "Session {$session->code} dimulai. Silakan import packing list.");
    }

    /**
     * Pilih session sebagai session aktif (untuk manage / scan / lihat data lama).
     */
    public function select(Request $request, ImportSession $session)
    {
        ImportSession::setCurrent($session->id);

        $message = "Session {$session->code} dipilih.";

        if ($request->expectsJson()) {
            return response()->json([
                'success'  => true,
                'message'  => $message,
                'id'       => $session->id,
                'code'     => $session->code,
                'redirect' => route('jancode.index'),
            ]);
        }

        return redirect()->route('jancode.index')->with('success', $message);
    }

    /**
     * Tutup session (tidak bisa import/scan baru, data tetap tersimpan).
     */
    public function close(Request $request, ImportSession $session)
    {
        $session->close();
        $message = "Session {$session->code} ditutup.";

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return redirect()->route('session.index')->with('success', $message);
    }
}
