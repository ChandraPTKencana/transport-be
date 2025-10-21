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
        Schema::create('fin_payment_req', function (Blueprint $table) {
            $table->id();

            // $table->decimal('amount',18);
            $table->foreignId('created_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('updated_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');

            $table->boolean('val')->default(0);
            $table->foreignId('val_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val_at')->nullable();

            $table->boolean('deleted')->default(0);
            $table->foreignId('deleted_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('deleted_at')->nullable();
            $table->text('deleted_reason')->nullable();
            $table->string('filename')->nullable();

            $table->string('status',5); //OPEN,CLOSE
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
        Schema::dropIfExists('fin_payment_req');
    }
};
