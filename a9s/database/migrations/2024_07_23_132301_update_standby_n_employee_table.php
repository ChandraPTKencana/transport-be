<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('standby_trx', function (Blueprint $table) {
            $table->foreignId('salary_paid_id')->nullable()->references('id')->on('employee_mst')->onDelete('restrict')->onUpdate('cascade');
        });

        Schema::table('vehicle_mst', function (Blueprint $table) {
            $table->string('tipe',10)->nullable();
        });

        Schema::table('info', function (Blueprint $table) {
            $table->id();
        });

        Schema::table('permission_list', function (Blueprint $table) {
            $table->id();
        });

        Schema::table('permission_user_detail', function (Blueprint $table) {
            $table->id();
        });

        Schema::table('permission_group_detail', function (Blueprint $table) {
            $table->id();
        });

        Schema::table('permission_group_user', function (Blueprint $table) {
            $table->id();
        });

        Schema::table('syslog', function (Blueprint $table) {
            $table->id();
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->id();
        });

        DB::statement("ALTER TABLE employee_mst ADD attachment_1 LONGBLOB"); 
        DB::statement("ALTER TABLE employee_mst ADD attachment_2 LONGBLOB"); 
        Schema::table('employee_mst', function (Blueprint $table) {
            $table->string("attachment_1_type",255)->nullable();
            $table->string("attachment_2_type",255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('standby_trx', function (Blueprint $table) {
            $table->dropForeign(["salary_paid_id"]);
            $table->dropColumn('salary_paid_id');
        });

        Schema::table('vehicle_mst', function (Blueprint $table) {
            $table->dropColumn('tipe');
        });

        Schema::table('employee_mst', function (Blueprint $table) {
            $table->dropColumn('attachment_1');
            $table->dropColumn('attachment_2');
            $table->dropColumn('attachment_1_type');
            $table->dropColumn('attachment_2_type');
        });

        Schema::table('info', function (Blueprint $table) {
            $table->dropColumn('id');
        });

        Schema::table('permission_list', function (Blueprint $table) {
            $table->dropColumn('id');
        });

        Schema::table('permission_user_detail', function (Blueprint $table) {
            $table->dropColumn('id');
        });

        Schema::table('permission_group_detail', function (Blueprint $table) {
            $table->dropColumn('id');
        });

        Schema::table('permission_group_user', function (Blueprint $table) {
            $table->dropColumn('id');
        });

        Schema::table('syslog', function (Blueprint $table) {
            $table->dropColumn('id');
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->dropColumn('id');
        });

    }
};
