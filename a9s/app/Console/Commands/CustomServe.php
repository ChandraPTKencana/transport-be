<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CustomServe extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'custom_serve';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Serve the application from a custom public folder';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // $publicPath = base_path('front/public');
        $publicPath = public_path("/../../a9p");
        // Adjust the path to your new public folder location
        $this->info("Laravel development server started on http://0.0.0.0:8000");
        passthru("php -S 0.0.0.0:8000 -t $publicPath");
    }
}
