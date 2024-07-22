<?php

use App\Models\MySql\PaymentMethod;
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
        Schema::create('payment_method', function (Blueprint $table) {
            $table->id();
            $table->string('name',15);
            $table->string('account_code',20);
        });
 
        PaymentMethod::insert([
            "name"=>"CASH",
            "account_code"=>env('PVR_BANK_ACCOUNT_CODE'),
        ]);

        Schema::table('trx_trp', function (Blueprint $table) {
            $table->foreignId('payment_method_id')->default(1)->references('id')->on('payment_method')->onDelete('restrict')->onUpdate('cascade');
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
            $table->dropForeign(["payment_method_id"]);
            $table->dropColumn('payment_method_id');
        });

        Schema::dropIfExists('payment_method');
    }
};
