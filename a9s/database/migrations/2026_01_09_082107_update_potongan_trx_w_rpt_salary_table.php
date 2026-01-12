<?php

use App\Models\MySql\PotonganTrx;
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
        Schema::table('potongan_trx', function (Blueprint $table) {
            $table->date('tanggal')->default(DB::raw('(CURRENT_DATE)'));
        });

        PotonganTrx::query()->update([
            'tanggal' => DB::raw('DATE(created_at)')
        ]);

        Schema::table('rpt_salary_dtl', function (Blueprint $table) {
            $table->decimal('potongan_manual',18)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('potongan_trx', function (Blueprint $table) {
            $table->dropColumn('tanggal');
        });

        Schema::table('potongan_trx', function (Blueprint $table) {
            $table->dropColumn('potongan_manual');
        });
    }
};
