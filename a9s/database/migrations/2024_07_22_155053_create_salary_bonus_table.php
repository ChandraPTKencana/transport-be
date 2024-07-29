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
        Schema::create('salary_bonus', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->string('type',20);
            $table->foreignId('employee_id')->references('id')->on('employee_mst')->onDelete('restrict')->onUpdate('cascade');
            $table->decimal('nominal',18)->default(0);
            $table->text('note')->nullable();
            $table->foreignId('created_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('updated_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('salary_paid_id')->nullable()->references('id')->on('salary_paid')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamps();

            $table->boolean('val1')->default(0);
            $table->foreignId('val1_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val1_at')->nullable();

            $table->boolean('val2')->default(0);
            $table->foreignId('val2_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val2_at')->nullable();

            $table->boolean('val3')->default(0);
            $table->foreignId('val3_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val3_at')->nullable();

            $table->boolean('deleted')->default(0);
            $table->foreignId('deleted_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('deleted_at')->nullable();
            $table->text('deleted_reason')->nullable();

            $table->string("attachment_1_type",255)->nullable();
        });

        DB::statement("ALTER TABLE salary_bonus ADD attachment_1 LONGBLOB"); 
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('salary_bonus');
    }
};
