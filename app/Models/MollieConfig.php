<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MollieConfig extends Model
{
    use HasFactory;

    protected $table = 'mollie_config';
    protected $fillable = [
        'store_id',
        'mollie_api',
    ];

    public function mollie_cofig() {
        return $this->hasMany(MollieOrders::class, 'mollie_config_id', 'table_id');
    }
}
