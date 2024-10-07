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

Route::get('/trx_trp_nologs', [\App\Http\Controllers\Transaction\TrxTrpNologController::class, 'index']);
Route::get('/trx_trp_nolog', [\App\Http\Controllers\Transaction\TrxTrpNologController::class, 'show']);


Route::get('/trx_trps', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'index']);
Route::get('/trx_trp', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'show']);
Route::get('/trx_trp/mandor_verify_trx', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'mandorGetVerifyTrx']);
Route::post('/trx_trp', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'store']);
Route::put('/trx_trp', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'update']);
Route::put('/trx_trp/mandor_verify_trx', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'mandorGetVerifySet']);

Route::delete('/trx_trp', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'delete']);
Route::delete('/trx_trp_req_delete', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'reqDelete']);
Route::delete('/trx_trp_approve_req_delete', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'approveReqDelete']);
// Route::get('/trx_trps_preview_file', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'previewFiles']);

Route::get('/trx_trp_susuts/data', [\App\Http\Controllers\Transaction\TrxTrpSusutController::class, 'index']);
Route::get('/trx_trp_susuts/report_PDF', [\App\Http\Controllers\Transaction\TrxTrpSusutController::class, 'reportSusutPDF']);
Route::get('/trx_trp_susuts/report_Excel', [\App\Http\Controllers\Transaction\TrxTrpSusutController::class, 'reportSusutExcel']);

Route::get('/trx_trps/dataFin', [\App\Http\Controllers\Transaction\TrxTrpFinanceController::class, 'index']);
Route::get('/trx_trps/reportFinPDF', [\App\Http\Controllers\Transaction\TrxTrpFinanceController::class, 'reportFinPDF']);
Route::get('/trx_trps/reportFinExcel', [\App\Http\Controllers\Transaction\TrxTrpFinanceController::class, 'reportFinExcel']);

Route::get('/trx_trp/transfers', [\App\Http\Controllers\Transaction\TrxTrpTransferController::class, 'index']);
Route::put('/trx_trp/transfer', [\App\Http\Controllers\Transaction\TrxTrpTransferController::class, 'validasiAndTransfer']);
Route::get('/trx_trp/transfer/detail', [\App\Http\Controllers\Transaction\TrxTrpTransferController::class, 'show']);
Route::get('/trx_trp_preview_file_bt', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'previewFileBT']);

Route::get('/trx_trp/transfers_mandiri', [\App\Http\Controllers\Transaction\TrxTrpTransferController::class, 'indexMandiri']);
Route::put('/trx_trp/transfer_mandiri', [\App\Http\Controllers\Transaction\TrxTrpTransferController::class, 'validasiAndTransferMandiri']);
Route::put('/trx_trp/gen_csv_mandiri', [\App\Http\Controllers\Transaction\TrxTrpTransferController::class, 'generateCSVMandiri']);


Route::get('/trx_trp_tickets', [\App\Http\Controllers\Transaction\TrxTrpTicketController::class, 'index']);
Route::put('/trx_trp_ticket', [\App\Http\Controllers\Transaction\TrxTrpTicketController::class, 'updateTicket']);
Route::get('/trx_trps/ticket_over', [\App\Http\Controllers\Transaction\TrxTrpTicketController::class, 'ticketOver']);
Route::post('/trx_trp_do_update_ticket', [\App\Http\Controllers\Transaction\TrxTrpTicketController::class, 'doUpdateTicket']);


// Route::get('/trx_trps_download_excel', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'downloadExcel']);
Route::get('/trx_trp_preview_file', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'previewFile']);
Route::put('/trx_trp_validasi', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'validasi']);
Route::post('/trx_trp_do_gen_pvr', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'doGenPVR']);
Route::post('/trx_trp_do_update_pv', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'doUpdatePV']);
Route::put('/trx_trp_val_tickets', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'valTickets']);

Route::get('/trx_load_cost_center', [\App\Http\Controllers\Transaction\TrxLoadDataController::class, 'cost_center']);
Route::get('/trx_load_for_trp', [\App\Http\Controllers\Transaction\TrxLoadDataController::class, 'trp']);
Route::get('/trx_load_for_local', [\App\Http\Controllers\Transaction\TrxLoadDataController::class, 'local']);
Route::delete('/trx_trp_absen', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'delete_absen']);

Route::get('/trx_trp/absens', [\App\Http\Controllers\Transaction\TrxTrpAbsenController::class, 'index']);
Route::get('/trx_trp/absen', [\App\Http\Controllers\Transaction\TrxTrpAbsenController::class, 'show']);
Route::put('/trx_trp/absen', [\App\Http\Controllers\Transaction\TrxTrpAbsenController::class, 'update']);
Route::put('/trx_trp/absen/validasi', [\App\Http\Controllers\Transaction\TrxTrpAbsenController::class, 'validasi']);

