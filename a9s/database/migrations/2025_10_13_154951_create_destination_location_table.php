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
        Schema::create('destination_location', function (Blueprint $table) {
            $table->id();
            $table->string('xto',50);
            $table->integer("minimal_trip")->default(1);
            $table->decimal('bonus_trip_supir',18)->default(0);
            $table->decimal('bonus_next_trip_supir',18)->default(0);
            $table->decimal('bonus_trip_kernet',18)->default(0);
            $table->decimal('bonus_next_trip_kernet',18)->default(0);
            $table->foreignId('created_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('updated_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamps();
        });

        Schema::table('is_uj', function (Blueprint $table) {
            $table->foreignId('destination_location_id')->nullable()->references('id')->on('destination_location')->onDelete('restrict')->onUpdate('cascade');
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
            $table->dropForeign(["destination_location_id"]);
            $table->dropColumn('destination_location_id');      
        });

        Schema::dropIfExists('destination_location');
    }
};
