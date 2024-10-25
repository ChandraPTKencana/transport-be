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
        Schema::table('trx_absen', function (Blueprint $table) {
            $table->string('gambar_loc',255)->nullable();
        });

        Schema::table('employee_mst', function (Blueprint $table) {
            $table->string('attachment_1_loc',255)->nullable();
            $table->string('attachment_2_loc',255)->nullable();
        });

        Schema::table('potongan_mst', function (Blueprint $table) {
            $table->string('attachment_1_loc',255)->nullable();
            $table->string('attachment_2_loc',255)->nullable();
        });

        Schema::table('salary_bonus', function (Blueprint $table) {
            $table->string('attachment_1_loc',255)->nullable();
        });

        Schema::table('standby_trx_dtl', function (Blueprint $table) {
            $table->string('attachment_1_loc',255)->nullable();
        });

        Schema::table('extra_money_trx', function (Blueprint $table) {
            $table->foreignId('trx_trp_id')->nullable()->references('id')->on('trx_trp')->onDelete('restrict')->onUpdate('cascade');
        
            $table->dropColumn('duitku_employee_disburseId');
            $table->dropColumn('duitku_employee_inv_res_code');
            $table->dropColumn('duitku_employee_inv_res_desc');
            $table->dropColumn('duitku_employee_trf_res_code');
            $table->dropColumn('duitku_employee_trf_res_desc');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('trx_absen', function (Blueprint $table) {
            $table->dropColumn('gambar_loc');
        });

        Schema::table('employee_mst', function (Blueprint $table) {
            $table->dropColumn('attachment_1_loc');
            $table->dropColumn('attachment_2_loc');
        });

        Schema::table('potongan_mst', function (Blueprint $table) {
            $table->dropColumn('attachment_1_loc');
            $table->dropColumn('attachment_2_loc');
        });

        Schema::table('salary_bonus', function (Blueprint $table) {
            $table->dropColumn('attachment_1_loc');
        });

        Schema::table('standby_trx_dtl', function (Blueprint $table) {
            $table->dropColumn('attachment_1_loc');
        });

        Schema::table('extra_money_trx', function (Blueprint $table) {
            $table->dropForeign(["trx_trp_id"]);
            $table->dropColumn('trx_trp_id');

            $table->bigInteger('duitku_employee_disburseId')->nullable();
            $table->string('duitku_employee_inv_res_code',8)->nullable();
            $table->string('duitku_employee_inv_res_desc',100)->nullable();
            $table->string('duitku_employee_trf_res_code',8)->nullable();
            $table->string('duitku_employee_trf_res_desc',100)->nullable();

        });
    }
};
