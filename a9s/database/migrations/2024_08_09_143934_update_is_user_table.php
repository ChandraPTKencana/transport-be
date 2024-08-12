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
        Schema::table('is_users', function (Blueprint $table) {
            $table->string('ga_secret_key',255)->nullable();
            $table->timestamp('ga_timeout',3)->nullable();
        });
    }
    public function down()
    {
        Schema::table('is_users', function (Blueprint $table) {       
            $table->dropColumn('ga_secret_key');
            $table->dropColumn('ga_timeout');
        });
    }
};
