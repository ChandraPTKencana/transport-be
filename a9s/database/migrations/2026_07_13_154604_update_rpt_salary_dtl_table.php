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
            $table->integer('trip_cangkang')->default(0);
            $table->decimal('trip_cangkang_bonus_gaji',18)->default(0);
            $table->decimal('trip_cangkang_bonus_dinas',18)->default(0);
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
            $table->dropColumn('trip_cangkang');
            $table->dropColumn('trip_cangkang_bonus_gaji');
            $table->dropColumn('trip_cangkang_bonus_dinas');
        });
    }
};
