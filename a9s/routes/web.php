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


    Route::get('/trx_trps', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'index']);
    Route::get('/trx_trp', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'show']);
    Route::get('/trx_trp/mandor_verify_trx', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'mandorGetVerifyTrx']);
    Route::post('/trx_trp', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'store']);
    Route::put('/trx_trp', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'update']);
    Route::put('/trx_trp/mandor_verify_trx', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'mandorGetVerifySet']);
    Route::put('/trx_trp_ticket', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'updateTicket']);
    Route::delete('/trx_trp', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'delete']);
    Route::delete('/trx_trp_req_delete', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'reqDelete']);
    Route::delete('/trx_trp_approve_req_delete', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'approveReqDelete']);
    Route::get('/trx_trps_preview_file', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'previewFiles']);
    Route::get('/trx_trps_download_excel', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'downloadExcel']);
    Route::get('/trx_trp_preview_file', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'previewFile']);
    Route::put('/trx_trp_validasi', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'validasi']);
    Route::post('/trx_trp_do_gen_pvr', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'doGenPVR']);
    Route::post('/trx_trp_do_update_pv', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'doUpdatePV']);
    
    Route::get('/trx_load_cost_center', [\App\Http\Controllers\Transaction\TrxLoadDataController::class, 'cost_center']);
    Route::get('/trx_load_for_trp', [\App\Http\Controllers\Transaction\TrxLoadDataController::class, 'trp']);
    Route::get('/trx_load_for_local', [\App\Http\Controllers\Transaction\TrxLoadDataController::class, 'local']);
    Route::delete('/trx_trp_absen', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'delete_absen']);

    
    Route::get('/users', [\App\Http\Controllers\User\UserController::class, 'index']);
    Route::get('/user', [\App\Http\Controllers\User\UserController::class, 'show']);
    Route::post('/user', [\App\Http\Controllers\User\UserController::class, 'store']);
    Route::put('/user', [\App\Http\Controllers\User\UserController::class, 'update']);
    Route::delete('/user', [\App\Http\Controllers\User\UserController::class, 'delete']);

    Route::get('/vehicles', [\App\Http\Controllers\Vehicle\VehicleController::class, 'index']);
    Route::get('/vehicle', [\App\Http\Controllers\Vehicle\VehicleController::class, 'show']);
    Route::post('/vehicle', [\App\Http\Controllers\Vehicle\VehicleController::class, 'store']);
    Route::put('/vehicle', [\App\Http\Controllers\Vehicle\VehicleController::class, 'update']);
    Route::delete('/vehicle', [\App\Http\Controllers\Vehicle\VehicleController::class, 'delete']);

    Route::get('/employees', [\App\Http\Controllers\Employee\EmployeeController::class, 'index']);
    Route::get('/employee', [\App\Http\Controllers\Employee\EmployeeController::class, 'show']);
    Route::post('/employee', [\App\Http\Controllers\Employee\EmployeeController::class, 'store']);
    Route::put('/employee', [\App\Http\Controllers\Employee\EmployeeController::class, 'update']);
    Route::delete('/employee', [\App\Http\Controllers\Employee\EmployeeController::class, 'delete']);
    Route::put('/employee_validasi', [\App\Http\Controllers\Employee\EmployeeController::class, 'validasi']);


    Route::get('/standby_msts', [\App\Http\Controllers\Standby\StandbyMstController::class, 'index']);
    Route::get('/standby_mst', [\App\Http\Controllers\Standby\StandbyMstController::class, 'show']);
    Route::post('/standby_mst', [\App\Http\Controllers\Standby\StandbyMstController::class, 'store']);
    Route::put('/standby_mst', [\App\Http\Controllers\Standby\StandbyMstController::class, 'update']);
    Route::delete('/standby_mst', [\App\Http\Controllers\Standby\StandbyMstController::class, 'delete']);
    Route::put('/standby_mst_validasi', [\App\Http\Controllers\Standby\StandbyMstController::class, 'validasi']);

    Route::get('/ac_accounts', [\App\Http\Controllers\AcAccountController::class, 'index']);

    

    Route::get('/standby_trxs', [\App\Http\Controllers\Standby\StandbyTrxController::class, 'index']);
    Route::get('/standby_trx_load_local', [\App\Http\Controllers\Standby\StandbyTrxController::class, 'loadLocal']);
    Route::get('/standby_trx_load_sqlsrv', [\App\Http\Controllers\Standby\StandbyTrxController::class, 'loadSqlSrv']);


    Route::get('/standby_trx', [\App\Http\Controllers\Standby\StandbyTrxController::class, 'show']);
    // Route::get('/standby_trx/mandor_verify_trx', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'mandorGetVerifyTrx']);
    Route::post('/standby_trx', [\App\Http\Controllers\Standby\StandbyTrxController::class, 'store']);
    Route::put('/standby_trx', [\App\Http\Controllers\Standby\StandbyTrxController::class, 'update']);
    // Route::put('/standby_trx/mandor_verify_trx', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'mandorGetVerifySet']);
    // Route::put('/standby_trx_ticket', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'updateTicket']);
    Route::delete('/standby_trx', [\App\Http\Controllers\Standby\StandbyTrxController::class, 'delete']);
    Route::delete('/standby_trx_req_delete', [\App\Http\Controllers\Standby\StandbyTrxController::class, 'reqDelete']);
    Route::delete('/standby_trx_approve_req_delete', [\App\Http\Controllers\Standby\StandbyTrxController::class, 'approveReqDelete']);
    // Route::get('/standby_trxs_preview_file', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'previewFiles']);
    // Route::get('/standby_trxs_download_excel', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'downloadExcel']);
    Route::get('/standby_trx_preview_file', [\App\Http\Controllers\Standby\StandbyTrxController::class, 'previewFile']);
    Route::put('/standby_trx_validasi', [\App\Http\Controllers\Standby\StandbyTrxController::class, 'validasi']);
    Route::post('/standby_trx_do_gen_pvr', [\App\Http\Controllers\Standby\StandbyTrxController::class, 'doGenPVR']);
    Route::post('/standby_trx_do_update_pv', [\App\Http\Controllers\Standby\StandbyTrxController::class, 'doUpdatePV']);

    Route::get('/report/ramp/get_locations', [\App\Http\Controllers\Report\RampController::class, 'getLocations']);
    Route::get('/report/ramp/pdf_preview', [\App\Http\Controllers\Report\RampController::class, 'pdfPreview']);
    Route::get('/report/ramp/excel_download', [\App\Http\Controllers\Report\RampController::class, 'excelDownload']);

    Route::get('/report/ast_n_driver/load_data', [\App\Http\Controllers\Report\AstNDriverController::class, 'loadData']);
    Route::get('/report/ast_n_driver/index', [\App\Http\Controllers\Report\AstNDriverController::class, 'index']);
    Route::get('/report/ast_n_driver/pdf_preview', [\App\Http\Controllers\Report\AstNDriverController::class, 'pdfPreview']);
    Route::get('/report/ast_n_driver/excel_download', [\App\Http\Controllers\Report\AstNDriverController::class, 'excelDownload']);

    
    Route::get('/fin_payment_reqs', [\App\Http\Controllers\Finance\FinPaymentReqController::class, 'index']);
    Route::get('/fin_payment_req', [\App\Http\Controllers\Finance\FinPaymentReqController::class, 'show']);
    Route::post('/fin_payment_req', [\App\Http\Controllers\Finance\FinPaymentReqController::class, 'store']);
    Route::put('/fin_payment_req', [\App\Http\Controllers\Finance\FinPaymentReqController::class, 'update']);
    Route::delete('/fin_payment_req', [\App\Http\Controllers\Finance\FinPaymentReqController::class, 'delete']);
    Route::put('/fin_payment_req_validasi', [\App\Http\Controllers\Finance\FinPaymentReqController::class, 'validasi']);

    Route::get('/fin_payment_req/get_trx_trp_unprocessed', [\App\Http\Controllers\Finance\FinPaymentReqController::class, 'get_trx_trp_unprocessed']);
    Route::get('/fin_payment_req/download_view', [\App\Http\Controllers\Finance\FinPaymentReqController::class, 'excelDownload']);
    
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