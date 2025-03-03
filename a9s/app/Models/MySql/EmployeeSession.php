<?php

namespace App\Models\MySql;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Str;
use Illuminate\Database\Eloquent\Builder;
use App\Traits\Excludable;

class EmployeeSession extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    use Excludable;
    protected $table = 'employee_sessions';
    public $timestamps = false;

    function the_employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id');
    }
}
