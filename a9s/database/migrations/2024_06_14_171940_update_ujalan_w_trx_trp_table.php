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
            $table->string('transition_from',10)->nullable();
        });

        Schema::table('standby_mst', function (Blueprint $table) {
            $table->boolean('is_transition',10)->default(false);
        });

        DB::statement('ALTER TABLE trx_trp CHANGE transition_to transition_target VARCHAR(10)');
        Schema::table('trx_trp', function (Blueprint $table) { 
            $table->string('transition_type',4)->nullable();

            $table->boolean('val3')->default(0);
            $table->foreignId('val3_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val3_at')->nullable();

            $table->string('fin_status',1)->default("N"); //N = Not Finished , P = "Process" , Y = "Finished"

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
            $table->dropColumn('transition_from');
        });

        Schema::table('standby_mst', function (Blueprint $table) {
            $table->dropColumn('is_transition');
        });

        DB::statement('ALTER TABLE trx_trp CHANGE transition_target transition_to VARCHAR(10)');
        Schema::table('trx_trp', function (Blueprint $table) { 
            $table->dropForeign(["val3_user"]);

            $table->dropColumn('transition_type');

            $table->dropColumn('val3');
            $table->dropColumn('val3_user');
            $table->dropColumn('val3_at');
            
            $table->dropColumn('fin_status');
        });

    }
};
