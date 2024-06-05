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
        Schema::table('is_ujdetails2', function (Blueprint $table) {
            $table->string('xfor',10)->nullable();
        });

        Schema::table('standby_dtl', function (Blueprint $table) {
            $table->string('xfor',10)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('is_ujdetails2', function (Blueprint $table) { 
            $table->dropColumn('xfor');
        });
        Schema::table('standby_dtl', function (Blueprint $table) { 
            $table->dropColumn('xfor');
        });
    }
};
