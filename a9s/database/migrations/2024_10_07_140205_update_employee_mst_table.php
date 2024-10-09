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
        Schema::table('trx_trp', function (Blueprint $table) {
            $table->foreignId('rp_supir_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('rp_supir_at')->nullable();

            $table->foreignId('rp_kernet_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('rp_kernet_at')->nullable();
        
            $table->boolean('val6')->default(0);
            $table->foreignId('val6_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val6_at')->nullable();
        });

        Schema::table('employee_mst', function (Blueprint $table) {
            $table->date('birth_date')->nullable();
            $table->string('birth_place',100)->nullable();

            $table->date('tmk')->nullable();
            $table->text('address')->nullable();
            $table->string('status',7)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('trx_trp', function (Blueprint $table) {
            $table->dropForeign(["rp_supir_user"]);            
            $table->dropForeign(["rp_kernet_user"]);
            $table->dropForeign(["val6_user"]);
            
            $table->dropColumn('rp_supir_user');
            $table->dropColumn('rp_supir_at');
            
            $table->dropColumn('rp_kernet_user');
            $table->dropColumn('rp_kernet_at');
            
            $table->dropColumn('val6');
            $table->dropColumn('val6_user');
            $table->dropColumn('val6_at');
        });

        Schema::table('employee_mst', function (Blueprint $table) {
            $table->dropColumn('birth_date');
            $table->dropColumn('birth_place');

            $table->dropColumn('tmk');
            $table->dropColumn('address');
            $table->dropColumn('status');
        });
    }
};
