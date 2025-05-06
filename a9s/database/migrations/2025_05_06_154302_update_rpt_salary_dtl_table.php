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
        Schema::table('rpt_salary_dtl', function (Blueprint $table) {
            $table->integer('trip_cpo')->default(0);
            $table->integer('trip_pk')->default(0);
            $table->integer('trip_tbs')->default(0);
            $table->integer('trip_tbsk')->default(0);
            $table->integer('trip_lain')->default(0);
            $table->dropColumn('total_trip');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('rpt_salary_dtl', function (Blueprint $table) {
            $table->dropColumn('trip_cpo');
            $table->dropColumn('trip_pk');
            $table->dropColumn('trip_tbs');
            $table->dropColumn('trip_tbsk');
            $table->dropColumn('trip_lain');
            $table->integer('total_trip')->default(0);
        });
    }
};
