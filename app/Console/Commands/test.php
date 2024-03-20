<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Illuminate\Http\Request;

use File;
use DB;

use \App\Helpers\MyLib;
use \App\Helpers\EzLog;
use \App\Model\AirLimbahSensor;
use \App\Model\AirLimbahFlowMeter;

use Illuminate\Support\Facades\Validator;

class dataSimplification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dataSimplification {id} {end_date} {start_date}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make All Data To More Reasonable';

    /**
     * Create a new command instance.
     *
     * @return void
     */

    private $date_time_spacer;
    private $date_from_data;
    private $limit = 0;
    private $end_date = "";
    private $start_date = "";



    public function __construct()
    {
      parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
      $this->info("==============".date("Y-m-d H:i:s")."============== \n ");


      $request=[];
      $request["end_date"]=$this->argument('end_date');
      $request["start_date"]=$this->argument('start_date');

      $rules = [
        'end_date' => 'nullable|date_format:Y-m-d\TH:i:s',
        'start_date'=>"nullable|date_format:Y-m-d\TH:i:s",
      ];
  
      $messages=[
        'end_date.date_format' => 'Format End Date wrong',
        'start_date.date_format' => 'Format Start Date wrong',
      ];
  
      $validator = Validator::make($request,$rules,$messages);
  
      if ($validator->fails()) {
        $this->info(json_encode($validator->errors()->all()));        
        return;
      }


      $ed = $sd = 0;
      if($request["end_date"]){
        $this->end_date = $request["end_date"];
      }
      if($request["start_date"]){
        $this->start_date = $request["start_date"];
      }

      if($request["end_date"] && $request["start_date"]){
        if(strtotime($request["end_date"]) < strtotime($request["start_date"])){
          $this->info("wrong date => date end must after date start");        
          return;
        }
      }

      // GET UTC Date
      $now = new \DateTime();

      // SET TO IND Date
      $now->add(new \DateInterval('PT7H'));

      // GET First Date of Month 
      $first_date = $now->format('Y-m')."-01 00:00:00";

      // SET Date To First Date
      $dt = new \DateTime($first_date);

      // Reduce 2 Months
      $dt->sub(new \DateInterval('P2M'));
      $this->date_time_spacer=$dt; 

      // Get Date after reduce
      $sd = $dt->format('Y-m-d H:i:s');

      // $this->info("==============".$sd."============== \n ");

      $this->getData();
    }


    public function getData()
    {
      
      // $this->info("xxxxxx".MyLib::utcMillis($this->start_date)."xxxxxx \n ");

      // return;
      // $flowmeters = AirLimbahSensor::get();
      // foreach ($flowmeters as $k => $v) {
      //   $air_limbah = AirLimbahFlowMeter::where("air_limbah_sensor_id",$v->id)->orderBy("created_at","asc")->first();
      //   $this->recrusiveData($v->id,$air_limbah);
      // }
      $sensor_id = $this->argument('id');

      if($this->start_date){
        $air_limbah = AirLimbahFlowMeter::where("air_limbah_sensor_id",$sensor_id);
        $air_limbah = $air_limbah->where("created_at","<",MyLib::utcMillis($this->start_date));
        $air_limbah = $air_limbah->orderBy("created_at","desc")->first();

        if(!$air_limbah){
          $air_limbah = AirLimbahFlowMeter::where("air_limbah_sensor_id",$sensor_id);
          $air_limbah = $air_limbah->where("created_at",">=",MyLib::utcMillis($this->start_date));
          $air_limbah = $air_limbah->orderBy("created_at","asc")->first();

          if(!$air_limbah){
            $this->info("Tidak ada Data untuk tanggal yang diminta"."\n ");
            return;
          }

          $this->injectFirstData($sensor_id,$air_limbah);
        }

      }else {
        $air_limbah = AirLimbahFlowMeter::where("air_limbah_sensor_id",$sensor_id);
        $air_limbah = $air_limbah->orderBy("created_at","asc")->first();

        $this->injectFirstData($sensor_id,$air_limbah);
      }
      // $air_limbah = $air_limbah->orderBy("created_at","asc")->first();

      

      // EzLog::logging([
      //   "data"=>json_encode($air_limbah)
      // ],"xx");
    

      $this->recrusiveData($sensor_id,$air_limbah,$air_limbah);
    }

    //   $this->info("same date \n ");
    public function recrusiveData($sensor_id,$first,$last,$count=1){

      $arr_range_date=$this->rangeDate($first->created_at);

      if($this->end_date!=="" && MyLib::utcMillis($this->end_date) <= $arr_range_date["from_millis_utc"]) {
        return;
      }
      
      
      $last_data_of_range = AirLimbahFlowMeter::where("air_limbah_sensor_id",$sensor_id)
      ->where("created_at",">=",$arr_range_date["from_millis_utc"])
      ->where("created_at","<",$arr_range_date["to_millis_utc"])
      ->orderBy("created_at","desc")->first();
      
      
      if($this->start_date == "" || MyLib::utcMillis($this->start_date) <=  $arr_range_date["from_millis_utc"]){
        $deviation = $last_data_of_range->total_val - $last->total_val;
        $realtimeval = ($deviation / ($arr_range_date["to_millis_utc"] - $arr_range_date["from_millis_utc"])) * 3600000;


        EzLog::logging([
          "_"=>$arr_range_date["date_to_data"],
          "for_sql"=>[
            "millis_from_utc"=>$arr_range_date["from_millis_utc"],
            "millis_to_utc"=>$arr_range_date["to_millis_utc"],
            "date_from_local"=>$arr_range_date["from_date_local"],
            "date_to_local"=>$arr_range_date["to_date_local"],
          ],
          
          "first_millis_utc"=>$last->created_at,
          "last_millis_utc"=>$last_data_of_range->created_at,
  
          "first_date_local"=>MyLib::millisToDateLocal($last->created_at),
          "last_date_local"=>MyLib::millisToDateLocal($last_data_of_range->created_at),
          
          "first_totalizer"=>$last->total_val,
          "last_totalizer"=>$last_data_of_range->total_val,
          "diff_totalizer"=> $deviation,
          
          "creating"=>[
            "created_at"=>$arr_range_date["millis_to_data_check"],
            "air_limbah_sensor_id"=>$sensor_id,
            "location_id"=>$last_data_of_range->location_id,
            "real_time_val"=>$realtimeval,
            "total_val"=>$last_data_of_range->total_val,
            "u_id"=>null,
            "deviation"=>$deviation,
          ]       
          
        ],"test_dataxy");
  
  
        
        DB::beginTransaction();
        try {
          AirLimbahFlowMeter::where("air_limbah_sensor_id",$sensor_id)
          ->where("location_id",$last_data_of_range->location_id)
          ->where("created_at",">=",$arr_range_date["from_millis_utc"])
          ->where("created_at","<",$arr_range_date["to_millis_utc"])->delete();
          
          AirLimbahFlowMeter::insert([
            "created_at"=>$arr_range_date["millis_to_data_check"],
            "air_limbah_sensor_id"=>$sensor_id,
            "location_id"=>$last_data_of_range->location_id,
            "real_time_val"=>$realtimeval,
            "total_val"=>$last_data_of_range->total_val,
            "u_id"=>null,
            "deviation"=>$deviation,
          ]);
    
          DB::commit();
        } catch (\Exception $e) {
          DB::rollback();
          $this->info("DB ERR".$e->getMessage()."\n ");
          return;
        }
      }
      

      

      if($this->limit > 0 && $this->limit <= $count){
        $this->info("==============".date("Y-m-d H:i:s")."============== \n ");
        return;
      }else {
        // $this->recrusiveData(1,$arr_range_date["to_millis_utc"],$last_data_of_range,$count+1);
        $first_data_of_range = AirLimbahFlowMeter::where("air_limbah_sensor_id",$sensor_id)
        ->where("created_at",">=",$arr_range_date["to_millis_utc"])
        ->orderBy("created_at","asc")->first();
        if($first_data_of_range)
          $this->recrusiveData($sensor_id,$first_data_of_range,$last_data_of_range,$count+1);
        else 
          return;
      }
    }
    
    public function rangeDate($millis){

      $data_date = date("Y-m-d H:i:s", $millis / 1000);

      $date = new \DateTime(MyLib::millisToDateLocal($millis));
      $interval = $this->date_time_spacer->diff($date);
      
      $return = [
        "from_millis_utc"=>0,
        "from_date_local"=>"",
        "to_millis_utc"=>0,
        "to_date_local"=>"",

        "date_to_data"=>"",
        "millis_to_data"=>0,
        "millis_to_data_check"=>0
      ];

      $to_millis = 0;
      $sign = $interval->format('%r');
      if($sign == '-'){
        $date->add(new \DateInterval('P1D'));
        $to = $date->format('Y-m-d')." 00:00:00";  
        $date->sub(new \DateInterval('P1D'));
        $prev = $date->format('Y-m-d')." 00:00:00";
        $df = $date->format('Y-m-d')." 23:59:59";
      }else{
        $date->add(new \DateInterval('PT1H'));
        $to = $date->format('Y-m-d H').":00:00";
        $date->sub(new \DateInterval('PT1H'));
        $prev = $date->format('Y-m-d H').":00:00";
        $df = $date->format('Y-m-d H').":59:59";
      }

      $return["from_millis_utc"] = MyLib::utcMillis($prev);
      $return["from_date_local"] = $prev;
      $return["to_millis_utc"] = MyLib::utcMillis($to);
      $return["to_date_local"] = $to;
      $return["millis_to_data"] = MyLib::utcMillis($df);
      $return["date_to_data"] = $df;
      $return["millis_to_data_check"] = $return["to_millis_utc"] - 1000;

      $json = json_encode($return);
      $this->info("res: {$json} \n ");
      return $return;

      
      // // $diff_a=$interval->format('%r%a');
      // $diff_y=$interval->format('%r%y');
      // $diff_m=$interval->format('%r%m');
      // $diff_d=$interval->format('%r%d');
      // $diff_h=$interval->format('%r%h');
      // $diff_i=$interval->format('%r%i');
    }


    public function injectFirstData($sensor_id, $air_limbah){
      $local_millis = MyLib::millisUTCToLocal($air_limbah->created_at);

      $local_datetime = date("H:i:s",$local_millis / 1000);
      if($local_datetime !== "23:59:59"){
        
        $new_date = new \DateTime(date("Y-m-d H:i:s",$local_millis / 1000));
        $new_date->sub(new \DateInterval('P1D'));
        $new_created_at_utc = MyLib::utcMillis($new_date->format('Y-m-d')." 23:59:59");
        // $this->info($new_created_at_utc);
        // return;
      
        AirLimbahFlowMeter::insert([
          "created_at"=>$new_created_at_utc,
          "air_limbah_sensor_id"=>$sensor_id,
          "location_id"=>$air_limbah->location_id,
          "real_time_val"=>0,
          "total_val"=>$air_limbah->total_val,
          "u_id"=>null,
          "deviation"=>0,
        ]);
  
      }
      
    }

}
// $this->info("==============".date("d-m-Y H:i:s")."============== \n ");

      // foreach ($flowmeters as $key => $fm) {
      //   $this->info("id {$fm->air_limbah_sensor_id} is run\n ");

      //   \App\Model\AirLimbahFlowMeter::where("air_limbah_sensor_id",$fm->air_limbah_sensor_id)->where("created_at",$fm->created_at)->update([
      //     "total_val" => $fm->total_val / 1000,
      //     "deviation" => $fm->deviation / 1000,
      //   ]);

      // }
      // $this->info("------------------------------------------------------------------------------------------\n ");