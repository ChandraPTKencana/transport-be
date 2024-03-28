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
        Schema::create('trx_trp', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');            
            
            $table->foreignId('id_uj')->references('id')->on('is_uj')->onDelete('restrict')->onUpdate('cascade');
            $table->string('xto',50);
            $table->string('tipe',50);
            $table->string('jenis',50);
            $table->string('amount',50);
            
            $table->bigInteger('pv_id')->nullable();
            $table->string('pv_no',255)->nullable();
            $table->bigInteger('pv_total')->nullable();

            $table->bigInteger('ticket_a_id')->nullable();
            $table->string('ticket_a_no',255)->nullable();
            $table->bigInteger('ticket_a_bruto')->nullable();
            $table->bigInteger('ticket_a_tara')->nullable();
            $table->bigInteger('ticket_a_netto')->nullable();
            $table->string('ticket_a_supir',255)->nullable();
            $table->string('ticket_a_no_pol',12)->nullable();
            $table->timestamp('ticket_a_in_at')->nullable();
            $table->timestamp('ticket_a_out_at')->nullable();

            $table->bigInteger('ticket_b_id')->nullable();
            $table->string('ticket_b_no',255)->nullable();
            $table->bigInteger('ticket_b_bruto')->nullable();
            $table->bigInteger('ticket_b_tara')->nullable();
            $table->bigInteger('ticket_b_netto')->nullable();
            $table->string('ticket_b_supir',255)->nullable();
            $table->string('ticket_b_no_pol',12)->nullable();
            $table->timestamp('ticket_b_in_at')->nullable();
            $table->timestamp('ticket_b_out_at')->nullable();

            $table->string('supir',255);
            $table->string('kernet',255);
            $table->string('no_pol',12);

            $table->boolean('val',1)->default(0);
            $table->foreignId('val_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val_at')->nullable();

            $table->boolean('val1',1)->default(0);
            $table->foreignId('val1_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val1_at')->nullable();

            $table->bigInteger('print')->default(0);
            
            $table->foreignId('created_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('updated_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamps();

            $table->boolean('deleted')->default(0);
            $table->foreignId('deleted_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('deleted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('trx_trp');
    }
};
