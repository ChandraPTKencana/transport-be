<?php

namespace App\Console\Commands;

use App\Helpers\MyLog;
use App\Models\MySql\Employee;
use Illuminate\Console\Command;

use Illuminate\Support\Facades\Validator;
use App\Models\Stok\Transaction;
use DB;
class RunCom extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'run_1';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get Supir And Kernet Name From Server';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info("------------------------------------------------------------------------------------------\n ");
        $this->info("Start\n ");
        
        if(!Employee::where('name','BLANK')->first()){
            $this->info("=====Begin Employee=====\n ");
            $sds = Employee::orderBy("id","desc")->get();         
            foreach ($sds as $k => $v) {
                $newId= $v->id+1;
                if($k==0){
                    DB::statement("ALTER TABLE employee_mst AUTO_INCREMENT = $newId");
                }
                $v->id = $newId;
                $v->save();
            }
    
            Employee::insert([
                "id"=>1,
                "name"=>"BLANK",
                "role"=>"BLANK",
                "val"=>1,
                "val_user"=>1,
                "val_at"=>date("Y-m-d H:i:s"),
                "created_at"=>date("Y-m-d H:i:s"),
                "updated_at"=>date("Y-m-d H:i:s"),
            ]);    
            $this->info("=====End Employee=====\n ");
        } else{
            $this->info("=====Abort Employee=====\n ");
        }

       
        $this->info("Finish\n ");
        $this->info("------------------------------------------------------------------------------------------\n ");
    }
}
