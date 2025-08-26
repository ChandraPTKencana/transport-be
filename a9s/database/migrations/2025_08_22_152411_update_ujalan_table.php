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
            $table->decimal('batas_persen_susut', 8, 2)->default(0);
        });
        Schema::table('salary_bonus', function (Blueprint $table) {
            $table->decimal('trx_trp_id', 8, 2)->nullable();
        });
        Schema::table('trx_trp', function (Blueprint $table) {
            $table->decimal('batas_persen_susut', 8, 2)->default(0);
            $table->foreignId('salary_paid_id')->nullable()->references('id')->on('salary_paid')->onDelete('restrict')->onUpdate('cascade');
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
            $table->dropColumn('batas_persen_susut');
        });
        Schema::table('salary_bonus', function (Blueprint $table) {
            $table->dropColumn('trx_trp_id');
        });
        Schema::table('trx_trp', function (Blueprint $table) {
            $table->dropForeign(["salary_paid_id"]);
            $table->dropColumn('salary_paid_id');
            $table->dropColumn('batas_persen_susut');           
        });
    }
};
