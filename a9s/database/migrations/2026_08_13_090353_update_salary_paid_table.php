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
        DB::statement('SET SESSION innodb_strict_mode = OFF;');
        Schema::table('salary_paid', function (Blueprint $table) {
            $table->string('payment_status',18)->default("OPEN"); //OPEN , CLOSE
            $table->string('filename',100)->nullable();
        });

        Schema::table('salary_paid_dtl', function (Blueprint $table) {
            $table->string('employee_role',30)->nullable();
            $table->string('employee_ktp_no',50)->nullable();
            $table->string('employee_name',50)->nullable();
            $table->string('employee_rek_no',20)->nullable();
            $table->string('employee_rek_name',50)->nullable();
            $table->string('employee_bank_code',20)->nullable();

            $table->decimal('payment_total',18)->default(0);
            $table->string('payment_status',18)->default("READY"); //READY,INQUIRY_PROCESS,INQUIRY_FAILED,INQUIRY_SUCCESS,TRANSFER_PROCESS,TRANSFER_FAILED,TRANSFER_SUCCESS
            $table->text('payment_failed_reason')->nullable(); //FAILED REASON
        });

        Schema::table('rpt_salary', function (Blueprint $table) {
            $table->string('payment_status',18)->default("OPEN");
            $table->string('filename',100)->nullable();
            $table->boolean('val2')->default(0);
            $table->foreignId('val2_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val2_at')->nullable();
        });

        Schema::table('rpt_salary_dtl', function (Blueprint $table) {
            $table->decimal('uj_gaji_manual',18)->default(0);
            $table->decimal('uj_makan_manual',18)->default(0);
            $table->decimal('uj_dinas_manual',18)->default(0);
            $table->text('uj_manual_reason')->nullable();

            $table->decimal('payment_total',18)->default(0);
            $table->string('payment_status',18)->default("READY"); //READY,INQUIRY_PROCESS,INQUIRY_FAILED,INQUIRY_SUCCESS,TRANSFER_PROCESS,TRANSFER_FAILED,TRANSFER_SUCCESS
            $table->text('payment_failed_reason')->nullable(); //FAILED REASON
        });
        DB::statement('SET SESSION innodb_strict_mode = ON;');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement('SET SESSION innodb_strict_mode = OFF;');
        Schema::table('salary_paid', function (Blueprint $table) {
            $table->dropColumn('payment_status');
            $table->dropColumn('filename');
        });

        Schema::table('salary_paid_dtl', function (Blueprint $table) {
            $table->dropColumn('employee_role');
            $table->dropColumn('employee_ktp_no');
            $table->dropColumn('employee_name');
            $table->dropColumn('employee_rek_no');
            $table->dropColumn('employee_rek_name');
            $table->dropColumn('employee_bank_code');

            $table->dropColumn('payment_total');
            $table->dropColumn('payment_status'); //READY,INQUIRY_PROCESS,INQUIRY_FAILED,INQUIRY_SUCCESS,TRANSFER_PROCESS,TRANSFER_FAILED,TRANSFER_SUCCESS
            $table->dropColumn('payment_failed_reason'); 
        });

        Schema::table('rpt_salary', function (Blueprint $table) {
            $table->dropForeign(["val2_user"]);
            $table->dropColumn('val2');
            $table->dropColumn('val2_user');
            $table->dropColumn('val2_at');
            $table->dropColumn('payment_status');
            $table->dropColumn('filename');
        });

        Schema::table('rpt_salary_dtl', function (Blueprint $table) {
            $table->dropColumn('uj_gaji_manual');
            $table->dropColumn('uj_makan_manual');
            $table->dropColumn('uj_dinas_manual');
            $table->dropColumn('uj_manual_reason');
            $table->dropColumn('payment_total');
            $table->dropColumn('payment_status');
            $table->dropColumn('payment_failed_reason');
        });

        DB::statement('SET SESSION innodb_strict_mode = ON;');
    }
};
