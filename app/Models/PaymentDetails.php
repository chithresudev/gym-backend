<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class PaymentDetails extends Model
{
    public function getCreatedAtAttribute($value)
    {
        return Carbon::parse($value)->format('d-M-Y h:i:s');
    }

    public function getPaidAtAttribute($value)
    {
        return Carbon::parse($value)->format('d-M-Y h:i:s');
    }
}
