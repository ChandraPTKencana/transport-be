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
            $table->decimal('trip_cpo_bonus',18)->default(0);
            $table->decimal('trip_pk_bonus',18)->default(0);
            $table->decimal('trip_tbs_bonus',18)->default(0);
            $table->decimal('trip_tbsk_bonus',18)->default(0);
            $table->integer('trip_tunggu')->default(0);
            $table->decimal('trip_tunggu_gaji',18)->default(0);
        });

        Schema::table('is_uj', function (Blueprint $table) {
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
        Schema::table('rpt_salary_dtl', function (Blueprint $table) {
            $table->dropColumn('trip_cpo_bonus');
            $table->dropColumn('trip_pk_bonus');
            $table->dropColumn('trip_tbs_bonus');
            $table->dropColumn('trip_tbsk_bonus');
            $table->dropColumn('trip_tunggu');
            $table->dropColumn('trip_tunggu_gaji');
        });

        Schema::table('is_uj', function (Blueprint $table) {
            $table->dropColumn('bonus_trip_supir');
            $table->dropColumn('bonus_trip_kernet');
        });
    }
};
