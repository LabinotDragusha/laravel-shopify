<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MollieOrders extends Model
{
    use HasFactory;

    protected $fillable = [
        'mollie_id',
        'payment_method',
        'payment_id',
        'transaction_id',
        'createdAt',
        'shipping_id',
        'email',
        'given_name',
        'mollie_config_id',
    ];
}
