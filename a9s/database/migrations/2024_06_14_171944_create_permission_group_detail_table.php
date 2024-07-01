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
        Schema::create('permission_group_detail', function (Blueprint $table) {
            $table->integer("ordinal");
            $table->boolean('p_change')->default(false);
            $table->foreignId('permission_group_id')->references('id')->on('permission_group')->onDelete('restrict')->onUpdate('cascade');
            $table->string('permission_list_name',255);
            $table->foreignId('created_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('updated_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
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
        Schema::dropIfExists('permission_group_detail');
    }
};
