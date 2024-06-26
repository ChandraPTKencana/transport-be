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
        Schema::table('employee_mst', function (Blueprint $table) {
            $table->string('ktp_no',50)->nullable();
            $table->string('sim_no',50)->nullable();
            
            $table->string('bank_name',20)->nullable();
            $table->string('rek_no',20)->nullable();
            $table->string('rek_name',50)->nullable();

            $table->string('phone_number',20)->nullable();

            $table->boolean('val')->default(0);
            $table->foreignId('val_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val_at')->nullable();
        });

        Schema::table('trx_trp', function (Blueprint $table) { 
            $table->foreignId('supir_id')->nullable()->references('id')->on('employee_mst')->onDelete('restrict')->onUpdate('cascade');
            $table->string('supir_rek_no',20)->nullable();
            $table->string('supir_rek_name',50)->nullable();
            $table->foreignId('kernet_id')->nullable()->references('id')->on('employee_mst')->onDelete('restrict')->onUpdate('cascade');
            $table->string('kernet_rek_no',20)->nullable();
            $table->string('kernet_rek_name',50)->nullable();
        });

        Schema::table('standby_trx', function (Blueprint $table) { 
            $table->foreignId('supir_id')->nullable()->references('id')->on('employee_mst')->onDelete('restrict')->onUpdate('cascade');
            $table->string('supir_rek_no',20)->nullable();
            $table->string('supir_rek_name',50)->nullable();
            $table->foreignId('kernet_id')->nullable()->references('id')->on('employee_mst')->onDelete('restrict')->onUpdate('cascade');
            $table->string('kernet_rek_no',20)->nullable();
            $table->string('kernet_rek_name',50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('employee_mst', function (Blueprint $table) {
            $table->dropForeign(["val_user"]);

            $table->dropColumn('val');
            $table->dropColumn('val_user');
            $table->dropColumn('val_at');
            
            $table->dropColumn('ktp_no');
            $table->dropColumn('sim_no');

            $table->dropColumn('bank_name');
            $table->dropColumn('rek_no');
            $table->dropColumn('rek_name');

            $table->dropColumn('phone_number');
        });

        Schema::table('trx_trp', function (Blueprint $table) { 
            $table->dropForeign(["supir_id"]);
            $table->dropForeign(["kernet_id"]);
            
            $table->dropColumn('supir_id');
            $table->dropColumn('supir_rek_no');
            $table->dropColumn('supir_rek_name');
            $table->dropColumn('kernet_id');
            $table->dropColumn('kernet_rek_no');
            $table->dropColumn('kernet_rek_name');
        });

        Schema::table('standby_trx', function (Blueprint $table) { 
            $table->dropForeign(["supir_id"]);
            $table->dropForeign(["kernet_id"]);
            
            $table->dropColumn('supir_id');
            $table->dropColumn('supir_rek_no');
            $table->dropColumn('supir_rek_name');
            $table->dropColumn('kernet_id');
            $table->dropColumn('kernet_rek_no');
            $table->dropColumn('kernet_rek_name');
        });
    }
};
