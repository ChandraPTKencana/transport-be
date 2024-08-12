<?php

use App\Models\MySql\Bank;
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
        Schema::create('bank', function (Blueprint $table) {
            $table->id();
            $table->string("code",10)->unique();
            $table->string("name",20);
            $table->string("code_duitku",6)->nullable();
            $table->timestamps();
        });


        Bank::insert([
            'code'=>'Mandiri',
            'name'=>'Bank Mandiri',
            'code_duitku'=>'008'
        ]);

        Bank::insert([
            'code'=>'BRI',
            'name'=>'Bank BRI',
            'code_duitku'=>'002'
        ]);

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bank');
    }
};
