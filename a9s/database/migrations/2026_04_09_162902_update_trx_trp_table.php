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
            $table->string("timbang_a_1_img_in_loc",255)->nullable();
            $table->string("timbang_a_1_img_out_loc",255)->nullable();
            $table->string("timbang_a_2_img_in_loc",255)->nullable();
            $table->string("timbang_a_2_img_out_loc",255)->nullable();

            $table->text('timbang_a_note')->nullable();

            $table->boolean('timbang_a_val1')->default(0);
            $table->foreignId('timbang_a_val1_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('timbang_a_val1_at')->nullable();        
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
            $table->dropForeign(["timbang_a_val1_user"]);

            $table->dropColumn('timbang_a_val1');
            $table->dropColumn('timbang_a_val1_user');
            $table->dropColumn('timbang_a_val1_at');

            $table->dropColumn('timbang_a_note');

            $table->dropColumn('timbang_a_1_img_in_loc');
            $table->dropColumn('timbang_a_1_img_out_loc');
            $table->dropColumn('timbang_a_2_img_in_loc');
            $table->dropColumn('timbang_a_2_img_out_loc');
        });
    }
};
