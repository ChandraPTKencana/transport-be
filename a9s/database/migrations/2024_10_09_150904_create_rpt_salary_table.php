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
        Schema::create('rpt_salary', function (Blueprint $table) {
            $table->id();
            $table->date('period_end');
            $table->foreignId('created_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('updated_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamps();

            $table->boolean('val1')->default(0);
            $table->foreignId('val1_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val1_at')->nullable();

        });

        Schema::create('rpt_salary_dtl', function (Blueprint $table) {
            $table->foreignId('rpt_salary_id')->references('id')->on('rpt_salary')->onDelete('restrict')->onUpdate('cascade');
            
            $table->foreignId('employee_id')->references('id')->on('employee_mst')->onDelete('restrict')->onUpdate('cascade');

            $table->string('employee_name',50)->nullable();
            $table->string('employee_role',30)->nullable();
            $table->string('employee_birth_place',100)->nullable();
            $table->date('employee_birth_date')->nullable();
            $table->date('employee_tmk')->nullable();
            $table->string('employee_ktp_no',50)->nullable();
            $table->text('employee_address')->nullable();
            $table->string('employee_status',7)->nullable();
            $table->string('employee_rek_no',20)->nullable();
            $table->string('employee_bank_name',20)->nullable();
            
            $table->decimal('sb_gaji',18)->default(0);
            $table->decimal('sb_makan',18)->default(0);

            $table->decimal('uj_gaji',18)->default(0);
            $table->decimal('uj_makan',18)->default(0);
            
            $table->decimal('nominal_cut',18)->default(0);
            $table->decimal('salary_bonus_nominal',18)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rpt_salary_dtl');
        Schema::dropIfExists('rpt_salary');
    }
};
