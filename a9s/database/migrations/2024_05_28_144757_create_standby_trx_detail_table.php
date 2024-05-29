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
        Schema::create('standby_trx_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('standby_trx_id')->references('id')->on('standby_trx')->onDelete('restrict')->onUpdate('cascade');
            $table->date("tanggal");
            $table->text('keterangan',12);

            $table->foreignId('created_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('updated_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamps();

            $table->integer("ordinal");
            $table->boolean('p_change')->default(false);

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('standby_trx_detail');
    }
};
