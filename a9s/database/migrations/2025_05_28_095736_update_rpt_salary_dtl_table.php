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
        // dd(config('database.connections')[env('DB_CONNECTION', 'mysql')]);
        // dd(config('database.connections'));
        Schema::table('rpt_salary_dtl', function (Blueprint $table) {
            $table->dropColumn('trip_cpo_bonus');
            $table->dropColumn('trip_pk_bonus');
            $table->dropColumn('trip_tbs_bonus');
            $table->dropColumn('trip_tbsk_bonus');

            $table->decimal('trip_tunggu_dinas',18)->default(0);
            $table->decimal('trip_cpo_bonus_gaji',18)->default(0);
            $table->decimal('trip_cpo_bonus_dinas',18)->default(0);
            $table->decimal('trip_pk_bonus_gaji',18)->default(0);
            $table->decimal('trip_pk_bonus_dinas',18)->default(0);
            $table->decimal('trip_tbs_bonus_gaji',18)->default(0);
            $table->decimal('trip_tbs_bonus_dinas',18)->default(0);
            $table->decimal('trip_tbsk_bonus_gaji',18)->default(0);
            $table->decimal('trip_tbsk_bonus_dinas',18)->default(0);
            $table->decimal('trip_lain_gaji',18)->default(0);
            $table->decimal('trip_lain_makan',18)->default(0);
            $table->decimal('trip_lain_dinas',18)->default(0);

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
            $table->dropColumn('trip_tunggu_dinas');
            $table->dropColumn('trip_cpo_bonus_gaji');
            $table->dropColumn('trip_cpo_bonus_dinas');
            $table->dropColumn('trip_pk_bonus_gaji');
            $table->dropColumn('trip_pk_bonus_dinas');
            $table->dropColumn('trip_tbs_bonus_gaji');
            $table->dropColumn('trip_tbs_bonus_dinas');
            $table->dropColumn('trip_tbsk_bonus_gaji');
            $table->dropColumn('trip_tbsk_bonus_dinas');
            $table->dropColumn('trip_lain_gaji');
            $table->dropColumn('trip_lain_makan');
            $table->dropColumn('trip_lain_dinas');

            $table->decimal('trip_cpo_bonus',18)->default(0);
            $table->decimal('trip_pk_bonus',18)->default(0);
            $table->decimal('trip_tbs_bonus',18)->default(0);
            $table->decimal('trip_tbsk_bonus',18)->default(0);
        });
    }
};
