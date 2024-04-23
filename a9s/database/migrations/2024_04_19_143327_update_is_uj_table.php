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
            $table->boolean('val1',1)->default(0);
            $table->foreignId('val1_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val1_at')->nullable();
        });
        
        DB::statement('ALTER TABLE trx_trp MODIFY COLUMN amount decimal(18)');  

        Schema::table('trx_trp', function (Blueprint $table) { 
            $table->string('cost_center_code',255)->nullable();
            $table->string('cost_center_desc',255)->nullable();
            $table->string('pvr_id',50)->nullable();
            $table->string('pvr_number',50)->nullable();
            $table->decimal('pvr_amount',18)->nullable();
            $table->boolean('pvr_had_detail')->default(0);
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
            $table->dropForeign(["val1_user"]);
            $table->dropColumn('val1');
            $table->dropColumn('val1_user');
            $table->dropColumn('val1_at');
        });

        DB::statement('ALTER TABLE trx_trp MODIFY COLUMN amount VARCHAR(50)');  

        Schema::table('trx_trp', function (Blueprint $table) {
            $table->dropColumn('cost_center_code');
            $table->dropColumn('cost_center_desc');
            $table->dropColumn('pvr_id');
            $table->dropColumn('pvr_number');
            $table->dropColumn('pvr_amount');
            $table->dropColumn('pvr_had_detail');
        });
    }
};
