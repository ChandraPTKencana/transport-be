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

        Schema::table('standby_trx', function (Blueprint $table) {
            $table->boolean('val3')->default(0);
            $table->foreignId('val3_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val3_at')->nullable();

            $table->boolean('val4')->default(0);
            $table->foreignId('val4_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val4_at')->nullable();

            $table->boolean('val5')->default(0);
            $table->foreignId('val5_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val5_at')->nullable();
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('standby_trx', function (Blueprint $table) {
            $table->dropForeign(["val3_user"]);
            $table->dropColumn('val3');
            $table->dropColumn('val3_user');
            $table->dropColumn('val3_at');

            $table->dropForeign(["val4_user"]);
            $table->dropColumn('val4');
            $table->dropColumn('val4_user');
            $table->dropColumn('val4_at');

            $table->dropForeign(["val5_user"]);
            $table->dropColumn('val5');
            $table->dropColumn('val5_user');
            $table->dropColumn('val5_at');
        });
    }
};
