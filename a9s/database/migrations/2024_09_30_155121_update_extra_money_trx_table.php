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
        DB::statement('ALTER TABLE extra_money_trx CHANGE note note_for_remarks TEXT');

        Schema::table('extra_money', function (Blueprint $table) {
            $table->dropForeign(["req_deleted_user"]);
            $table->dropColumn('req_deleted_user');

            $table->dropColumn('req_deleted');
            $table->dropColumn('req_deleted_at');
            $table->dropColumn('req_deleted_reason');
        });

        Schema::table('extra_money_trx', function (Blueprint $table) {
            $table->foreignId('payment_method_id')->default(1)->references('id')->on('payment_method')->onDelete('restrict')->onUpdate('cascade');

            $table->bigInteger('duitku_employee_disburseId')->nullable();
            $table->string('duitku_employee_inv_res_code',8)->nullable();
            $table->string('duitku_employee_inv_res_desc',100)->nullable();
            $table->string('duitku_employee_trf_res_code',8)->nullable();
            $table->string('duitku_employee_trf_res_desc',100)->nullable();

            $table->boolean('received_payment')->default(0);

            $table->string('attachment_1_loc',255)->nullable();
        });

        Schema::table('salary_paid', function (Blueprint $table) {
            $table->tinyInteger('period_part');
        });

        Schema::table('salary_paid_dtl', function (Blueprint $table) {
            $table->decimal('sb_gaji',18)->default(0);
            $table->decimal('sb_makan',18)->default(0);
            $table->dropColumn('standby_nominal');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement('ALTER TABLE extra_money_trx CHANGE note_for_remarks note TEXT');

        Schema::table('extra_money', function (Blueprint $table) {
            $table->boolean('req_deleted')->default(0);
            $table->foreignId('req_deleted_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('req_deleted_at')->nullable();
            $table->text('req_deleted_reason')->nullable();
        });
        
        Schema::table('extra_money_trx', function (Blueprint $table) {
            $table->dropForeign(["payment_method_id"]);
            $table->dropColumn('payment_method_id');

            $table->dropColumn('duitku_employee_disburseId');
            $table->dropColumn('duitku_employee_inv_res_code');
            $table->dropColumn('duitku_employee_inv_res_desc');
            $table->dropColumn('duitku_employee_trf_res_code');
            $table->dropColumn('duitku_employee_trf_res_desc');

            $table->dropColumn('received_payment');

            $table->dropColumn('attachment_1_loc');
        });

        Schema::table('salary_paid', function (Blueprint $table) {
            $table->dropColumn('period_part');
        });

        Schema::table('salary_paid_dtl', function (Blueprint $table) {
            $table->dropColumn('sb_gaji');
            $table->dropColumn('sb_makan');
            $table->decimal('standby_nominal',18)->default(0);
        });


    }
};
