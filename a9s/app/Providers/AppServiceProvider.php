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
        MyLog::logging("In2 boot", "retry_report");
    
        DB::extend('mysql', function ($config, $name) {
            MyLog::logging("Custom mysql connection resolver called", "retry_report");
    
            $factory = app(\Illuminate\Database\Connectors\ConnectionFactory::class);
            $connection = $factory->make($config, $name);
    
            $pdo = $connection->getPdo();
    
            return new class($pdo, $config['database'], $config['prefix'] ?? '', $config) extends MySqlConnection {
                public function run($query, $bindings, \Closure $callback)
                {    
                    $maxAttempts = 100;
                    $attempt = 0;
                    $delayMs = 200;
    
                    while ($attempt < $maxAttempts) {
                        try {
                            return parent::run($query, $bindings, $callback);
                        } catch (\Exception $e) {
                            $attempt++;

                            MyLog::logging("Retry DB Query [$attempt]: " . $e->getMessage(), "retry_report");
    
                            if (!$this->shouldRetry($e) || $attempt >= $maxAttempts) {
                                throw $e;
                            }
    
    
                            usleep($delayMs * 1000);
                        }
                    }
                }
    
                private function shouldRetry(\Exception $e): bool
                {
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
