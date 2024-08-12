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
        Schema::table('trx_trp', function (Blueprint $table) {
            $table->bigInteger('duitku_supir_disburseId')->nullable();
            $table->string('duitku_supir_inv_res_code',8)->nullable();
            $table->string('duitku_supir_inv_res_desc',100)->nullable();
            $table->string('duitku_supir_trf_res_code',8)->nullable();
            $table->string('duitku_supir_trf_res_desc',100)->nullable();

            $table->bigInteger('duitku_kernet_disburseId')->nullable();
            $table->string('duitku_kernet_inv_res_code',8)->nullable();
            $table->string('duitku_kernet_inv_res_desc',100)->nullable();
            $table->string('duitku_kernet_trf_res_code',8)->nullable();
            $table->string('duitku_kernet_trf_res_desc',100)->nullable();

            $table->boolean('received_payment')->default(0);
        });

        Schema::table('employee_mst', function (Blueprint $table) {
            $table->foreignId('bank_id')->nullable()->references('id')->on('bank')->onDelete('restrict')->onUpdate('cascade');
            $table->dropColumn('bank_name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('trx_trp', function (Blueprint $table) {
            $table->dropColumn('duitku_supir_disburseId');
            $table->dropColumn('duitku_supir_inv_res_code');
            $table->dropColumn('duitku_supir_inv_res_desc');
            $table->dropColumn('duitku_supir_trf_res_code');
            $table->dropColumn('duitku_supir_trf_res_desc');

            $table->dropColumn('duitku_kernet_disburseId');
            $table->dropColumn('duitku_kernet_inv_res_code');
            $table->dropColumn('duitku_kernet_inv_res_desc');
            $table->dropColumn('duitku_kernet_trf_res_code');
            $table->dropColumn('duitku_kernet_trf_res_desc');

            $table->dropColumn('received_payment');
        });

        Schema::table('employee_mst', function (Blueprint $table) {
            $table->string('bank_name',20)->nullable();
            $table->dropForeign(["bank_id"]);            
            $table->dropColumn('bank_id');
        });

        
    }
};
