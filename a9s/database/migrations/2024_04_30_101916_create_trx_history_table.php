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
        Schema::create('trx_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trx_trp_id')->references('id')->on('trx_trp')->onDelete('restrict')->onUpdate('cascade');
            $table->bigInteger('ticket_a_id')->nullable();
            $table->string('ticket_a_no',255)->nullable();
            $table->bigInteger('ticket_b_id')->nullable();
            $table->string('ticket_b_no',255)->nullable();
            $table->timestamps();
        });
        	
        // DB::statement("ALTER TABLE trx_history ADD gambar_1 LONGBLOB");     
        // DB::statement("ALTER TABLE trx_history ADD gambar_2 LONGBLOB");     
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('trx_history');
    }
};
