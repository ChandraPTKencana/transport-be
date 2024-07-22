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
        Schema::table('trx_trp', function (Blueprint $table) {

            $table->text('ticket_note')->nullable();

            $table->boolean('val4')->default(0);
            $table->foreignId('val4_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val4_at')->nullable();

            $table->boolean('val5')->default(0);
            $table->foreignId('val5_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val5_at')->nullable();

            $table->boolean('val_ticket')->default(0);
            $table->foreignId('val_ticket_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val_ticket_at')->nullable(); 
        });

        DB::update('update trx_trp set val_ticket = val2, val_ticket_user = val2_user, val_ticket_at=val2_at');
        DB::update('update trx_trp set val2=0, val2_user=null, val2_at=null');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::update('update trx_trp set val2 = val_ticket , val2_user = val_ticket_user , val2_at = val_ticket_at');
        DB::update('update trx_trp set val_ticket=0, val_ticket_user=null, val_ticket_at=null');

        Schema::table('trx_trp', function (Blueprint $table) {
            $table->dropForeign(["val4_user"]);
            $table->dropForeign(["val5_user"]);
            $table->dropForeign(["val_ticket_user"]);

            $table->dropColumn('ticket_note');

            $table->dropColumn('val4');
            $table->dropColumn('val4_user');
            $table->dropColumn('val4_at');

            $table->dropColumn('val5');
            $table->dropColumn('val5_user');
            $table->dropColumn('val5_at');

            $table->dropColumn('val_ticket');
            $table->dropColumn('val_ticket_user');
            $table->dropColumn('val_ticket_at');
        });
    }
};