Route::get('/users', [\App\Http\Controllers\User\UserController::class, 'index']);
Route::get('/user', [\App\Http\Controllers\User\UserController::class, 'show']);
Route::post('/user', [\App\Http\Controllers\User\UserController::class, 'store']);
Route::put('/user', [\App\Http\Controllers\User\UserController::class, 'update']);
Route::delete('/user', [\App\Http\Controllers\User\UserController::class, 'delete']);

Route::get('/vehicles_available', [\App\Http\Controllers\Vehicle\VehicleController::class, 'available']);
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


Route::get('/permission_lists', [\App\Http\Controllers\Permission\PermissionListController::class, 'index']);

Route::get('/permission_groups', [\App\Http\Controllers\Permission\PermissionGroupController::class, 'index']);
Route::get('/permission_group', [\App\Http\Controllers\Permission\PermissionGroupController::class, 'show']);
Route::post('/permission_group', [\App\Http\Controllers\Permission\PermissionGroupController::class, 'store']);
Route::put('/permission_group', [\App\Http\Controllers\Permission\PermissionGroupController::class, 'update']);
Route::delete('/permission_group', [\App\Http\Controllers\Permission\PermissionGroupController::class, 'delete']);

Route::get('/potongan_msts', [\App\Http\Controllers\Potongan\PotonganMstController::class, 'index']);
Route::get('/potongan_mst', [\App\Http\Controllers\Potongan\PotonganMstController::class, 'show']);
Route::post('/potongan_mst', [\App\Http\Controllers\Potongan\PotonganMstController::class, 'store']);
Route::put('/potongan_mst', [\App\Http\Controllers\Potongan\PotonganMstController::class, 'update']);
Route::delete('/potongan_mst', [\App\Http\Controllers\Potongan\PotonganMstController::class, 'delete']);
Route::get('/potongan_mst_load_local', [\App\Http\Controllers\Potongan\PotonganMstController::class, 'loadLocal']);
Route::put('/potongan_mst_validasi', [\App\Http\Controllers\Potongan\PotonganMstController::class, 'validasi']);

Route::get('/potongan_trxs', [\App\Http\Controllers\Potongan\PotonganTrxController::class, 'index']);
Route::get('/potongan_trx', [\App\Http\Controllers\Potongan\PotonganTrxController::class, 'show']);
Route::post('/potongan_trx', [\App\Http\Controllers\Potongan\PotonganTrxController::class, 'store']);
Route::put('/potongan_trx', [\App\Http\Controllers\Potongan\PotonganTrxController::class, 'update']);
Route::delete('/potongan_trx', [\App\Http\Controllers\Potongan\PotonganTrxController::class, 'delete']);
Route::post('/potongan_trx_recalculate', [\App\Http\Controllers\Potongan\PotonganTrxController::class, 'recalculate']);
Route::put('/potongan_trx_validasi', [\App\Http\Controllers\Potongan\PotonganTrxController::class, 'validasi']);

Route::get('/salary_paids', [\App\Http\Controllers\Salary\SalaryPaidController::class, 'index']);
Route::get('/salary_paid', [\App\Http\Controllers\Salary\SalaryPaidController::class, 'show']);
Route::post('/salary_paid', [\App\Http\Controllers\Salary\SalaryPaidController::class, 'store']);
Route::put('/salary_paid', [\App\Http\Controllers\Salary\SalaryPaidController::class, 'update']);
// Route::delete('/salary_paid', [\App\Http\Controllers\Standby\StandbyTrxController::class, 'delete']);
Route::put('/salary_paid_validasi', [\App\Http\Controllers\Salary\SalaryPaidController::class, 'validasi']);
Route::get('/salary_paid/pdf_preview', [\App\Http\Controllers\Salary\SalaryPaidController::class, 'pdfPreview']);
Route::get('/salary_paid/excel_download', [\App\Http\Controllers\Salary\SalaryPaidController::class, 'excelDownload']);


Route::get('/salary_bonuses', [\App\Http\Controllers\Salary\SalaryBonusController::class, 'index']);
Route::get('/salary_bonus', [\App\Http\Controllers\Salary\SalaryBonusController::class, 'show']);
Route::post('/salary_bonus', [\App\Http\Controllers\Salary\SalaryBonusController::class, 'store']);
Route::put('/salary_bonus', [\App\Http\Controllers\Salary\SalaryBonusController::class, 'update']);
Route::get('/salary_bonus_load_local', [\App\Http\Controllers\Salary\SalaryBonusController::class, 'loadLocal']);
Route::delete('/salary_bonus', [\App\Http\Controllers\Salary\SalaryBonusController::class, 'delete']);
Route::put('/salary_bonus_validasi', [\App\Http\Controllers\Salary\SalaryBonusController::class, 'validasi']);
// Route::get('/salary_bonus_generate_detail', [\App\Http\Controllers\Transaction\TrxTrpController::class, 'previewFiles']);
// Route::get('/salary_bonus_preview_file', [\App\Http\Controllers\Standby\StandbyTrxController::class, 'previewFile']);


