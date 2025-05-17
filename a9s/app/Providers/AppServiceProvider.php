<?php

namespace App\Providers;

use App\Helpers\MyLog;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\MySqlConnection;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        DB::resolverFor('mysql', function ($connection, $database, $prefix, $config) {
            $pdo = DB::connection($connection)->getPdo();

            return new class($pdo, $database, $prefix, $config) extends MySqlConnection {
                public function run($query, $bindings, \Closure $callback)
                {
                    $maxAttempts = 3;
                    $attempt = 0;
                    $delayMs = 200;

                    while ($attempt < $maxAttempts) {
                        try {
                            return parent::run($query, $bindings, $callback);
                        } catch (\Exception $e) {
                            // Bisa disesuaikan hanya untuk error tertentu
                            $isRetryable = $this->shouldRetry($e);
                            $attempt++;

                            if (!$isRetryable || $attempt >= $maxAttempts) {
                                throw $e;
                            }
                            MyLog::logging("Retry DB Query [$attempt]: " . $e->getMessage(),"retry_report");
                            usleep($delayMs * 1000); // delay dalam mikrodetik
                        }
                    }
                }

                private function shouldRetry(\Exception $e): bool
                {
                    // Bisa dibuat lebih canggih, misalnya hanya jika koneksi terputus
                    $message = $e->getMessage();

                    return str_contains($message, 'server has gone away')
                        || str_contains($message, 'Connection refused')
                        || str_contains($message, 'Connection timed out')
                        || str_contains($message, 'Lost connection')
                        || str_contains($message, 'Deadlock');
                }
            };
        });
    }
}
