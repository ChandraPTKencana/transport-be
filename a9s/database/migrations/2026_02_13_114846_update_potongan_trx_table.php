<?php

use App\Models\MySql\PotonganTrx;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up()
    {
        Schema::table('potongan_trx', function (Blueprint $table) {
            $table->string('sumber',30)->default('TRIP'); //TRIP,MANUAL(bonus trip,kerajinan),PENERIMAAN
        });

        PotonganTrx::whereNull('trx_trp_id')->update(['sumber'=>'MANUAL']);
    }

    public function down()
    {
        Schema::table('potongan_trx', function (Blueprint $table) {
            $table->dropColumn('sumber');
        });
    }
};