Route::get('/extra_moneys', [\App\Http\Controllers\ExtraMoney\ExtraMoneyController::class, 'index']);
Route::get('/extra_money', [\App\Http\Controllers\ExtraMoney\ExtraMoneyController::class, 'show']);
Route::post('/extra_money', [\App\Http\Controllers\ExtraMoney\ExtraMoneyController::class, 'store']);
Route::put('/extra_money', [\App\Http\Controllers\ExtraMoney\ExtraMoneyController::class, 'update']);
Route::delete('/extra_money', [\App\Http\Controllers\ExtraMoney\ExtraMoneyController::class, 'delete']);
Route::put('/extra_money_validasi', [\App\Http\Controllers\ExtraMoney\ExtraMoneyController::class, 'validasi']);
Route::get('/extra_money_load_local', [\App\Http\Controllers\ExtraMoney\ExtraMoneyController::class, 'loadLocal']);
Route::get('/extra_money_load_sqlsrv', [\App\Http\Controllers\ExtraMoney\ExtraMoneyController::class, 'loadSqlSrv']);

Route::get('/extra_money_trxs', [\App\Http\Controllers\ExtraMoney\ExtraMoneyTrxController::class, 'index']);
Route::get('/extra_money_trx', [\App\Http\Controllers\ExtraMoney\ExtraMoneyTrxController::class, 'show']);
Route::post('/extra_money_trx', [\App\Http\Controllers\ExtraMoney\ExtraMoneyTrxController::class, 'store']);
Route::put('/extra_money_trx', [\App\Http\Controllers\ExtraMoney\ExtraMoneyTrxController::class, 'update']);
Route::delete('/extra_money_trx', [\App\Http\Controllers\ExtraMoney\ExtraMoneyTrxController::class, 'delete']);
Route::get('/extra_money_trx_load_local', [\App\Http\Controllers\ExtraMoney\ExtraMoneyTrxController::class, 'loadLocal']);
Route::get('/extra_money_trx_load_sqlsrv', [\App\Http\Controllers\ExtraMoney\ExtraMoneyTrxController::class, 'loadSqlSrv']);
Route::put('/extra_money_trx_validasi', [\App\Http\Controllers\ExtraMoney\ExtraMoneyTrxController::class, 'validasi']);
Route::get('/extra_money_trx_preview_file', [\App\Http\Controllers\ExtraMoney\ExtraMoneyTrxController::class, 'previewFile']);

Route::get('/extra_money_trx/transfers', [\App\Http\Controllers\ExtraMoney\ExtraMoneyTrxTransferController::class, 'index']);
Route::put('/extra_money_trx/transfer', [\App\Http\Controllers\ExtraMoney\ExtraMoneyTrxTransferController::class, 'validasiAndTransfer']);
Route::get('/extra_money_trx/transfer/detail', [\App\Http\Controllers\ExtraMoney\ExtraMoneyTrxTransferController::class, 'show']);
Route::get('/extra_money_trx_preview_file_bt', [\App\Http\Controllers\ExtraMoney\ExtraMoneyController::class, 'previewFileBT']);

// Route::get('/payment_methods', [\App\Http\Controllers\PaymentMethodController::class, 'index']);
Route::get('/banks', [\App\Http\Controllers\BankController::class, 'index']);

Route::get('/ga_qr', [\App\Http\Controllers\GAController::class, 'qr']);
Route::post('/ga_pin', [\App\Http\Controllers\GAController::class, 'pin']);


Route::get('/temp_data/vehiclesAllowedUpdateTicket', [\App\Http\Controllers\TempDataController::class, 'vehiclesAllowedUpdateTicket']);


// use Illuminate\Support\Facades\Storage;
// use App\Helpers\MyLog;
// Route::get('/testsend',function () {

// $file = 'public/20240924112404-transfer_mandiri.csv';
// $remotePath = 'public_html/testsendfile.csv';
// try {
//     // Storage::disk('ftp')->put($remotePath, file_get_contents(Storage::get($file)));
//     Storage::disk('ftp')->put($remotePath,Storage::get($file));
// } catch (\Exception $e) {
//     // Handle the exception
//     MyLog::logging($e->getMessage());
// }
// });