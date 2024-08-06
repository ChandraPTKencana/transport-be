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
        Schema::create('extra_money_trx', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('updated_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamps();

            $table->foreignId('extra_money_id')->references('id')->on('extra_money')->onDelete('restrict')->onUpdate('cascade');

            $table->date('tanggal');

            $table->foreignId('employee_id')->references('id')->on('employee_mst')->onDelete('restrict')->onUpdate('cascade');
            $table->string('employee_rek_no',20)->nullable();
            $table->string('employee_rek_name',50)->nullable();

            $table->string('no_pol',12);
            $table->text('note')->nullable();

            $table->string('cost_center_code',255)->nullable();
            $table->string('cost_center_desc',255)->nullable();

            $table->bigInteger('pvr_id')->nullable();
            $table->string('pvr_no',50)->nullable();
            $table->decimal('pvr_total',18)->nullable();
            $table->boolean('pvr_complete')->default(0);

            $table->bigInteger('pv_id')->nullable();
            $table->string('pv_no',255)->nullable();
            $table->bigInteger('pv_total')->nullable();
            $table->timestamp('pv_datetime',3)->nullable();

            $table->boolean('val1',1)->default(0);
            $table->foreignId('val1_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val1_at')->nullable();

            $table->boolean('val2')->default(0);
            $table->foreignId('val2_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val2_at')->nullable();

            $table->boolean('val3')->default(0);
            $table->foreignId('val3_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val3_at')->nullable();

            $table->boolean('val4')->default(0);
            $table->foreignId('val4_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val4_at')->nullable();

            $table->boolean('val5')->default(0);
            $table->foreignId('val5_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val5_at')->nullable();

            $table->boolean('val6')->default(0);
            $table->foreignId('val6_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val6_at')->nullable();

            $table->boolean('req_deleted')->default(0);
            $table->foreignId('req_deleted_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('req_deleted_at')->nullable();
            $table->text('req_deleted_reason')->nullable();

            $table->boolean('deleted')->default(0);
            $table->foreignId('deleted_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('deleted_at')->nullable();
            $table->text('deleted_reason')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('extra_money_trx');
    }
};
