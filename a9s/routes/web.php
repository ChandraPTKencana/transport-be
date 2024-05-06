<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});





// Route::get("testdb",function(){

//     $data = DB::select("select * from cc");
//     $data1 = DB::connection('sqlsrv')->select("select top 1 * from palm_tickets");
//     dd($data1);
//     // return response()->json([
//     //     "1"=>$data,
//     //     "2"=>$data1,
//     // ],200);
// });

    Route::post('/login', [\App\Http\Controllers\User\UserAccount::class, 'login']);
    Route::post('/logout', [\App\Http\Controllers\User\UserAccount::class, 'logout']);
    Route::put('/change_password', [\App\Http\Controllers\User\UserAccount::class, 'change_password']);
  
    Route::get('/check_user', [\App\Http\Controllers\User\UserAccount::class, 'checkUser']);
    Route::get('/profile', [\App\Http\Controllers\User\UserAccount::class, 'dataUser']);
    Route::put('/update_profile', [\App\Http\Controllers\User\UserAccount::class, 'updateUser']);



    Route::get('/ujalan', [\App\Http\Controllers\Ujalan\UjalanController::class, 'index']);
    Route::get('/ujalan_', [\App\Http\Controllers\Ujalan\UjalanController::class, 'show']);
    Route::post('/ujalan', [\App\Http\Controllers\Ujalan\UjalanController::class, 'store']);
    Route::put('/ujalan', [\App\Http\Controllers\Ujalan\UjalanController::class, 'update']);
    Route::delete('/ujalan', [\App\Http\Controllers\Ujalan\UjalanController::class, 'delete']);
    Route::put('/ujalan_validasi', [\App\Http\Controllers\Ujalan\UjalanController::class, 'validasi']);
    Route::get('/ujalan/ac_accounts', [\App\Http\Controllers\Ujalan\UjalanController::class, 'ac_accounts']);


    Route::get('/trx_trps', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'index']);
    Route::get('/trx_trp', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'show']);
    Route::post('/trx_trp', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'store']);
    Route::put('/trx_trp', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'update']);
    Route::delete('/trx_trp', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'delete']);
    Route::get('/trx_trps_preview_file', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'previewFiles']);
    Route::get('/trx_trps_download_excel', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'downloadExcel']);
    Route::get('/trx_trp_preview_file', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'previewFile']);
    Route::put('/trx_trp_validasi', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'validasi']);
    Route::post('/trx_trp_do_gen_pvr', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'doGenPVR']);
    Route::post('/trx_trp_do_update_pv', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'doUpdatePV']);
    
    Route::get('/trx_load_for_trp', [\App\Http\Controllers\Transaction\TrxLoadDataController::class, 'trp']);
    Route::delete('/trx_trp_absen', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'delete_absen']);

    
    Route::get('/users', [\App\Http\Controllers\User\UserController::class, 'index']);
    Route::get('/user', [\App\Http\Controllers\User\UserController::class, 'show']);
    Route::post('/user', [\App\Http\Controllers\User\UserController::class, 'store']);
    Route::put('/user', [\App\Http\Controllers\User\UserController::class, 'update']);
    Route::delete('/user', [\App\Http\Controllers\User\UserController::class, 'delete']);

    
    // Route::prefix("stok/api")->group(function(){

//     // Route::post('login', function () {
//     //     return response()->json(["error"],400);
//     // });


//     Route::get('/units', [\App\Http\Controllers\Stok\UnitController::class, 'index']);
//     Route::get('/unit', [\App\Http\Controllers\Stok\UnitController::class, 'show']);
//     Route::post('/unit', [\App\Http\Controllers\Stok\UnitController::class, 'store']);
//     Route::put('/unit', [\App\Http\Controllers\Stok\UnitController::class, 'update']);
//     Route::delete('/unit', [\App\Http\Controllers\Stok\UnitController::class, 'delete']);

