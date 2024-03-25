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
        Schema::create('trx_cpo', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');            
            
            $table->foreignId('id_uj')->references('id')->on('is_uj')->onDelete('restrict')->onUpdate('cascade');
            $table->string('xto',50);
            $table->string('tipe',50);
            $table->string('amount',50);
            
            $table->bigInteger('pv_id')->nullable();
            $table->string('pv_no',255)->nullable();
            $table->bigInteger('pv_total')->nullable();

            $table->bigInteger('ticket_id')->nullable();
            $table->string('ticket_no',255)->nullable();
            $table->bigInteger('ticket_bruto')->nullable();
            $table->bigInteger('ticket_tara')->nullable();
            $table->bigInteger('ticket_netto')->nullable();
            $table->string('ticket_supir',255)->nullable();
            $table->string('ticket_no_pol',12)->nullable();

            $table->string('supir',255);
            $table->string('no_pol',12);
            $table->bigInteger('bruto')->nullable();
            $table->bigInteger('tara')->nullable();
            $table->bigInteger('netto')->nullable();

            $table->string('val',1)->default("N");
            $table->foreignId('val_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val_date')->nullable();
            $table->bigInteger('print')->default(0);
            
            $table->foreignId('created_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('updated_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamps();

            $table->boolean('deleted',1)->default(0);
            $table->foreignId('deleted_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('deleted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('trx_cpo');
    }
};
