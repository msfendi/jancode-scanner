<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\JancodeScanController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RFIDController;

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

Route::group(['middleware' => 'auth'], function () {
    Route::get('/home', [HomeController::class, 'index'])->name('home');
    Route::get('/logout', [LoginController::class, 'logout'])->name('logout');

    //Register
    Route::get('/register/create', [RegisterController::class, 'create'])->name('register.create')->middleware(['auth', 'role:Admin']);
    Route::post('/register', [RegisterController::class, 'storeAuth'])->name('register.store')->middleware(['auth', 'role:Admin']);

    //Role
    Route::get('/role/index', [RoleController::class, 'index'])->name('role.index')->middleware(['auth', 'role:Admin']);
    Route::get('/role/delete/{id}', [RoleController::class, 'delete'])->name('role.delete')->middleware(['auth', 'role:Admin']);
    Route::get('/role/create', [RoleController::class, 'create'])->name('role.create')->middleware(['auth', 'role:Admin']);
    Route::post('/role/store', [RoleController::class, 'store'])->name('role.store')->middleware(['auth', 'role:Admin']);
    Route::get('/role/find/{id}', [RoleController::class, 'find'])->name('role.find')->middleware(['auth', 'role:Admin']);
    Route::post('/role/update', [RoleController::class, 'update'])->name('role.update')->middleware(['auth', 'role:Admin']);

    //User
    Route::get('/user/index', [UserController::class, 'index'])->name('user.index')->middleware(['auth', 'role:Admin']);
    Route::get('/user/profile', [UserController::class, 'profile'])->name('user.profile');
    Route::post('/user/update', [UserController::class, 'update'])->name('user.update')->middleware(['auth', 'role:Admin']);
    Route::get('/user/detail/{id}', [UserController::class, 'detail'])->name('user.detail')->middleware(['auth', 'role:Admin']);
    Route::get('/user/delete/{id}', [UserController::class, 'delete'])->name('user.delete')->middleware(['auth', 'role:Admin']);
    Route::get('/user/assign/{id}', [UserController::class, 'assign'])->name('user.assign')->middleware(['auth', 'role:Admin']);
    Route::post('/user/assignrole', [UserController::class, 'assignrole'])->name('user.assignrole')->middleware(['auth', 'role:Admin']);

    //Jancode
    Route::get('/jancode', [App\Http\Controllers\JancodeController::class, 'index'])->name('jancode.index')->middleware(['auth', 'role:Admin']);
    Route::post('/jancode', [App\Http\Controllers\JancodeController::class, 'store'])->name('jancode.store')->middleware(['auth', 'role:Admin']);
    Route::get('/jancode/{id}/edit', [App\Http\Controllers\JancodeController::class, 'edit'])->name('jancode.edit')->middleware(['auth', 'role:Admin']);
    Route::delete('/jancode/{id}', [App\Http\Controllers\JancodeController::class, 'destroy'])->name('jancode.destroy')->middleware(['auth', 'role:Admin']);
    Route::post('/jancode/import', [App\Http\Controllers\JancodeController::class, 'import'])->name('jancode.import')->middleware(['auth', 'role:Admin']);
    Route::get('/jancode/{id}/scan-logs', [App\Http\Controllers\JancodeController::class, 'scanLogs'])->name('jancode.scanLogs')->middleware(['auth', 'role:Admin']);
    Route::get('/jancode/{id}/export-scan-logs', [App\Http\Controllers\JancodeController::class, 'exportScanLogs'])->name('jancode.exportScanLogs')->middleware(['auth', 'role:Admin']);

    Route::prefix('scanner')->name('scanner.')->group(function () {
        Route::get('/', [JancodeScanController::class, 'index'])->name('index')->middleware(['auth', 'role:Admin']);
        Route::get('/count', [JancodeScanController::class, 'count'])->name('count')->middleware(['auth', 'role:Admin']);
        Route::post('/scan', [JancodeScanController::class, 'scan'])->name('scan')->middleware(['auth', 'role:Admin']);
        Route::delete('/void', [JancodeScanController::class, 'voidLast'])->name('void')->middleware(['auth', 'role:Admin']);
        Route::post('/reset', [JancodeScanController::class, 'resetLock'])->name('reset')->middleware(['auth', 'role:Admin']);
        Route::post('/submit', [JancodeScanController::class, 'submit'])->name('submit')->middleware(['auth', 'role:Admin']);
        Route::delete('/rollback', [JancodeScanController::class, 'rollback'])->name('rollback')->middleware(['auth', 'role:Admin']);
    });

    // Hangtag
    Route::prefix('hangtag')->name('hangtag.')->group(function () {
        // Hangtag Scanner
        Route::get('/scanner', [\App\Http\Controllers\HangtagScanController::class, 'index'])->name('scanner.index')->middleware(['auth', 'role:Admin']);
        Route::get('/scanner/count', [\App\Http\Controllers\HangtagScanController::class, 'count'])->name('scanner.count')->middleware(['auth', 'role:Admin']);
        Route::post('/scanner/scan', [\App\Http\Controllers\HangtagScanController::class, 'scan'])->name('scanner.scan')->middleware(['auth', 'role:Admin']);
        Route::delete('/scanner/void', [\App\Http\Controllers\HangtagScanController::class, 'voidLast'])->name('scanner.void')->middleware(['auth', 'role:Admin']);
        Route::post('/scanner/reset', [\App\Http\Controllers\HangtagScanController::class, 'resetLock'])->name('scanner.reset')->middleware(['auth', 'role:Admin']);
        Route::post('/scanner/submit', [\App\Http\Controllers\HangtagScanController::class, 'submit'])->name('scanner.submit')->middleware(['auth', 'role:Admin']);
        Route::delete('/scanner/rollback', [\App\Http\Controllers\HangtagScanController::class, 'rollback'])->name('scanner.rollback')->middleware(['auth', 'role:Admin']);

        Route::get('/', [\App\Http\Controllers\HangtagController::class, 'index'])->name('index')->middleware(['auth', 'role:Admin']);
        Route::post('/', [\App\Http\Controllers\HangtagController::class, 'store'])->name('store')->middleware(['auth', 'role:Admin']);
        Route::get('/{id}/edit', [\App\Http\Controllers\HangtagController::class, 'edit'])->name('edit')->middleware(['auth', 'role:Admin']);
        Route::delete('/{id}', [\App\Http\Controllers\HangtagController::class, 'destroy'])->name('destroy')->middleware(['auth', 'role:Admin']);
        Route::post('/import', [\App\Http\Controllers\HangtagController::class, 'import'])->name('import')->middleware(['auth', 'role:Admin']);
        Route::get('/{id}/scan-logs', [\App\Http\Controllers\HangtagController::class, 'scanLogs'])->name('scanLogs')->middleware(['auth', 'role:Admin']);
        Route::get('/{id}/export-scan-logs', [\App\Http\Controllers\HangtagController::class, 'exportScanLogs'])->name('exportScanLogs')->middleware(['auth', 'role:Admin']);
    });
});

Route::get('/rfid', [RFIDController::class, 'index'])->name('rfid.index');
Route::get('/rfid/data', [RFIDController::class, 'data'])->name('rfid.data');
Route::post('/rfid/clear', [RFIDController::class, 'clear'])->name('rfid.clear');