//     Route::get('/warehouses', [\App\Http\Controllers\Stok\WarehouseController::class, 'index']);
//     Route::get('/warehouse', [\App\Http\Controllers\Stok\WarehouseController::class, 'show']);
//     Route::post('/warehouse', [\App\Http\Controllers\Stok\WarehouseController::class, 'store']);
//     Route::put('/warehouse', [\App\Http\Controllers\Stok\WarehouseController::class, 'update']);
//     Route::delete('/warehouse', [\App\Http\Controllers\Stok\WarehouseController::class, 'delete']);

//     Route::get('/items', [\App\Http\Controllers\Stok\ItemController::class, 'index']);
//     Route::get('/item', [\App\Http\Controllers\Stok\ItemController::class, 'show']);
//     Route::post('/item', [\App\Http\Controllers\Stok\ItemController::class, 'store']);
//     Route::put('/item', [\App\Http\Controllers\Stok\ItemController::class, 'update']);
//     Route::delete('/item', [\App\Http\Controllers\Stok\ItemController::class, 'delete']);

//     Route::get('/transactions', [\App\Http\Controllers\Stok\TransactionController::class, 'index']);
//     Route::get('/transaction', [\App\Http\Controllers\Stok\TransactionController::class, 'show']);
//     Route::post('/transaction', [\App\Http\Controllers\Stok\TransactionController::class, 'store']);
//     Route::put('/transaction', [\App\Http\Controllers\Stok\TransactionController::class, 'update']);
//     Route::delete('/transaction', [\App\Http\Controllers\Stok\TransactionController::class, 'delete']);
//     Route::put('/confirm_transaction', [\App\Http\Controllers\Stok\TransactionController::class, 'confirm_transaction']);
    
//     Route::get('/request_transactions', [\App\Http\Controllers\Stok\TransactionController::class, 'request_transactions']);
//     Route::post('/request_transaction_confirm', [\App\Http\Controllers\Stok\TransactionController::class, 'request_transaction_confirm']);

//     Route::get('/summary_transactions', [\App\Http\Controllers\Stok\TransactionController::class, 'summary_transactions']);
//     Route::get('/summary_detail_transactions', [\App\Http\Controllers\Stok\TransactionController::class, 'summary_detail_transactions']);

//     Route::get('/hrm_revisi_lokasis', [\App\Http\Controllers\HrmRevisiLokasiController::class, 'index']);
    

  
//     // Route::get('/action_permissions', [\App\Http\Controllers\Internal\User\UserPermissionController::class, 'getActionPermissions']);
//     // Route::get('/data_permissions', [\App\Http\Controllers\Internal\User\UserPermissionController::class, 'getDataPermissions']);
//     // Route::get('/user/permissions', [\App\Http\Controllers\Internal\User\UserPermissionController::class, 'show']);
//     // Route::put('/user/permissions', [\App\Http\Controllers\Internal\User\UserPermissionController::class, 'update']);
  
//     // Route::get('/institutes', [\App\Http\Controllers\Internal\InstituteController::class, 'index']);
//     // Route::get('/institute', [\App\Http\Controllers\Internal\InstituteController::class, 'show']);
//     // Route::post('/institute', [\App\Http\Controllers\Internal\InstituteController::class, 'store']);
//     // Route::put('/institute', [\App\Http\Controllers\Internal\InstituteController::class, 'update']);
//     // Route::delete('/institute', [\App\Http\Controllers\Internal\InstituteController::class, 'delete']);
  
//     // Route::get('/members', [\App\Http\Controllers\Internal\MemberController::class, 'index']);
//     // Route::get('/member', [\App\Http\Controllers\Internal\MemberController::class, 'show']);
//     // Route::post('/member', [\App\Http\Controllers\Internal\MemberController::class, 'store']);
//     // Route::put('/member', [\App\Http\Controllers\Internal\MemberController::class, 'update']);
//     // Route::delete('/member', [\App\Http\Controllers\Internal\MemberController::class, 'delete']);
  
// });