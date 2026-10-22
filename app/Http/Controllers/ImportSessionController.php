<?php

namespace App\Http\Controllers;

use App\Models\ImportSession;
use App\Models\PackingList;
use App\Services\SessionPurgeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Session Import = induk sebelum import packing list.
 *
 * Alur: Start Session -> Import (bisa banyak file/revisi) -> Manage -> Scan -> Pallet.
 * Kode session dibuat otomatis: IMP-YYYYMMDD-HHMMSS-001.
 */
class ImportSessionController extends Controller
{
    /**
     * Halaman session: form start session + daftar session (bisa dicari).
     */
    public function index(Request $request)
    {
        $search   = trim((string) $request->input('q', ''));
        $status   = $request->input('status');

        $current  = ImportSession::current();
        $sessions = ImportSession::withCount(['importBatches', 'packingLists'])
            ->search($search)
            ->when(in_array($status, ['open', 'closed'], true), fn ($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->get();

        $stats = [
            'total'         => ImportSession::count(),
            'open'          => ImportSession::where('status', 'open')->count(),
            'closed'        => ImportSession::where('status', 'closed')->count(),
            'packing_lists' => PackingList::count(),
        ];

        // Tombol hapus permanen hanya ditampilkan bila route-nya benar-benar
        // terdaftar (aman kalau route cache server belum di-refresh).
        $canHardDelete = Route::has('session.destroy');

        return view('jancode.sessions', compact('current', 'sessions', 'search', 'status', 'stats', 'canHardDelete'));
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
                'success'     => true,
                'message'     => "Session {$session->code} dimulai. Silakan import packing list.",
                'id'          => $session->id,
                'code'        => $session->code,
                'group_label' => $session->group_label,
                'redirect'    => route('jancode.index'),
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
                'success'     => true,
                'message'     => $message,
                'id'          => $session->id,
                'code'        => $session->code,
                'group_label' => $session->group_label,
                'redirect'    => route('jancode.index'),
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

    /**
     * Buka kembali session yang sudah ditutup (pekerjaan belum selesai).
     * Session langsung dijadikan session aktif.
     */
    public function reopen(Request $request, ImportSession $session)
    {
        // Satu kombinasi style|OCF|destination|warehouse hanya boleh punya
        // satu session OPEN. Jika ada session lain yang sudah OPEN untuk
        // kombinasi sama, pakai session itu.
        if ($session->group_key) {
            $other = ImportSession::query()
                ->where('group_key', $session->group_key)
                ->where('status', 'open')
                ->where('id', '!=', $session->id)
                ->first();

            if ($other) {
                ImportSession::setCurrent($other->id);
                $message = "Kombinasi ini sudah aktif pada session {$other->code}. Dialihkan ke session tersebut.";

                if ($request->expectsJson()) {
                    return response()->json([
                        'success'     => true,
                        'message'     => $message,
                        'id'          => $other->id,
                        'code'        => $other->code,
                        'group_label' => $other->group_label,
                        'redirect'    => route('jancode.packingLists'),
                    ]);
                }

                return redirect()->route('jancode.packingLists')->with('success', $message);
            }
        }

        $session->reopen();
        ImportSession::setCurrent($session->id);

        $message = "Session {$session->code} dibuka kembali. Silakan lanjutkan import / scan.";

        if ($request->expectsJson()) {
            return response()->json([
                'success'     => true,
                'message'     => $message,
                'id'          => $session->id,
                'code'        => $session->code,
                'group_label' => $session->group_label,
                'redirect'    => route('jancode.packingLists'),
            ]);
        }

        return redirect()->route('jancode.packingLists')->with('success', $message);
    }

    /**
     * Ringkasan data yang akan terhapus saat session dihapus permanen.
     */
    public function purgePreview(Request $request, ImportSession $session, SessionPurgeService $purge)
    {
        $counts = $purge->preview($session);

        return response()->json([
            'success' => true,
            'code'    => $session->code,
            'counts'  => $counts,
        ]);
    }

    /**
     * Hapus permanen (hard delete) session beserta seluruh datanya:
     * revisi import, packing list, jancode, log scan, batch scan, pallet, dan
     * karton keluar.
     *
     * Hanya Super Admin (permission `session.hard-delete`).
     */
    public function destroy(Request $request, ImportSession $session, SessionPurgeService $purge)
    {
        $code = $session->code;

        $counts = $purge->purge($session);

        // Session aktif & lock scan tidak boleh menunjuk session yang dihapus.
        if ((int) session(ImportSession::SESSION_KEY) === (int) $session->id) {
            ImportSession::setCurrent(null);
        }
        session()->forget('scan_import_batch_id');

        $message = "Session {$code} beserta seluruh datanya dihapus permanen ("
            . ($counts['packing_lists'] ?? 0) . " packing list, "
            . ($counts['batches'] ?? 0) . " revisi, "
            . ($counts['jancodes'] ?? 0) . " jancode, "
            . ($counts['jancode_logs'] ?? 0) . " log scan).";

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message, 'counts' => $counts]);
        }

        return redirect()->route('session.index')->with('success', $message);
    }
}
