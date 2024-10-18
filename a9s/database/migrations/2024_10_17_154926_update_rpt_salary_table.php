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

        Schema::table('salary_paid_dtl', function (Blueprint $table) {
            $table->decimal('sb_dinas',18)->default(0);
        });

        Schema::table('rpt_salary_dtl', function (Blueprint $table) {        
            $table->decimal('sb_dinas',18)->default(0);
            $table->decimal('uj_dinas',18)->default(0);
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('salary_paid_dtl', function (Blueprint $table) {
            $table->dropColumn('sb_dinas');
        });

        Schema::table('rpt_salary_dtl', function (Blueprint $table) {
            $table->dropColumn('sb_dinas');
            $table->dropColumn('uj_dinas');
        });
    }
};
