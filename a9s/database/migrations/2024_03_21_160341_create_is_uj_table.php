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
        Schema::create('is_uj', function (Blueprint $table) {
            $table->id();
            $table->string('xto',50);
            $table->string('tipe',100);
            $table->string('jenis',50);

            $table->foreignId('created_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('updated_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamps();
            
            $table->decimal('harga',18);

            $table->boolean('deleted')->default(0);
            $table->foreignId('deleted_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('deleted_at')->nullable();

            $table->boolean('val',1)->default(0);
            $table->foreignId('val_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('is_uj');
    }
};
