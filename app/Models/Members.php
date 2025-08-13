<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Members extends Model
{
    protected $fillable = [
        'organization_id',
        'name',
        'register_no',
        'email',
        'phone',
        'address',
        'plan',
        'age',
        'status',
        'join_date',
        'payment_status',
        'image'
    ];

    protected $appends = [
        'total_payments',
        'unpaid_payments',
        'paid_payments',
        'next_due_date',
    ];


    protected static function booted()
    {
        static::addGlobalScope('organization_filter', function ($builder) {
            $orgName = request()->header('Organization');

            if ($orgName) {
                $organization = Organization::where('organization_name', $orgName)->first();
                if ($organization) {
                    $builder->where($builder->getModel()->getTable() . '.organization_id', $organization->id);
                }
            }
        });
    }


    public function paymentDetails()
    {
        return $this->hasMany(PaymentDetails::class, 'member_id');
    }

    public function getTotalPaymentsAttribute()
    {
        return $this->paymentDetails()->sum('amount');
    }

    public function getUnpaidPaymentsAttribute()
    {
        return $this->paymentDetails()->where('status', 'unpaid')->sum('amount');
    }

    public function getPaidPaymentsAttribute()
    {
        return $this->paymentDetails()->where('status', 'paid')->sum('amount');
    }

    public function getCreatedAtAttribute($value)
    {
        return \Carbon\Carbon::parse($value)->format('d-m-Y H:i');
    }

    public function getNextDueDateAttribute($value)
    {
        return \Carbon\Carbon::parse($this->created_at)->addMonth(1)->format('d-m-Y H:i');
    }

    // public function getLastPaymentDateAttribute()
    // {
    //     return $this->paymentDetails()->latest('created_at')->value('created_at');
    // }
}
