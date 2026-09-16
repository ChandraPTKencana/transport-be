<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_payment_req_2', function (Blueprint $table) {
            $table->id();

            // $table->decimal('amount',18);
            $table->foreignId('created_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('updated_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');

            $table->boolean('val')->default(0);
            $table->foreignId('val_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val_at')->nullable();

            $table->boolean('deleted')->default(0);
            $table->foreignId('deleted_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('deleted_at')->nullable();
            $table->text('deleted_reason')->nullable();
            $table->string('filename')->nullable();

            $table->json('steps')->nullable(); 
            $table->string('status',15)->nullable(); //READY,SEND,RESEND,FAILED,SUCCESS,SET BATCH, SET PAID DONE,CLOSE
            $table->smallInteger('batch_no')->default(0);
            $table->timestamp('wait_at')->nullable();
            $table->timestamps();
        });

        Schema::create('fin_payment_req_dtl_2', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fin_payment_req_2_id')->references('id')->on('fin_payment_req_2')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('created_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('updated_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');

            $table->foreignId('trx_trp_id')->nullable()->references('id')->on('trx_trp')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('extra_money_trx_id')->nullable()->references('id')->on('extra_money_trx')->onDelete('restrict')->onUpdate('cascade');
            $table->json('potongan_trx_ids')->nullable();

            $table->foreignId('employee_id')->references('id')->on('employee_mst')->onDelete('restrict')->onUpdate('cascade');
            $table->string('employee_name',50)->nullable();
            $table->string('employee_role',30)->nullable();

            $table->string('employee_rek_no',20)->nullable();
            $table->string('employee_rek_name',50)->nullable();
            $table->string('employee_bank_code',20)->nullable();

            $table->decimal('nom_potongan_trxs',18)->default(0);
            $table->decimal('nom_trx_trp',18)->default(0);
            $table->decimal('nom_extra_money_trx',18)->default(0);

            $table->decimal('nom_final',18)->default(0);
            
            $table->string('status',18); //READY,INQUIRY_PROCESS,INQUIRY_FAILED,INQUIRY_SUCCESS,TRANSFER_PROCESS,TRANSFER_FAILED,TRANSFER_SUCCESS
            $table->string('failed_reason',255)->nullable(); //FAILED REASON
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_payment_req_dtl_2');

        Schema::dropIfExists('fin_payment_req_2');
    }
};
