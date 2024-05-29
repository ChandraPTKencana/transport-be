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
        DB::statement("ALTER TABLE trx_absen ADD gambarori LONGBLOB");
        Schema::table('trx_absen', function (Blueprint $table) { 
            $table->string('status',1)->default('B');
            $table->boolean('is_manual')->default(0);
            $table->boolean('is_sync')->default(0);
            $table->foreignId('created_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
        });
        Schema::table('trx_trp', function (Blueprint $table) {
            $table->boolean('req_deleted')->default(0);
            $table->foreignId('req_deleted_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('req_deleted_at')->nullable();
            $table->text('req_deleted_reason')->nullable();

            $table->timestamp('req_deleted_succeed_at')->nullable();

            $table->boolean('standby_is_open')->default(0);

            $table->timestamp('ritase_leave_at')->nullable();
            $table->timestamp('ritase_arrive_at')->nullable();
            $table->timestamp('ritase_return_at')->nullable();
            $table->timestamp('ritase_till_at')->nullable();
            $table->text('ritase_note')->nullable();

            $table->boolean('ritase_val')->default(0);
            $table->foreignId('ritase_val_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('ritase_val_at')->nullable();

            $table->boolean('ritase_val1')->default(0);
            $table->foreignId('ritase_val1_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('ritase_val1_at')->nullable();

            $table->boolean('val2')->default(0);
            $table->foreignId('val2_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val2_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('trx_absen', function (Blueprint $table) { 
            $table->dropForeign(["created_user"]);

            $table->dropColumn('gambarori');
            $table->dropColumn('status');
            $table->dropColumn('is_manual');
            $table->dropColumn('is_sync');
            $table->dropColumn('created_user');

        });

        Schema::table('trx_trp', function (Blueprint $table) {
            $table->dropForeign(["req_deleted_user"]);
            $table->dropForeign(["ritase_val_user"]);
            $table->dropForeign(["ritase_val1_user"]);
            $table->dropForeign(["val2_user"]);

            $table->dropColumn('req_deleted');
            $table->dropColumn('req_deleted_user');
            $table->dropColumn('req_deleted_at');
            $table->dropColumn('req_deleted_reason');

            $table->dropColumn('req_deleted_succeed_at');

            $table->dropColumn('standby_is_open');

            $table->dropColumn('ritase_leave_at');
            $table->dropColumn('ritase_arrive_at');
            $table->dropColumn('ritase_return_at');
            $table->dropColumn('ritase_till_at');
            $table->dropColumn('ritase_note');

            $table->dropColumn('ritase_val');
            $table->dropColumn('ritase_val_user');
            $table->dropColumn('ritase_val_at');

            $table->dropColumn('ritase_val1');
            $table->dropColumn('ritase_val1_user');
            $table->dropColumn('ritase_val1_at');

            $table->dropColumn('val2');
            $table->dropColumn('val2_user');
            $table->dropColumn('val2_at');

        });
    }
};
