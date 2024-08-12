<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Exceptions\MyException;

use App\Helpers\MyAdmin;

use App\Http\Resources\MySql\BankResource;
use App\Models\MySql\Bank;

class BankController extends Controller
{
  private $admin;
  private $role;
  private $admin_id;
  private $permissions;

  public function __construct(Request $request)
  {
    $this->admin = MyAdmin::user();
    $this->admin_id = $this->admin->the_user->id;
    $this->role = $this->admin->the_user->hak_akses;
    $this->permissions = $this->admin->the_user->listPermissions();

  }

  public function index(Request $request)
  {
    // MyAdmin::checkScope($this->permissions, 'bank.views');

    //======================================================================================================
    // Pembatasan Data hanya memerlukan limit dan offset
    //======================================================================================================

    $limit = 250; // Limit +> Much Data
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
    $model_query = Bank::offset($offset)->limit($limit);

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

      if (isset($sort_lists["name"])) {
        $model_query = $model_query->orderBy("name", $sort_lists["name"]);
        if (count($first_row) > 0) {
          $model_query = $model_query->where("name",$sort_symbol,$first_row["name"]);
        }
      }

      if (isset($sort_lists["role"])) {
        $model_query = $model_query->orderBy("role", $sort_lists["role"]);
        if (count($first_row) > 0) {
          $model_query = $model_query->where("role",$sort_symbol,$first_row["role"]);
        }
      }

      

      // if (isset($sort_lists["role"])) {
      //   $model_query = $model_query->orderBy(function($q){
      //     $q->from("internal.roles")
      //     ->select("name")
      //     ->whereColumn("id","auths.role_id");
      //   },$sort_lists["role"]);
      // }

      // if (isset($sort_lists["auth"])) {
      //   $model_query = $model_query->orderBy(function($q){
      //     $q->from("users as u")
      //     ->select("u.username")
      //     ->whereColumn("u.id","users.id");
      //   },$sort_lists["auth"]);
      // }
    } else {
      $model_query = $model_query->orderBy('id', 'ASC');
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

      if (isset($like_lists["name"])) {
        $model_query = $model_query->orWhere("name", "like", $like_lists["name"]);
      }

      if (isset($like_lists["role"])) {
        $model_query = $model_query->orWhere("role", "like", $like_lists["role"]);
      }

      // if (isset($like_lists["role"])) {
      //   $model_query = $model_query->orWhere("role","like",$like_lists["role"]);
      // }
    }

    // ==============
    // Model Filter
    // ==============

    if (isset($request->name)) {
      $model_query = $model_query->where("name", 'like', '%' . $request->name . '%');
    }

    if (isset($request->role)) {
      $model_query = $model_query->where("role", 'like', '%' . $request->role . '%');
    }


    // $model_query = $model_query->where("deleted",0)->get();
    $model_query = $model_query->get();

    return response()->json([
      // "data"=>BankResource::collection($employees->keyBy->id),
      "data" => BankResource::collection($model_query),
    ], 200);
  }
}
