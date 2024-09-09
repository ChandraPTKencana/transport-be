<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Exceptions\MyException;
use Illuminate\Validation\ValidationException;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

use App\Helpers\MyAdmin;
use App\Helpers\MyLog;
use App\Helpers\MyLib;

use App\Models\MySql\TempData;
use App\Models\MySql\IsUser;

use App\Http\Requests\MySql\TempDataRequest;

use App\Http\Resources\MySql\TempDataResource;
use App\Http\Resources\MySql\IsUserResource;
use App\Models\MySql\Vehicle;

class TempDataController extends Controller
{
  private $admin;
  private $admin_id;
  private $syslog_db = 'temp_data_mst';

  public function __construct(Request $request)
  {
    $this->admin = MyAdmin::user();
    $this->admin_id = $this->admin->the_user->id;

  }

  public function vehiclesAllowedUpdateTicket(Request $request){
    $dkey = "vehiclesAllowedUpdateTicket";
    
    $data = [];
    $model_query =TempData::where('dkey',$dkey)->first();
    if($model_query) $data = json_decode($model_query->dval,true); 

    return response()->json([
      "data" => $data,
    ], 200);
  }


}
