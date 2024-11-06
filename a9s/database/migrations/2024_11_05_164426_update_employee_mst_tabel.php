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
        Schema::table('employee_mst', function (Blueprint $table) {
            $table->decimal('bpjs_kesehatan',18)->default(0);
            $table->decimal('bpjs_jamsos',18)->default(0);
        });

        Schema::table('rpt_salary_dtl', function (Blueprint $table) {
            $table->decimal('employee_bpjs_kesehatan',18)->default(0);
            $table->decimal('employee_bpjs_jamsos',18)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('employee_mst', function (Blueprint $table) {
            $table->dropColumn('bpjs_kesehatan');
            $table->dropColumn('bpjs_jamsos');
        });

        Schema::table('rpt_salary_dtl', function (Blueprint $table) {
            $table->dropColumn('employee_bpjs_kesehatan');
            $table->dropColumn('employee_bpjs_jamsos');
        });
    }
};
