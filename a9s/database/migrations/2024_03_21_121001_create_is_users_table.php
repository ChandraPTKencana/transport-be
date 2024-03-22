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
        Schema::create('is_users', function (Blueprint $table) {
            $table->id();
            $table->string('username',50)->unique();
            $table->string('password',255);
            $table->string('hak_akses',50);
            $table->string('status',1)->default("Y");
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
        Schema::dropIfExists('is_users');
    }
};
