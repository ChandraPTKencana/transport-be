<?php

namespace App\Models\MySql;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PermissionGroupDetail extends Model
{
    use HasFactory;

    protected $table = 'permission_group_detail';
    // public $timestamps = false;
    // protected $primaryKey = null;

    public function permission_group()
    {
        return $this->belongsTo(PermissionGroup::class, "permission_group_id", 'id');
    }

    // public function warehouse_source()
    // {
    //     return $this->belongsTo(\App\Models\HrmRevisiLokasi::class, "hrm_revisi_lokasi_source_id", 'id');
    // }

    // public function warehouse_target()
    // {
    //     return $this->belongsTo(\App\Models\HrmRevisiLokasi::class, "hrm_revisi_lokasi_target_id", 'id');
    // }

    // public function details()
    // {
    //     return $this->hasMany(FinPaymentReqDtl::class, 'fin_payment_req_id', 'id');
    // }

    // public function val_by()
    // {
    //     return $this->hasOne(IsUser::class, 'id', "val_user");
    // }

    // public function val1_by()
    // {
    //     return $this->hasOne(IsUser::class, 'id', "val1_user");
    // }

    // public function requester()
    // {
    //     return $this->hasOne(\App\Models\IsUser::class, 'id_user', "requested_by");
    // }

    // public function confirmer()
    // {
    //     return $this->hasOne(\App\Models\IsUser::class, 'id_user', "confirmed_by");
    // }
}
