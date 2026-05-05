<?php

namespace App\Models\MySql;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class TripInfo extends Model
{
    use HasFactory;

    protected $table = 'trip_info';
    
    public function getImgPreviewAttribute()
    {
        if ($this->img_loc && Storage::disk('public')->exists($this->img_loc)) {
            return "trx_trp/timbang_info/".$this->trx_trp_id."/".$this->id;
        }

        return null;
    }
 
    public function trip_info_ordinal()
    {
        return $this->belongsTo(TripInfoOrdinal::class, 'trip_info_ordinal_id', 'id');
    }

}
