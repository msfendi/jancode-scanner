<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;

/**
 * Menghapus cache route/config yang basi (lebih lama dari source-nya).
 *
 * `php artisan route:cache` / `config:cache` tidak otomatis invalidated oleh
 * `git pull`, sehingga route/permission baru tidak terbaca dan muncul error
 * seperti "Route [xxx] not defined". Cache basi dihapus di sini; request
 * berikutnya akan memakai source terbaru (cache dibangun ulang otomatis bila
 * perintah `route:cache` dijalankan lagi).
 */
class PurgeStaleCaches
{
    public function __construct(protected Application $app)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        try {
            $this->purge($this->app->getCachedRoutesPath(), base_path('routes/web.php'));
            $this->purge($this->app->getCachedConfigPath(), base_path('config'));
        } catch (\Throwable $e) {
            // Abaikan: tidak boleh menggagalkan request.
        }

        return $next($request);
    }

    private function purge(?string $cacheFile, string $source): void
    {
        if (! $cacheFile || ! is_file($cacheFile)) {
            return;
        }

        $cacheTime = @filemtime($cacheFile);

        if ($cacheTime === false) {
            return;
        }

        if (is_dir($source)) {
            $newest = 0;
            foreach (glob($source . '/*.php') ?: [] as $file) {
                $newest = max($newest, (int) @filemtime($file));
            }
        } else {
            $newest = (int) @filemtime($source);
        }

        if ($newest > $cacheTime) {
            @unlink($cacheFile);
        }
    }
}
