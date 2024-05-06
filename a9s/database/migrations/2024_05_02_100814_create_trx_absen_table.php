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
        Schema::create('trx_absen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trx_trp_id')->references('id')->on('trx_trp')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamps();
        });

        DB::statement("ALTER TABLE trx_absen ADD gambar LONGBLOB");     
        // DB::statement("ALTER TABLE trx_history ADD gambar_2 LONGBLOB");     

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('trx_absen');
    }
};
