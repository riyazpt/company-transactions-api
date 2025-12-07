<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;
   protected $fillable = [
        'user_id',
        'amount',
        'vat_percentage',
        'is_vat_inclusive',
        'due_on',
    ];

    protected $casts = [
        'is_vat_inclusive' => 'boolean',
        'due_on'           => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
