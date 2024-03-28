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
        Schema::create('is_ujdetails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_uj')->references('id')->on('is_uj')->onDelete('restrict')->onUpdate('cascade');

            $table->string('xdesc',50);
            $table->decimal('qty',18);
            $table->decimal('harga',18);

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
        Schema::dropIfExists('is_ujdetails');
    }
};
