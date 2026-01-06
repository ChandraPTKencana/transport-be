<?php

use App\Models\MySql\Employee;
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
            $table->string('sim_name',255)->nullable();
            $table->boolean('val1',1)->default(0);
            $table->foreignId('val1_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('val1_at')->nullable();
            $table->string('workers_from',5)->default(env('app_name'));
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
            $table->dropForeign(["val1_user"]);
            $table->dropColumn('sim_name');
            $table->dropColumn('val1');
            $table->dropColumn('val1_user');
            $table->dropColumn('val1_at');
            $table->dropColumn('workers_from');
        });
    }
};
