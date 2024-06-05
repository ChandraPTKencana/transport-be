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
        Schema::create('syslog', function (Blueprint $table) {
            $table->timestamp('created_at')->useCurrent();
            $table->string("ip_address",25);
            $table->foreignId('created_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->string("module",50);
            $table->bigInteger("module_id")->nullable();
            $table->string("action",20);
            $table->text('note');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('syslog');
    }
};
