<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsbVendor extends Model
{
    protected $fillable = ['vid', 'vendor_name'];
}
