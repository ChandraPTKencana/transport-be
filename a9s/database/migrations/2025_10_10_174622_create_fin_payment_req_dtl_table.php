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
        Schema::create('fin_payment_req_dtl', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fin_payment_req_id')->references('id')->on('fin_payment_req')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('trx_trp_id')->references('id')->on('trx_trp')->onDelete('restrict')->onUpdate('cascade');

            $table->foreignId('created_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('updated_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');

            $table->foreignId('employee_id')->references('id')->on('employee_mst')->onDelete('restrict')->onUpdate('cascade');

            $table->string('employee_name',50)->nullable();
            $table->string('employee_role',30)->nullable();

            $table->string('employee_rek_no',20)->nullable();
            $table->string('employee_rek_name',50)->nullable();
            $table->string('employee_bank_code',20)->nullable();

            $table->decimal('nominal',18)->default(0);
            
            $table->string('potongan_trx_ids',100)->nullable();
            $table->decimal('potongan_trx_ttl',18)->default(0);
            
            $table->string('extra_money_trx_ids',100)->nullable();
            $table->decimal('extra_money_trx_ttl',18)->default(0);
            
            $table->decimal('jumlah',18)->default(0);
            $table->string('status',18); //READY,INQUIRY_PROCESS,INQUIRY_FAILED,INQUIRY_SUCCESS,TRANSFER_PROCESS,TRANSFER_FAILED,TRANSFER_SUCCESS
            $table->string('failed_reason',255)->nullable(); //FAILED REASON
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fin_payment_req_dtl');
    }
};
