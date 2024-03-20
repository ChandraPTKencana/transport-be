<?php

namespace App\Console\Commands;

use App\Helpers\MyLog;
use Illuminate\Console\Command;

use Illuminate\Support\Facades\Validator;
use App\Models\Stok\Transaction;

class RecalculateStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recalStock {hrm_revisi_lokasi_id} {item_id} {start_date}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate Stock';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $request=[];
        $request["start_date"]=$this->argument('start_date');
        
        $rules = [
            // 'start_date'=>"nullable|date_format:Y-m-d\TH:i:s",
            'start_date'=>"nullable|date_format:Y-m-d",
        ];
      
        $messages=[
            'start_date.date_format' => 'Format Start Date wrong',
        ];

        $validator = Validator::make($request,$rules,$messages);
  
        if ($validator->fails()) {
          $this->info(json_encode($validator->errors()->all()));        
          return;
        }
  
        $request["item_id"]=$this->argument('item_id');
        $request["lokasi_id"]=$this->argument('hrm_revisi_lokasi_id');

        $first = Transaction::whereNotNull('input_at')
        ->where('input_at',"<=",$request["start_date"])
        ->where("hrm_revisi_lokasi_id",$request['lokasi_id'])
        ->whereHas('details',function ($q)use($request) {
            $q->where("st_item_id",$request["item_id"]);            
        })
        ->orderBy("input_at","desc")
        ->first();
        
        


        MyLog::logging([
            "transaction"=>$first
        ]);

        $this->info("------------------------------------------------------------------------------------------\n ");
        $this->info("Finish\n ");
        $this->info("------------------------------------------------------------------------------------------\n ");

    }
}
