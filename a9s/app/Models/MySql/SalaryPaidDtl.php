<?php

namespace App\Models\MySql;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalaryPaidDtl extends Model
{
    use HasFactory;

    protected $table = 'salary_paid_dtl';

    protected $primaryKey = null;
    public $incrementing = false;
    protected $fillable = [
        'employee_role',
        'employee_name',
        'employee_ktp_no',
        'employee_rek_no',
        'employee_rek_name',
        'employee_bank_code',
        'payment_status',
        'payment_total',
    ];
    // protected $guarded = [];
    public function employee()
    {
        return $this->belongsTo(Employee::class, "employee_id", 'id')->exclude(['attachment_1','attachment_2']);
    }
}
