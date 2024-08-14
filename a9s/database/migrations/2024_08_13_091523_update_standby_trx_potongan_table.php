<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("ALTER TABLE standby_trx_dtl ADD attachment_1 LONGBLOB");     
        DB::statement("ALTER TABLE potongan_mst ADD attachment_1 LONGBLOB");     
        DB::statement("ALTER TABLE potongan_mst ADD attachment_2 LONGBLOB");
        
        Schema::table('standby_trx_dtl', function (Blueprint $table) {
            $table->string("attachment_1_type",255)->nullable();
        });

        Schema::table('potongan_mst', function (Blueprint $table) {
            $table->string("attachment_1_type",255)->nullable();
            $table->string("attachment_2_type",255)->nullable();
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
            $table->dropColumn('attachment_1');
            $table->dropColumn('attachment_1_type');
        });

        Schema::table('potongan_mst', function (Blueprint $table) {
            $table->dropColumn('attachment_1');
            $table->dropColumn('attachment_1_type');
            $table->dropColumn('attachment_2');
            $table->dropColumn('attachment_2_type');

        });
    }
};
