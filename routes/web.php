<?php

use App\Http\Controllers\HangtagController;
use App\Http\Controllers\HangtagScanController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\JancodeController;
use App\Http\Controllers\JancodeScanController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/', [LoginController::class, 'login'])->name('/');

Route::group(['middleware' => 'guest'], function () {
    Route::get('/register', [RegisterController::class, 'index'])->name('register');
    Route::post('/register/guest', [RegisterController::class, 'store'])->name('register.guest');

    Route::get('/login', [LoginController::class, 'login'])->name('login');
    Route::post('/login', [LoginController::class, 'authenticate'])->name('login.post');
});

Route::get('/display/pallet', [\App\Http\Controllers\PalletController::class, 'publicDisplay'])->name('pallet.public');
Route::get('/display/pallet/stream', [\App\Http\Controllers\PalletController::class, 'stream'])->name('pallet.public.stream');
Route::get('/display/pallet/detail/{id}', [\App\Http\Controllers\PalletController::class, 'publicDetail'])->name('pallet.public.detail');

Route::group(['middleware' => 'auth'], function () {
    Route::get('/home', [HomeController::class, 'index'])->name('home');
    Route::get('/logout', [LoginController::class, 'logout'])->name('logout');

    //Register
    Route::get('/register/create', [RegisterController::class, 'create'])->name('register.create')->middleware(['auth', 'can:user.manage']);
    Route::post('/register', [RegisterController::class, 'storeAuth'])->name('register.store')->middleware(['auth', 'can:user.manage']);

    //Role
    Route::get('/role/index', [RoleController::class, 'index'])->name('role.index')->middleware(['auth', 'can:rbac.manage']);
    Route::get('/role/delete/{id}', [RoleController::class, 'delete'])->name('role.delete')->middleware(['auth', 'can:rbac.manage']);
    Route::get('/role/create', [RoleController::class, 'create'])->name('role.create')->middleware(['auth', 'can:rbac.manage']);
    Route::post('/role/store', [RoleController::class, 'store'])->name('role.store')->middleware(['auth', 'can:rbac.manage']);
    Route::post('/role/{id}/permissions', [RoleController::class, 'updatePermissions'])->name('role.permissions')->middleware(['auth', 'can:rbac.manage']);
    Route::get('/role/find/{id}', [RoleController::class, 'find'])->name('role.find')->middleware(['auth', 'can:rbac.manage']);
    Route::post('/role/update', [RoleController::class, 'update'])->name('role.update')->middleware(['auth', 'can:rbac.manage']);

    //User
    Route::get('/user/index', [UserController::class, 'index'])->name('user.index')->middleware(['auth', 'can:user.manage']);
    Route::get('/user/profile', [UserController::class, 'profile'])->name('user.profile');
    Route::post('/user/update', [UserController::class, 'update'])->name('user.update')->middleware(['auth', 'can:user.manage']);
    Route::get('/user/detail/{id}', [UserController::class, 'detail'])->name('user.detail')->middleware(['auth', 'can:user.manage']);
    Route::get('/user/delete/{id}', [UserController::class, 'delete'])->name('user.delete')->middleware(['auth', 'can:user.manage']);
    Route::get('/user/assign/{id}', [UserController::class, 'assign'])->name('user.assign')->middleware(['auth', 'can:user.manage']);
    Route::post('/user/assignrole', [UserController::class, 'assignrole'])->name('user.assignrole')->middleware(['auth', 'can:user.manage']);

    // Session Import (induk sebelum import packing list)
    Route::get('/session', [\App\Http\Controllers\ImportSessionController::class, 'index'])->name('session.index')->middleware(['auth', 'module:session']);
    Route::post('/session/start', [\App\Http\Controllers\ImportSessionController::class, 'start'])->name('session.start')->middleware(['auth', 'can:session.manage']);
    Route::post('/session/{session}/select', [\App\Http\Controllers\ImportSessionController::class, 'select'])->name('session.select')->middleware(['auth', 'module:session']);
    Route::post('/session/{session}/close', [\App\Http\Controllers\ImportSessionController::class, 'close'])->name('session.close')->middleware(['auth', 'can:session.manage']);
    Route::post('/session/{session}/reopen', [\App\Http\Controllers\ImportSessionController::class, 'reopen'])->name('session.reopen')->middleware(['auth', 'can:session.manage']);
    Route::get('/session/{session}/purge-preview', [\App\Http\Controllers\ImportSessionController::class, 'purgePreview'])->name('session.purgePreview')->middleware(['auth', 'can:session.hard-delete']);
    Route::post('/session/{session}/delete', [\App\Http\Controllers\ImportSessionController::class, 'destroy'])->name('session.destroy')->middleware(['auth', 'can:session.hard-delete']);

    //Jancode
    Route::get('/jancode', [JancodeController::class, 'index'])->name('jancode.index')->middleware(['auth', 'module:packing-list']);
    Route::post('/jancode', [JancodeController::class, 'store'])->name('jancode.store')->middleware(['auth', 'can:packing-list.manage']);
    Route::get('/jancode/{id}/edit', [JancodeController::class, 'edit'])->name('jancode.edit')->middleware(['auth', 'can:packing-list.manage']);
    Route::delete('/jancode/{id}', [JancodeController::class, 'destroy'])->name('jancode.destroy')->middleware(['auth', 'can:packing-list.manage']);
    Route::post('/jancode/import', [JancodeController::class, 'import'])->name('jancode.import')->middleware(['auth', 'can:packing-list.manage']);
    Route::post('/jancode/import-new', [JancodeController::class, 'importNewFormat'])->name('jancode.importNewFormat')->middleware(['auth', 'can:packing-list.manage']);
    Route::post('/jancode/ai-preview', [JancodeController::class, 'previewAiImport'])->name('jancode.aiPreview')->middleware(['auth', 'can:packing-list.manage']);
    Route::post('/jancode/ai-confirm', [JancodeController::class, 'confirmAiImport'])->name('jancode.aiConfirm')->middleware(['auth', 'can:packing-list.manage']);
    Route::get('/jancode/{id}/scan-logs', [JancodeController::class, 'scanLogs'])->name('jancode.scanLogs')->middleware(['auth', 'module:packing-list']);
    Route::get('/jancode/{id}/export-scan-logs', [JancodeController::class, 'exportScanLogs'])->name('jancode.exportScanLogs')->middleware(['auth', 'module:packing-list']);
    Route::get('/jancode/export-template', [JancodeController::class, 'exportTemplate'])->name('jancode.exportTemplate')->middleware(['auth', 'module:packing-list']);
    Route::get('/jancode/import-batches', [JancodeController::class, 'importBatches'])->name('jancode.importBatches')->middleware(['auth', 'module:packing-list']);
    Route::get('/jancode/import-batches/{id}', [JancodeController::class, 'importBatchDetail'])->name('jancode.importBatchDetail')->middleware(['auth', 'module:packing-list']);
    Route::get('/jancode/packing-lists', [JancodeController::class, 'packingLists'])->name('jancode.packingLists')->middleware(['auth', 'module:packing-list']);
    Route::get('/jancode/packing-lists/{packingList}/jancodes', [JancodeController::class, 'packingListJancodes'])->name('jancode.packingListJancodes')->middleware(['auth', 'module:packing-list']);
    Route::post('/jancode/packing-lists/{packingList}/finish', [JancodeController::class, 'finishPackingList'])->name('jancode.packingListFinish')->middleware(['auth', 'can:packing-list.manage']);
    Route::post('/jancode/packing-lists/{packingList}/clear-pallets', [\App\Http\Controllers\PalletController::class, 'clearForPackingList'])->name('jancode.packingListClearPallets')->middleware(['auth', 'module:pallet', 'can:pallet.manage']);
    Route::get('/jancode/packing-lists/{packingList}/report/pdf', [JancodeController::class, 'packingListReportPdf'])->name('jancode.packingListReportPdf')->middleware(['auth', 'module:packing-list']);
    Route::get('/jancode/packing-lists/{packingList}/report/excel', [JancodeController::class, 'packingListReportExcel'])->name('jancode.packingListReportExcel')->middleware(['auth', 'module:packing-list']);
    Route::delete('/jancode/packing-lists/{packingList}', [JancodeController::class, 'destroyPackingList'])->name('jancode.packingListDestroy')->middleware(['auth', 'can:packing-list.manage']);
    Route::get('/jancode/packing-list-filters', [JancodeController::class, 'packingListFilters'])->name('jancode.packingListFilters')->middleware(['auth', 'module:packing-list']);

    // Pallet Location & Placement
    Route::prefix('pallet')->name('pallet.')->middleware(['auth', 'module:pallet'])->group(function () {
        Route::get('/', [\App\Http\Controllers\PalletController::class, 'index'])->name('index');
        Route::get('/3d', [\App\Http\Controllers\PalletController::class, 'threeD'])->name('threeD');
        Route::get('/3d-data', [\App\Http\Controllers\PalletController::class, 'threeDData'])->name('threeDData');
        Route::get('/stream', [\App\Http\Controllers\PalletController::class, 'stream'])->name('stream');
        Route::get('/find-jancode', [\App\Http\Controllers\PalletController::class, 'findJancode'])->name('findJancode');
        Route::post('/{id}/country-mode', [\App\Http\Controllers\PalletController::class, 'updateCountryMode'])->name('countryMode');
        Route::post('/color-rules', [\App\Http\Controllers\PalletController::class, 'updateColorRules'])->name('colorRules')->middleware('can:pallet.manage');
        Route::get('/detail/{id}', [\App\Http\Controllers\PalletController::class, 'getDetail'])->name('detail');
        Route::post('/store', [\App\Http\Controllers\PalletController::class, 'storeAssignment'])->name('store');
        Route::post('/move', [\App\Http\Controllers\PalletController::class, 'moveCarton'])->name('move');
        Route::delete('/detail/{id}', [\App\Http\Controllers\PalletController::class, 'removeDetail'])->name('removeDetail');
        Route::delete('/clear/{addressId}', [\App\Http\Controllers\PalletController::class, 'clearPallet'])->name('clear');
    });

    Route::prefix('scanner')->name('scanner.')->group(function () {
        Route::get('/', [JancodeScanController::class, 'index'])->name('index')->middleware(['auth', 'module:scan']);
        Route::get('/packing-lists', [JancodeScanController::class, 'packingLists'])->name('packingLists')->middleware(['auth', 'module:scan']);
        Route::post('/load', [JancodeScanController::class, 'load'])->name('load')->middleware(['auth', 'module:scan']);
        Route::get('/rows', [JancodeScanController::class, 'rows'])->name('rows')->middleware(['auth', 'module:scan']);
        Route::get('/stream', [JancodeScanController::class, 'stream'])->name('stream')->middleware(['auth', 'module:scan']);
        Route::get('/delta', [JancodeScanController::class, 'delta'])->name('delta')->middleware(['auth', 'module:scan']);
        Route::get('/qr', [JancodeScanController::class, 'qr'])->name('qr')->middleware(['auth', 'module:scan']);
        Route::get('/join', [JancodeScanController::class, 'join'])->name('join')->middleware(['auth', 'module:scan']);
        Route::get('/search', [JancodeScanController::class, 'search'])->name('search')->middleware(['auth', 'module:scan']);
        Route::get('/count', [JancodeScanController::class, 'count'])->name('count')->middleware(['auth', 'module:scan']);
        Route::post('/scan', [JancodeScanController::class, 'scan'])->name('scan')->middleware(['auth', 'module:scan']);
        Route::delete('/void', [JancodeScanController::class, 'voidLast'])->name('void')->middleware(['auth', 'module:scan']);
        Route::post('/reset', [JancodeScanController::class, 'resetLock'])->name('reset')->middleware(['auth', 'module:scan']);
        Route::post('/submit', [JancodeScanController::class, 'submit'])->name('submit')->middleware(['auth', 'module:scan']);
        Route::delete('/rollback', [JancodeScanController::class, 'rollback'])->name('rollback')->middleware(['auth', 'module:scan']);
        Route::get('/batches', [JancodeScanController::class, 'batchHistory'])->name('batches')->middleware(['auth', 'module:scan']);
        Route::get('/logs', [JancodeScanController::class, 'scanLogHistory'])->name('logs')->middleware(['auth', 'module:scan']);
    });

    // Karton Keluar (scan out) — packing mengambil karton yang sudah discan / di pallet
    Route::prefix('carton-out')->name('cartonOut.')->middleware(['auth', 'module:carton-out'])->group(function () {
        Route::get('/', [\App\Http\Controllers\CartonOutController::class, 'index'])->name('index');
        Route::get('/packing-lists', [\App\Http\Controllers\CartonOutController::class, 'packingLists'])->name('packingLists');
        Route::post('/load', [\App\Http\Controllers\CartonOutController::class, 'load'])->name('load');
        Route::get('/rows', [\App\Http\Controllers\CartonOutController::class, 'rows'])->name('rows');
        Route::get('/lookup', [\App\Http\Controllers\CartonOutController::class, 'lookup'])->name('lookup');
        Route::post('/store', [\App\Http\Controllers\CartonOutController::class, 'store'])->name('store');
        Route::get('/history', [\App\Http\Controllers\CartonOutController::class, 'history'])->name('history');
    });

    // Hangtag
    Route::prefix('hangtag')->name('hangtag.')->group(function () {
        // Hangtag Scanner
        Route::get('/scanner', [HangtagScanController::class, 'index'])->name('scanner.index')->middleware(['auth', 'module:scan']);
        Route::get('/scanner/count', [HangtagScanController::class, 'count'])->name('scanner.count')->middleware(['auth', 'module:scan']);
        Route::post('/scanner/scan', [HangtagScanController::class, 'scan'])->name('scanner.scan')->middleware(['auth', 'can:scan.manage']);
        Route::delete('/scanner/void', [HangtagScanController::class, 'voidLast'])->name('scanner.void')->middleware(['auth', 'can:scan.manage']);
        Route::post('/scanner/reset', [HangtagScanController::class, 'resetLock'])->name('scanner.reset')->middleware(['auth', 'can:scan.manage']);
        Route::post('/scanner/submit', [HangtagScanController::class, 'submit'])->name('scanner.submit')->middleware(['auth', 'can:scan.manage']);
        Route::delete('/scanner/rollback', [HangtagScanController::class, 'rollback'])->name('scanner.rollback')->middleware(['auth', 'can:scan.manage']);

        Route::get('/', [HangtagController::class, 'index'])->name('index')->middleware(['auth', 'module:hangtag']);
        Route::post('/', [HangtagController::class, 'store'])->name('store')->middleware(['auth', 'module:hangtag']);
        Route::get('/{id}/edit', [HangtagController::class, 'edit'])->name('edit')->middleware(['auth', 'module:hangtag']);
        Route::delete('/{id}', [HangtagController::class, 'destroy'])->name('destroy')->middleware(['auth', 'module:hangtag']);
        Route::post('/import', [HangtagController::class, 'import'])->name('import')->middleware(['auth', 'module:hangtag']);
        Route::get('/{id}/scan-logs', [HangtagController::class, 'scanLogs'])->name('scanLogs')->middleware(['auth', 'module:hangtag']);
        Route::get('/{id}/export-scan-logs', [HangtagController::class, 'exportScanLogs'])->name('exportScanLogs')->middleware(['auth', 'module:hangtag']);
        Route::get('/export-template', [HangtagController::class, 'exportTemplate'])->name('exportTemplate')->middleware(['auth', 'module:hangtag']);
    });
});