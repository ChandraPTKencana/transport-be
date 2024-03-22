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
        Schema::create('trx_tbs', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');            
            $table->string('xto',50);
            $table->foreignId('id_uj')->references('id')->on('is_uj')->onDelete('restrict')->onUpdate('cascade');
            $table->string('nopol',12);
            $table->bigInteger('pv');
            $table->bigInteger('tiketa');
            $table->bigInteger('tiketb');
            $table->bigInteger('bruto');
            $table->bigInteger('tara');
            $table->bigInteger('netto');
            $table->string('val',1)->default("N");
            $table->foreignId('val_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val_date')->nullable();
            $table->bigInteger('print');
            
            $table->foreignId('created_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('updated_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamps();

            $table->string('status',1)->default("Y");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('trx_tbs');
    }
};
