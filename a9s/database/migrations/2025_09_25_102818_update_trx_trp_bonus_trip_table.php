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
            $table->decimal('bonus_trip_supir', 8, 2)->default(0);
            $table->decimal('bonus_trip_kernet', 8, 2)->default(0);
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
            $table->dropColumn('bonus_trip_supir');           
            $table->dropColumn('bonus_trip_kernet');           
        });
    }
};
