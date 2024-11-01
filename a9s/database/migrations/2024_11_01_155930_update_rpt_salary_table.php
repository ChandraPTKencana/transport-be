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
            $table->decimal('sb_gaji_2',18)->default(0);
            $table->decimal('sb_makan_2',18)->default(0);
            $table->decimal('sb_dinas_2',18)->default(0);
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
            $table->dropColumn('sb_gaji_2');
            $table->dropColumn('sb_makan_2');
            $table->dropColumn('sb_dinas_2');
        });
    }
};
