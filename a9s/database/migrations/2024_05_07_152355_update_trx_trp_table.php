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
        DB::statement('ALTER TABLE trx_trp ADD COLUMN pv_datetime timestamp(3) NULL');
        Schema::table('trx_trp', function (Blueprint $table) { 
            $table->text('deleted_reason')->nullable();
        });

        Schema::table('is_uj', function (Blueprint $table) {
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
        Schema::table('is_uj', function (Blueprint $table) { 
            $table->dropColumn('deleted_reason');
        });

        Schema::table('trx_trp', function (Blueprint $table) { 
            $table->dropColumn('pv_datetime');
            $table->dropColumn('deleted_reason');
        });
    }
};
