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
        Schema::create('standby_dtl', function (Blueprint $table) {
            $table->id();
            $table->foreignId('standby_mst_id')->references('id')->on('standby_mst')->onDelete('restrict')->onUpdate('cascade');
            $table->bigInteger('ac_account_id');
            $table->string('ac_account_code');
            $table->string('ac_account_name');
            $table->decimal('amount',18);
            $table->text('description');

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
        Schema::dropIfExists('standby_dtl');
    }
};
