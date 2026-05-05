<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\MySql\TripInfoOrdinal;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        Schema::create('trip_info_ordinal', function (Blueprint $table) {
            $table->id();
            $table->integer("ordinal");
            $table->string('dkey',255); //aksi.saat_timbang_masuk.a
            $table->string('title',255); //Timbang A - Masuk - Foto Mobil
            $table->boolean('permit_manual_input')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();        
            $table->foreignId('updated_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('created_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
        });

        $data =[
            ["aksi.saat_timbang_a.masuk.mobil","Timbang A - Masuk - Mobil",true],
            ["aksi.saat_timbang_a.masuk.gasoli","Timbang A - Masuk - Bensin",false],
            ["aksi.saat_timbang_a.masuk.cabin","Timbang A - Masuk - Kabin",false],
            ["aksi.saat_timbang_a.keluar.mobil","Timbang A - Keluar - Mobil",true],
            ["aksi.saat_timbang_a.keluar.gasoli","Timbang A - Keluar - Bensin",false],
            ["aksi.saat_timbang_a.keluar.cabin","Timbang A - Keluar - Kabin",false],
            ["aksi.saat_timbang_b.masuk.mobil","Timbang B - Masuk - Mobil",true],
            ["aksi.saat_timbang_b.keluar.mobil","Timbang B - Keluar - Mobil",true],
        ];

        $t_stamp = date("Y-m-d H:i:s");
        $ordinal=0;
        foreach ($data as $key => $val) {
            $ordinal++;
            TripInfoOrdinal::insert([
                "ordinal"=>$ordinal,
                "dkey"=>$val[0],
                "title"=>$val[1],
                "permit_manual_input"=>$val[2],
                "created_at"=>$t_stamp,
                "updated_at"=>$t_stamp,
                "created_user"=>1,
                "updated_user"=>1,
            ]);
        }

        Schema::create('trip_info', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trx_trp_id')->references('id')->on('trx_trp')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('trip_info_ordinal_id')->references('id')->on('trip_info_ordinal')->onDelete('restrict')->onUpdate('cascade');
            $table->string("img_loc",255)->nullable();
            $table->timestamp('img_at')->nullable();
            $table->decimal('img_latitude', 10, 8)->nullable();
            $table->decimal('img_longitude', 11, 8)->nullable(); 
            $table->string("img_note",255)->nullable();
            $table->timestamp('img_upload_at')->nullable();
            $table->foreignId('img_upload_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->boolean('source_browser')->default(1);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();        
            $table->foreignId('created_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('updated_user')->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');           
        });

        Schema::table('trx_trp', function (Blueprint $table) {
        //     $table->string("timbang_a_msk_2_gas_oil_loc",255)->nullable();
        //     $table->timestamp('timbang_a_msk_2_gas_oil_at')->nullable();        
        //     $table->string("timbang_a_msk_2_cabin_loc",255)->nullable();
        //     $table->timestamp('timbang_a_msk_2_cabin_at')->nullable();        
        //     $table->string("timbang_a_klr_2_gas_oil_loc",255)->nullable();
        //     $table->timestamp('timbang_a_klr_2_gas_oil_at')->nullable();        
        //     $table->string("timbang_a_klr_2_cabin_loc",255)->nullable();
        //     $table->timestamp('timbang_a_klr_2_cabin_at')->nullable();        

        //     $table->text('timbang_a_msk_2_note')->nullable();
        //     $table->text('timbang_a_klr_2_note')->nullable();

        //     $table->foreignId('timbang_a_msk_2_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
        //     $table->timestamp('timbang_a_msk_2_user_at')->nullable(); 
            
        //     $table->foreignId('timbang_a_klr_2_user')->nullable()->references('id')->on('is_users')->onDelete('restrict')->onUpdate('cascade');
        //     $table->timestamp('timbang_a_klr_2_user_at')->nullable();     
            $table->dropColumn('timbang_a_1_img_in_loc');
            $table->dropColumn('timbang_a_1_img_out_loc');
            $table->dropColumn('timbang_a_2_img_in_loc');
            $table->dropColumn('timbang_a_2_img_out_loc');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('trip_info');
        Schema::dropIfExists('trip_info_ordinal');

        Schema::table('trx_trp', function (Blueprint $table) {
            $table->string("timbang_a_1_img_in_loc",255)->nullable();
            $table->string("timbang_a_1_img_out_loc",255)->nullable();
            $table->string("timbang_a_2_img_in_loc",255)->nullable();
            $table->string("timbang_a_2_img_out_loc",255)->nullable();
        });
    }
};
