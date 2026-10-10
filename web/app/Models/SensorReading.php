<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SensorReading extends Model
{
    protected $fillable = [
        'temperature', 'humidity', 'pressure',
        'pm1', 'pm25', 'pm10',
        'gas_ppm', 'ispu', 'dominan', 'kategori',
    ];
}