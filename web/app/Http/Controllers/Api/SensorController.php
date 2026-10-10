<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SensorReading;
use Illuminate\Http\Request;

class SensorController extends Controller
{
    // Dipanggil ESP32: menerima dan menyimpan data
    public function store(Request $request)
    {
        $validated = $request->validate([
            'temperature' => 'nullable|numeric',
            'humidity'    => 'nullable|numeric|between:0,100',
            'pressure'    => 'nullable|numeric',
            'pm1'         => 'nullable|integer|min:0',
            'pm25'        => 'nullable|integer|min:0',
            'pm10'        => 'nullable|integer|min:0',
            'gas_ppm'     => 'nullable|numeric|min:0',
            'ispu'        => 'nullable|integer|min:0',
            'dominan'     => 'nullable|string|max:10',
            'kategori'    => 'nullable|string|max:20',
        ]);

        $reading = SensorReading::create($validated);

        return response()->json([
            'message' => 'Data tersimpan',
            'id'      => $reading->id,
        ], 201);
    }

    // Dipakai dashboard: mengambil data terbaru
    public function latest()
    {
        return response()->json(SensorReading::latest('id')->first());
    }
}