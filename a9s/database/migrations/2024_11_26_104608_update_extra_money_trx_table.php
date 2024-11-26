<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('extra_money_trx', function (Blueprint $table) {
            $table->bigInteger('duitku_employee_disburseId')->nullable();
            $table->string('duitku_employee_inv_res_code',8)->nullable();
            $table->string('duitku_employee_inv_res_desc',100)->nullable();
            $table->string('duitku_employee_trf_res_code',8)->nullable();
            $table->string('duitku_employee_trf_res_desc',100)->nullable();
        
            $table->foreignId('rp_employee_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('rp_employee_at')->nullable();

        });

        Schema::table('trx_trp', function (Blueprint $table) {
            $table->text('note_for_remarks')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('extra_money_trx', function (Blueprint $table) {
            $table->dropForeign(["rp_employee_user"]);            
            
            $table->dropColumn('rp_employee_user');
            $table->dropColumn('rp_employee_at');            
            
            $table->dropColumn('duitku_employee_disburseId');
            $table->dropColumn('duitku_employee_inv_res_code');
            $table->dropColumn('duitku_employee_inv_res_desc');
            $table->dropColumn('duitku_employee_trf_res_code');
            $table->dropColumn('duitku_employee_trf_res_desc');
        });

        Schema::table('trx_trp', function (Blueprint $table) {
            $table->dropColumn('note_for_remarks');
        });
    }
};
