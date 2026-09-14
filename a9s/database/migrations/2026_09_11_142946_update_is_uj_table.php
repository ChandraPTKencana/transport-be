<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // foreignId creates a BIGINT UNSIGNED column behind the scenes
        Schema::table('is_uj', function (Blueprint $table) {
            $table->string('group_name',30)->nullable(); //0 Idle , 1 Done 
        });
    }

    public function down(): void
    {
        Schema::table('is_uj', function (Blueprint $table) {
            $table->dropColumn('group_name'); //0 Idle , 1 Done 
        });
    }
};
