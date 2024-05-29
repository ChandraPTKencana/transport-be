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
            $table->foreignId('standby_mst_id')->references('id')->on('standby_mst')->onDelete('restrict')->onUpdate('cascade');
            $table->string('supir',255);
            $table->string('kernet',255)->nullable();
            $table->string('no_pol',12);

            // $table->text('keterangan',12);
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
