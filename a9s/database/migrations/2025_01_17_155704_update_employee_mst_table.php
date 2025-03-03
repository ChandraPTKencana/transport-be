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
            $table->string('religion',20)->default("ISLAM");
            
            $table->string('m_dekey',255)->nullable(); // IP Address + User ID + Rand(5 char)
            $table->string('m_enkey',255)->nullable(); // mkey_de ( encrypt )
            $table->string('username',50)->nullable()->unique();
            $table->string('password',255)->nullable();          

            $table->boolean('m_face_login')->default(0);
            $table->string('face_loc_target',255)->nullable();
            $table->string('face_loc_type',255)->nullable();
        });

        Schema::create('employee_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->references('id')->on('employee_mst')->onDelete('cascade')->onUpdate('cascade');            
            $table->string("token",255); //mkey from employee mst + expired datetime
            $table->string("m_enkey",255); //mkey from employee mst + expired datetime
            // $table->timestamp('expired_at');
            $table->timestamp('created_at')->useCurrent();
            // $table->foreignId('created_user')->references('id')->on('is_users')->onDelete('cascade')->onUpdate('cascade');
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
            $table->dropColumn('religion');

            $table->dropColumn('m_dekey');
            $table->dropColumn('m_enkey');
            $table->dropColumn('username');
            $table->dropColumn('password');
            
            $table->dropColumn('m_face_login');
            $table->dropColumn('face_loc_target');
            $table->dropColumn('face_loc_type');
        });

        Schema::dropIfExists('employee_sessions');
    }
};
