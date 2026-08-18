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
            $table->tinyInteger('sync')->default(0); //0 Idle , 1 Done 
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
            $table->dropColumn('sync'); //0 Idle , 1 Done 
        });
    }
};
