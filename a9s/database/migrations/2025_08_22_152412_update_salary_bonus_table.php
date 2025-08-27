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
        Schema::table('salary_bonus', function (Blueprint $table) {
            $table->dropColumn('trx_trp_id');
        });

        Schema::table('salary_bonus', function (Blueprint $table) {
            $table->foreignId('trx_trp_id')->nullable()->references('id')->on('trx_trp')->onDelete('restrict')->onUpdate('cascade');
        });
    }
    
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        
        Schema::table('salary_bonus', function (Blueprint $table) {
            $table->dropForeign(["trx_trp_id"]);
            $table->dropColumn('trx_trp_id');
        });

        Schema::table('salary_bonus', function (Blueprint $table) {
            $table->decimal('trx_trp_id', 8, 2)->nullable();
        });

    }
};
