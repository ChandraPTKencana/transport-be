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
            $table->text('delete_reason')->nullable();
        });

        Schema::table('trx_trp', function (Blueprint $table) { 
            $table->date('pv_date')->nullable();
            $table->text('delete_reason')->nullable();
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
            $table->dropColumn('delete_reason');
        });

        Schema::table('trx_trp', function (Blueprint $table) { 
            $table->dropColumn('pv_date');
            $table->dropColumn('delete_reason');
        });
    }
};
