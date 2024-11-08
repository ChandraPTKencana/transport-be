<?php

use App\Models\MySql\SalaryPaidDtl;
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
            $table->decimal('kerajinan',18)->default(0);
        });

        Schema::table('extra_money_trx', function (Blueprint $table) {
            $table->string('attachment_2_loc',255)->nullable();
            $table->string("attachment_2_type",255)->nullable();
        });

        Schema::table('standby_mst', function (Blueprint $table) {
            $table->boolean('is_trip')->default(true);
        });

        Schema::table('standby_trx', function (Blueprint $table) {
            $table->foreignId('trx_trp_id')->nullable()->references('id')->on('trx_trp')->onDelete('restrict')->onUpdate('cascade');
        });

        Schema::table('standby_trx_dtl', function (Blueprint $table) {
            $table->time('waktu')->nullable();
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
            $table->dropColumn('kerajinan');
        });

        Schema::table('extra_money_trx', function (Blueprint $table) {
            $table->dropColumn('attachment_2_loc');
            $table->dropColumn('attachment_2_type');
        });

        Schema::table('standby_mst', function (Blueprint $table) {
            $table->dropColumn('is_trip');
        });

        Schema::table('standby_trx', function (Blueprint $table) {
            $table->dropForeign(["trx_trp_id"]);
            $table->dropColumn('trx_trp_id');
        });

        Schema::table('standby_trx_dtl', function (Blueprint $table) {
            $table->dropColumn('waktu');
        });

    }
};
