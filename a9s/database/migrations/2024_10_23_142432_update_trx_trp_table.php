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
            $table->boolean('pv_complete')->default(0);
        });

        Schema::table('extra_money_trx', function (Blueprint $table) {
            $table->boolean('pv_complete')->default(0);
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
            $table->dropColumn('pv_complete');
        });

        Schema::table('extra_money_trx', function (Blueprint $table) {
            $table->dropColumn('pv_complete');
        });

    }
};
