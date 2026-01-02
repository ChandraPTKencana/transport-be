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
        Schema::table('payment_method', function (Blueprint $table) {
            $table->boolean('hidden')->default(0);
        });

        PaymentMethod::insert([
            "name"=>"TRANSFER-MANDIRI-6500",
            "account_code"=>"01.111.018",
            "hidden" => 1
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('payment_method', function (Blueprint $table) {
            $table->drop('hidden');
        });
    }
};
