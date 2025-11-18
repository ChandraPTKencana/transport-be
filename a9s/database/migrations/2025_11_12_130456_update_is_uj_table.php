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
            $table->boolean('val2',1)->default(0);
            $table->foreignId('val2_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val2_at')->nullable();

            $table->boolean('val3',1)->default(0);
            $table->foreignId('val3_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val3_at')->nullable();
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
            $table->dropForeign(["val2_user"]);
            $table->dropColumn('val2');
            $table->dropColumn('val2_user');
            $table->dropColumn('val2_at');

            $table->dropForeign(["val3_user"]);
            $table->dropColumn('val3');
            $table->dropColumn('val3_user');
            $table->dropColumn('val3_at');
        });
    }
};
