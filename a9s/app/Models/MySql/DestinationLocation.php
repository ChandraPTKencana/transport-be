<?php

namespace App\Models\MySql;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DestinationLocation extends Model
{
    use HasFactory;

    protected $table = 'destination_location';  
    // public $timestamps = false;



    // public function val1_by()
    // {
    //     return $this->hasOne(IsUser::class, 'id', "val1_user");
    // }

    // public function val2_by()
    // {
    //     return $this->hasOne(IsUser::class, 'id', "val2_user");
    // }

    // public function deleted_by()
    // {
    //     return $this->hasOne(IsUser::class, 'id', "deleted_user");
    // }

    // public function req_deleted_by()
    // {
    //     return $this->hasOne(IsUser::class, 'id', "req_deleted_user");
    // }

}
