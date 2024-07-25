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
        Schema::create('salary_paid_dtl', function (Blueprint $table) {
            $table->foreignId('salary_paid_id')->references('id')->on('salary_paid')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('employee_id')->references('id')->on('employee_mst')->onDelete('restrict')->onUpdate('cascade');
            $table->decimal('standby_nominal',18)->default(0);
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
        Schema::dropIfExists('salary_paid_dtl');
    }
};
