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
        Schema::create('trx_maintenance', function (Blueprint $table) {
            $table->id();
            $table->string('no_pol',12);
            $table->date('tanggal');
            $table->bigInteger('km_now')->default(0);
            $table->string('keterangan',1000)->default("");
            $table->string('status',1)->default('Y');
            $table->foreignId('created_user')->nullable()->references('id')->on('is_users')->onDelete('cascade')->onUpdate('cascade');
            $table->foreignId('updated_user')->nullable()->references('id')->on('is_users')->onDelete('cascade')->onUpdate('cascade');
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
            $table->bigInteger('iuid')->default(0);
            $table->string('iuno',100)->default("");
        }
    );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('trx_maintenance');
    }
};
