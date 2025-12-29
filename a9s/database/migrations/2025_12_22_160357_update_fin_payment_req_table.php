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
        Schema::table('fin_payment_req', function (Blueprint $table) {
            $table->smallInteger('batch_no')->default(0);
            $table->timestamp('wait_at')->nullable();
        });

        Schema::table('setup', function (Blueprint $table) {
            $table->string('mandiri_no_rek',20)->default("");
            $table->string('mandiri_nama',50)->default("");
            $table->integer('reminder_service')->default(0);
        });


        DB::statement('ALTER TABLE bank MODIFY COLUMN name varchar(50)');  

        // Schema::table('bank', function (Blueprint $table) {
        //     $table->string('code_mandiri',10)->nullable();
        // });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('fin_payment_req', function (Blueprint $table) {
            $table->dropColumn('batch_no');
            $table->dropColumn('wait_at');
        });

        Schema::table('setup', function (Blueprint $table) {
            $table->dropColumn('mandiri_no_rek');
            $table->dropColumn('mandiri_nama');
            $table->dropColumn('reminder_service');
        });

        DB::statement('ALTER TABLE bank MODIFY COLUMN name varchar(20)');  

        // Schema::table('bank', function (Blueprint $table) {
        //     $table->dropColumn('code_mandiri');
        // });
    }
};
