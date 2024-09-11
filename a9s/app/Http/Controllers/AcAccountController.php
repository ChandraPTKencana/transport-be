<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Exceptions\MyException;

use App\Helpers\MyAdmin;

use App\Http\Resources\AcAccountResource;

class AcAccountController extends Controller
{
  private $admin;
  private $admin_id;
  private $permissions;

  public function __construct(Request $request)
  {
    $this->admin = MyAdmin::user();
    $this->admin_id = $this->admin->the_user->id;
    $this->permissions = $this->admin->the_user->listPermissions();

  }

  public function index(Request $request)
  {
    MyAdmin::checkScope($this->permissions, 'srv.cost_center.views');
 
    //======================================================================================================
    // Pembatasan Data hanya memerlukan limit dan offset
    //======================================================================================================

    $limit = 100; // Limit +> Much Data
    if (isset($request->limit)) {
      if ($request->limit <= 250) {
        $limit = $request->limit;
      } else {
        throw new MyException(["message" => "Max Limit 250"]);
      }
    }

    $offset = isset($request->offset) ? (int) $request->offset : 0; // example offset 400 start from 401

    //======================================================================================================
    // Jika Halaman Ditentutkan maka $offset akan disesuaikan
    //======================================================================================================
    if (isset($request->page)) {
      $page =  (int) $request->page;
      $offset = ($page * $limit) - $limit;
    }


    //======================================================================================================
    // Init Model
    //======================================================================================================
    
    $model_query = DB::connection('sqlsrv')->table('AC_Accounts')
    ->select('AccountID','AccountCode','AccountName');

    $model_query = $model_query->offset($offset)->limit($limit);

    $first_row=[];
    if($request->first_row){
      $first_row 	= json_decode($request->first_row, true);
    }

    //======================================================================================================
    // Model Sorting | Example $request->sort = "username:desc,role:desc";
    //======================================================================================================
    

    if ($request->sort) {
      $sort_lists = [];

      $sorts = explode(",", $request->sort);
      foreach ($sorts as $key => $sort) {
        $side = explode(":", $sort);
        $side[1] = isset($side[1]) ? $side[1] : 'ASC';
        $sort_symbol = $side[1] == "desc" ? "<=" : ">=";
        $sort_lists[$side[0]] = $side[1];
      }

      if (isset($sort_lists["AccountID"])) {
        $model_query = $model_query->orderBy("AccountID", $sort_lists["AccountID"]);
        if (count($first_row) > 0) {
          $model_query = $model_query->where("AccountID",$sort_symbol,$first_row["ac_account_id"]);
        }
      }

      if (isset($sort_lists["AccountCode"])) {
        $model_query = $model_query->orderBy("AccountCode", $sort_lists["AccountCode"]);
        if (count($first_row) > 0) {
          $model_query = $model_query->where("AccountCode",$sort_symbol,$first_row["ac_account_code"]);
        }
      }

      if (isset($sort_lists["AccountName"])) {
        $model_query = $model_query->orderBy("AccountName", $sort_lists["AccountName"]);
        if (count($first_row) > 0) {
          $model_query = $model_query->where("AccountName",$sort_symbol,$first_row["ac_account_name"]);
        }
      }

    } else {
      $model_query = $model_query->orderBy('AccountID', 'DESC');
    }
    //======================================================================================================
    // Model Filter | Example $request->like = "username:%username,role:%role%,name:role%,";
    //======================================================================================================

    if ($request->like) {
      $like_lists = [];

      $likes = explode(",", $request->like);
      foreach ($likes as $key => $like) {
        $side = explode(":", $like);
        $side[1] = isset($side[1]) ? $side[1] : '';
        $like_lists[$side[0]] = $side[1];
      }

      if(count($like_lists) > 0){
        $model_query = $model_query->where(function ($q)use($like_lists){
            
          if (isset($like_lists["AccountID"])) {
            $q->orWhere("AccountID", "like", $like_lists["AccountID"]);
          }
    
          if (isset($like_lists["AccountCode"])) {
            $q->orWhere("AccountCode", "like", $like_lists["AccountCode"]);
          }
    
          if (isset($like_lists["AccountName"])) {
            $q->orWhere("AccountName", "like", $like_lists["AccountName"]);
          }

        });        
      }

      
    }

    // ==============
    // Model Filter
    // ==============

    $model_query = $model_query->get();

    $model_query= $model_query->map(function ($item) {
      return array_map('utf8_encode', (array)$item);
    })->toArray();

    return response()->json([
      "data" => AcAccountResource::collection($model_query),
    ], 200);
  }
}
