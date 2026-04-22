<?php

namespace App\Console\Commands;


use App\Models\MySql\TrxAbsenForLoop;
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

        // TrxAbsen::whereNull('gambar_loc')
        // ->whereNotNull('gambar')
        // ->chunkById(10, function ($trxabsens) {

        //     foreach ($trxabsens as $trxabsen) {
        //         $binary = "";
        //         if(mb_detect_encoding($trxabsen->gambar)===false){
        //             $this->info("mbdetect\n ");
                    
        //           $binary=$trxabsen->gambar;
        //         }else{
        //             $this->info("nodetect\n ");

        //           $binary=base64_decode($trxabsen->gambar);        
        //         }
        //         $ext = "png";
           
        //         $file_name = "{$trxabsen->trx_trp_id}_att".$trxabsen->status."_" . Str::uuid() . '.' . $ext;
        //         $path = "trx_trp/absen/{$file_name}";

        //         Storage::disk('public')->put($path, $binary, 'private');

        //         $trxabsen->update([
        //             'gambar_loc' => $path,
        //             // 'gambar' => null, 
        //         ]);
        //     }
        // });  
        
        TrxAbsenForLoop::whereNull('gambar_loc')
        ->whereNotNull('gambar')
        ->chunkById(10, function ($trxabsens) {

        foreach ($trxabsens as $trxabsen) {
            try {
                $data = $trxabsen->gambar;
                $binary = null;

                // =========================
                // 1. Handle data URI
                // =========================
                if (is_string($data) && str_starts_with($data, 'data:image')) {
                    $this->info("data-uri: {$trxabsen->id}");

                    $parts = explode('base64,', $data);
                    if (count($parts) === 2) {
                        $data = $parts[1];
                    }
                }

                // =========================
                // 2. Try decode base64
                // =========================
                if (is_string($data)) {
                    $decoded = base64_decode($data, true);

                    if ($decoded !== false && strlen($decoded) > 20) {
                        // cek apakah hasil decode itu image valid
                        if ($this->isImageBinary($decoded)) {
                            $this->info("base64 image: {$trxabsen->id}");
                            $binary = $decoded;
                        }
                    }
                }

                // =========================
                // 3. Fallback: anggap binary asli
                // =========================
                if (!$binary) {
                    if ($this->isImageBinary($data)) {
                        $this->info("raw binary: {$trxabsen->id}");
                        $binary = $data;
                    }
                }

                // =========================
                // 4. Validasi akhir
                // =========================
                if (!$binary) {
                    $sample = $trxabsen->gambar;
                    $this->info("LEN: " . strlen($sample));
                    $this->info("HEAD HEX: " . bin2hex(substr($sample, 0, 20)));
                    $this->info("HEAD TEXT: " . substr($sample, 0, 50));

                    $this->error("INVALID IMAGE: {$trxabsen->id}");
                    continue;
                }

                // =========================
                // 5. Detect extension
                // =========================
                $ext = $this->detectImageExtension($binary);

                if (!$ext) {
                    $this->error("UNKNOWN FORMAT: {$trxabsen->id}");
                    continue;
                }

                // =========================
                // 6. Simpan file
                // =========================
                $file_name = "{$trxabsen->trx_trp_id}_att{$trxabsen->status}_" . Str::uuid() . "." . $ext;
                $path = "trx_trp/absen/{$file_name}";

                Storage::disk('public')->put($path, $binary);

                // =========================
                // 7. Update DB
                // =========================
                $trxabsen->update([
                    'gambar_loc' => $path,
                    // 'gambar' => null, // optional
                ]);

            } catch (\Throwable $e) {
                $this->error("ERROR ID {$trxabsen->id}: " . $e->getMessage());
            }
        }
    });


        

        $this->info("Finish\n ");
        $this->info("------------------------------------------------------------------------------------------\n ");
    }

    // =========================
// Helper: cek binary image
// =========================
private function isImageBinary($binary)
{
    if (!is_string($binary) || strlen($binary) < 10) {
        return false;
    }

    return str_starts_with($binary, "\x89PNG") ||   // PNG
           str_starts_with($binary, "\xFF\xD8\xFF") || // JPG
           str_starts_with($binary, "RIFF"); // WEBP
}


// =========================
// Helper: detect extension
// =========================
private function detectImageExtension($binary)
{
    if (str_starts_with($binary, "\x89PNG")) {
        return 'png';
    }

    if (str_starts_with($binary, "\xFF\xD8\xFF")) {
        return 'jpg';
    }

    if (str_starts_with($binary, "RIFF") && str_contains($binary, "WEBP")) {
        return 'webp';
    }

    return null;
}
}
