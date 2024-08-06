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
        Schema::create('extra_money', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('updated_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->string('xto',50);
            $table->string('jenis',50);
            $table->string('transition_target',10)->nullable(); //PT
            $table->string('transition_type',4)->nullable(); // 1 = From, 2 = To
            $table->bigInteger('ac_account_id');
            $table->string('ac_account_code');
            $table->string('ac_account_name');
            $table->decimal('nominal',18);
            $table->decimal('qty',18);
            $table->text('description');
            $table->timestamps();

            $table->boolean('val1',1)->default(0);
            $table->foreignId('val1_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val1_at')->nullable();

            $table->boolean('val2')->default(0);
            $table->foreignId('val2_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val2_at')->nullable();

            $table->boolean('req_deleted')->default(0);
            $table->foreignId('req_deleted_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('req_deleted_at')->nullable();
            $table->text('req_deleted_reason')->nullable();

            $table->boolean('deleted')->default(0);
            $table->foreignId('deleted_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('deleted_at')->nullable();
            $table->text('deleted_reason')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('extra_money');
    }
};
