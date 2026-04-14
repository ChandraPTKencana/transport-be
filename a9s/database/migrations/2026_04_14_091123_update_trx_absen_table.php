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
        Schema::table('trx_absen', function (Blueprint $table) { 
            // Latitude: Total 10 digits, 8 after the decimal point
            $table->decimal('latitude', 10, 8)->nullable();
            // Longitude: Total 11 digits are recommended for -180 to 180 range
            $table->decimal('longitude', 11, 8)->nullable(); 
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('trx_absen', function (Blueprint $table) { 
            $table->dropColumn('latitude');
            $table->dropColumn('longitude');
        });
    }
};
