<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MollieOrders extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'payment_method',
        'payment_id',
        'transaction_id',
        'createdAt',
        'givenName',
        'email',
        'shipping_id',
    ];
}
