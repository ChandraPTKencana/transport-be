<?php

namespace App\Providers;

use App\Helpers\MyLog;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Connectors\ConnectionFactory;
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
        MyLog::logging("In1 bootx","retry_report");
        DB::resolverFor('mysql', function ($connection, $database, $prefix, $config) {
            MyLog::logging("bIn getPdo","retry_report");
            // $pdo = DB::connection($connection)->getPdo();
            // $pdo = app('db.factory')->make($config)->getPdo();
            // $pdo = app(ConnectionFactory::class)->make($config)->getPdo();
            $pdo = new \PDO(
                "mysql:host={$config['host']};dbname={$database};port={$config['port']}",
                $config['username'],
                $config['password'],
                $config['options'] ?? []
            );
            MyLog::logging("aIn getPdo","retry_report");

            return new class($pdo, $database, $prefix, $config) extends MySqlConnection {
                public function run($query, $bindings, \Closure $callback)
                {
                    MyLog::logging("In DB Query","retry_report");
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
