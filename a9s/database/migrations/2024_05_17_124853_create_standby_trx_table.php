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
        Schema::create('standby_trx', function (Blueprint $table) {
            $table->id();
            // $table->foreignId('trx_trp_id')->nullable()->references('id')->on('trx_trp')->onDelete('restrict')->onUpdate('cascade');
            // $table->date("tanggal");
            $table->string('transition_target',10)->nullable(); //PT
            $table->string('transition_type',4)->nullable(); // 1 = From, 2 = To
            
            $table->foreignId('standby_mst_id')->references('id')->on('standby_mst')->onDelete('restrict')->onUpdate('cascade');

            $table->string('supir',255)->nullable();
            $table->string('kernet',255)->nullable();
            $table->string('no_pol',12);
            $table->string('xto',50)->nullable();
            $table->text('note_for_remarks')->nullable();
            $table->string('ref',50)->nullable();
            // $table->text('keterangan',12);

            $table->string('cost_center_code',255)->nullable();
            $table->string('cost_center_desc',255)->nullable();

            $table->bigInteger('pvr_id')->nullable();
            $table->string('pvr_no',50)->nullable();
            $table->decimal('pvr_total',18)->nullable();
            $table->boolean('pvr_had_detail')->default(0);

            $table->bigInteger('pv_id')->nullable();
            $table->string('pv_no',50)->nullable();
            $table->decimal('pv_total',18)->nullable();
            $table->timestamp('pv_datetime',3)->nullable();

            $table->bigInteger('rv_id')->nullable();
            $table->string('rv_no',50)->nullable();
            $table->decimal('rv_total',18)->nullable();
            $table->boolean('rv_had_detail')->default(0);
            
            $table->boolean('val')->default(0);
            $table->foreignId('val_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val_at')->nullable();

            $table->boolean('val1')->default(0);
            $table->foreignId('val1_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val1_at')->nullable();

            $table->boolean('val2')->default(0);
            $table->foreignId('val2_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val2_at')->nullable();

            $table->boolean('req_deleted')->default(0);
            $table->foreignId('req_deleted_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('req_deleted_at')->nullable();
            $table->text('req_deleted_reason')->nullable();

            $table->boolean('deleted')->default(0);
            $table->foreignId('deleted_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('deleted_at')->nullable();
            $table->text('deleted_reason')->nullable();

            $table->foreignId('created_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('updated_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
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
        Schema::dropIfExists('standby_trx');
    }
};
