<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    protected $fillable = [
        'organization_name',
        'website',
        'description',
        'logo',
        'address',
    ];

    protected $appends = [
        'total_members',
    ];

    /**
     * Get the members associated with the organization.
     */
    public function members()
    {
        return $this->hasMany(Members::class, 'organization_id');
    }

    /**
     * Get the total number of members in the organization.
     */
    public function getTotalMembersAttribute()
    {
        return $this->members()->count();
    }
}
