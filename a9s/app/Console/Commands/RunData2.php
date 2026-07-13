<?php

namespace App\Console\Commands;

use App\Helpers\MyLib;
use App\Helpers\MyLog;
use App\Http\Resources\MySql\TrxTrpAbsenResource;
use App\Models\MySql\TrxTrp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RunData2 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'run_data2';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'RUN DATA';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info("------------------------------------------------------------------------------------------\n ");
        $this->info("Start\n ");

        // // $date2  = "2025-10-30 00:00:00";
        // // $date1  = "2025-10-31 00:01:00";

        // // $datetime1 = \DateTime::createFromFormat('Y-m-d H:i:s', $date1);
        // // $datetime2 = \DateTime::createFromFormat('Y-m-d H:i:s', $date2);

        // // $interval = $datetime1->diff($datetime2);
        // // $totalHours = ($interval->days * 24) + $interval->h + ($interval->i / 60);

        // // echo "Difference: " . $totalHours . " hours";
        
        // $date1 = "2025-10-30 00:00:00";
        // $date2 = "2025-10-31 01:27:53";
        $this->checkGetTripStatus(1163);

        // $this->info(json_encode(MyLib::dateDiff($date1,$date2))."\n ");
        $this->info("Finish\n ");
        $this->info("------------------------------------------------------------------------------------------\n ");
    }

    public function checkGetTripStatus($emp_id){

        $model_query = TrxTrp::where(function ($q)use($emp_id){
        $q->where("supir_id",$emp_id);
        $q->orWhere("kernet_id",$emp_id);
        })->where(function ($q){
        $q->whereNull("ritase_leave_at");
        $q->orwhereNull("ritase_arrive_at");
        $q->orwhereNull("ritase_return_at");
        $q->orwhereNull("ritase_till_at");
        })
        // ->where("ritase_val2",0)
        ->whereIn('jenis',['CPO','PK','TBS','TBSK','CANGKANG'])
        ->where("deleted",0)
        ->where("req_deleted",0)
        ->where("tanggal",">=","2025-09-17")
        ->orderBy("id","asc")
        ->first();
        $this->info("Pass 1\n ");

        if(!$model_query)
        return response()->json([
            "data"=>["id"=>-1],
        ],200);
        $this->info("Pass 2\n ");


        $data = new TrxTrpAbsenResource($model_query);
        $this->info("Pass 3\n ");
        $data = collect($data);
        $this->info("Pass 4\n ");

        $data['tanggal'] = date("d-m-Y",strtotime($data['tanggal']));
        $data['ritase_leave_at'] = date("d-m-Y H:i:s",strtotime($data['ritase_leave_at']));
        $data['ritase_arrive_at'] = date("d-m-Y H:i:s",strtotime($data['ritase_arrive_at']));
        $data['ritase_return_at'] = date("d-m-Y H:i:s",strtotime($data['ritase_return_at']));
        $data['ritase_till_at'] = date("d-m-Y H:i:s",strtotime($data['ritase_till_at']));

        $data['supir_name'] = $model_query->employee_s->name;
        $data['kernet_name'] = $model_query->employee_k ? $model_query->employee_k->name : "";
        $this->info("Pass 5\n ");

        // $img_leaves = [];
        $data['img_leave']="";
        foreach ($model_query->trx_absens as $k => $v) {
        $this->info("Pass xx => ".$v->id);
        $this->info("\n ");
            
        // mb_convert_encoding($img, 'UTF-8', 'UTF-8')
        $img = ($v->gambar_loc) ? "data:image/png;base64,".base64_encode(Storage::disk('public')->get($v->gambar_loc)):null;
        if($v['status']=="B") 
            $data["img_leave"]   = $img;
    
        if($v['status']=="T") 
            $data["img_arrive"]   = $img;
    
        if($v['status']=="K") 
            $data["img_return"]   = $img;
    
        if($v['status']=="S") 
            $data["img_till"]   = $img;
        }
        $this->info("Pass 6\n ");

        $this->info(json_encode($data)."\n ");

    }
}
