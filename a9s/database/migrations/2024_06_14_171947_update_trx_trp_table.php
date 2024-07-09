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
        Schema::table('trx_trp', function (Blueprint $table) {
            $table->bigInteger('ticket_a_ori_bruto')->nullable();
            $table->bigInteger('ticket_a_ori_tara')->nullable();
            $table->bigInteger('ticket_a_ori_netto')->nullable();

            $table->bigInteger('ticket_b_ori_bruto')->nullable();
            $table->bigInteger('ticket_b_ori_tara')->nullable();
            $table->bigInteger('ticket_b_ori_netto')->nullable();
        
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
        Schema::table('trx_trp', function (Blueprint $table) {
            $table->dropColumn('ticket_a_ori_bruto');
            $table->dropColumn('ticket_a_ori_tara');
            $table->dropColumn('ticket_a_ori_netto');

            $table->dropColumn('ticket_b_ori_bruto');
            $table->dropColumn('ticket_b_ori_tara');
            $table->dropColumn('ticket_b_ori_netto');

            $table->dropColumn('rv_id');
            $table->dropColumn('rv_no');
            $table->dropColumn('rv_total');
            $table->dropColumn('rv_completed');
        });
    }
};
