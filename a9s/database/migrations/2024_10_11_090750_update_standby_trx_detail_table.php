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
        Schema::table('standby_trx_dtl', function (Blueprint $table) {        
            $table->boolean('be_paid')->default(0);
        });

        Schema::table('extra_money_trx', function (Blueprint $table) {        
            $table->string("attachment_1_type",255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('standby_trx_dtl', function (Blueprint $table) {
            $table->dropColumn('be_paid');
        });

        Schema::table('extra_money_trx', function (Blueprint $table) {        
            $table->dropColumn('attachment_1_type');
        });
    }
};
