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
        Schema::create('potongan_mst', function (Blueprint $table) {
            $table->id();
            $table->text('kejadian');
            $table->foreignId('employee_id')->references('id')->on('employee_mst')->onDelete('restrict')->onUpdate('cascade');
            $table->string('no_pol',12);
            $table->decimal('nominal',18);
            $table->decimal('nominal_cut',18);
            $table->decimal('remaining_cut',18)->default(0);
            $table->timestamps();

            $table->foreignId('created_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('updated_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');

            $table->boolean('val',1)->default(0);
            $table->foreignId('val_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val_at')->nullable();

            $table->boolean('val1',1)->default(0);
            $table->foreignId('val1_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val1_at')->nullable();
            
            $table->string('status',20);

            $table->boolean('deleted')->default(0);
            $table->foreignId('deleted_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('deleted_at')->nullable();
            $table->text('deleted_reason')->nullable();


        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('potongan_mst');
    }
};
