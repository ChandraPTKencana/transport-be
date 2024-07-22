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
        Schema::create('potongan_trx', function (Blueprint $table) {
            $table->id();
            $table->foreignId('potongan_mst_id')->references('id')->on('potongan_mst')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('trx_trp_id')->nullable()->references('id')->on('trx_trp')->onDelete('restrict')->onUpdate('cascade');
            $table->decimal('nominal_cut',18);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->foreignId('created_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('updated_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');

            $table->boolean('val',1)->default(0);
            $table->foreignId('val_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val_at')->nullable();

            $table->boolean('val1',1)->default(0);
            $table->foreignId('val1_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val1_at')->nullable();

            $table->boolean('deleted')->default(0);
            $table->foreignId('deleted_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('deleted_at')->nullable();
            $table->text('deleted_reason')->nullable();
                    
            $table->bigInteger('rv_id')->nullable();
            $table->string('rv_no',50)->nullable();
            $table->decimal('rv_total',18)->nullable();
            $table->boolean('rv_completed')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('potongan_trx');
    }
};
