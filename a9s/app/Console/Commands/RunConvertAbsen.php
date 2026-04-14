<?php

namespace App\Console\Commands;


use App\Models\MySql\TrxAbsen;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RunConvertAbsen extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'run_convert_absen';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'RUN CONVERT ABSEN';

    /**
     * Execute the console command.
     *
     * @return int
     */

    public function handle()
    {

        $this->info("------------------------------------------------------------------------------------------\n ");
        $this->info("Start\n ");

        TrxAbsen::whereNull('gambar_loc')
        ->whereNotNull('gambar')
        ->chunkById(10, function ($trxabsens) {

            foreach ($trxabsens as $trxabsen) {
                $binary = "";
                if(mb_detect_encoding($trxabsen->gambar)===false){
                  $binary=$trxabsen->gambar;
                }else{
                  $binary=base64_decode($trxabsen->gambar);        
                }
                $ext = "png";
           
                $file_name = "{$trxabsen->trx_trp_id}_att".$trxabsen->status."_" . Str::uuid() . '.' . $ext;
                $path = "trx_trp/absen/{$file_name}";

                Storage::disk('public')->put($path, $binary, 'private');

                $trxabsen->update([
                    'gambar_loc' => $path,
                    // 'gambar' => null, 
                ]);
            }
        });        

        $this->info("Finish\n ");
        $this->info("------------------------------------------------------------------------------------------\n ");
    }
}
