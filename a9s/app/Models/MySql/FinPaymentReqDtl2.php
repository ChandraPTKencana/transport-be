<?php

namespace App\Models\MySql;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinPaymentReqDtl2 extends Model
{
    use HasFactory;

    protected $table = 'fin_payment_req_dtl_2';  
   
    protected $casts = [
        'potongan_trx_ids' => 'array', // Casts the JSON to a PHP array automatically
    ];

    public function trx_trp()
    {
        return $this->belongsTo(TrxTrp::class, 'trx_trp_id', 'id');
    }

    public function extra_money_trx()
    {
        return $this->belongsTo(ExtraMoneyTrx::class, 'extra_money_trx_id', 'id');
    }

    public function val_by()
    {
        return $this->hasOne(IsUser::class, 'id', "val_user");
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, "employee_id", 'id');
    }

    public function fin_payment_req2()
    {
        return $this->belongsTo(FinPaymentReq2::class, "fin_payment_req_2_id", 'id');
    }
}
