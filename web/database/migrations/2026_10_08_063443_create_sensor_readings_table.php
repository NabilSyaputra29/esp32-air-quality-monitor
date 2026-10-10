<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up(): void
{
    Schema::create('sensor_readings', function (Blueprint $table) {
        $table->id();
        $table->float('temperature')->nullable();   // suhu (°C)
        $table->float('humidity')->nullable();      // kelembapan (%)
        $table->float('pressure')->nullable();      // tekanan (hPa)
        $table->unsignedSmallInteger('pm1')->nullable();
        $table->unsignedSmallInteger('pm25')->nullable();
        $table->unsignedSmallInteger('pm10')->nullable();
        $table->float('gas_ppm')->nullable();       // gas MQ135
        $table->unsignedSmallInteger('ispu')->nullable();
        $table->string('dominan', 10)->nullable();  // PM2.5 / PM10 / Gas
        $table->string('kategori', 20)->nullable(); // Baik, Sedang, dst.
        $table->timestamps();                       // created_at & updated_at
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sensor_readings');
    }
};
