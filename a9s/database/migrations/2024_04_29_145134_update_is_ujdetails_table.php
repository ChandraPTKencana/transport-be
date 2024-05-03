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
        Schema::table('is_uj', function (Blueprint $table) {
            $table->text('note_for_remarks')->nullable();
        });
        Schema::table('is_ujdetails', function (Blueprint $table) {
            $table->boolean('for_remarks')->default(0);
        });  
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('is_uj', function (Blueprint $table) {
            $table->dropColumn('note_for_remarks');
        });
        Schema::table('is_ujdetails', function (Blueprint $table) {
            $table->dropColumn('for_remarks');
        });
    }
};
