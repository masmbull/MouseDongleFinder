<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsbProduct extends Model
{
    protected $fillable = ['vid', 'pid', 'product_name', 'interface'];
}
