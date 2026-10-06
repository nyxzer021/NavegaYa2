<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('destination_cities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('department', 100);
            $table->string('river', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('destination_cities')->insert([
            ['name' => 'Iquitos', 'department' => 'Loreto', 'river' => 'Amazonas', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Yurimaguas', 'department' => 'Loreto', 'river' => 'Huallaga', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Nauta', 'department' => 'Loreto', 'river' => 'Marañón', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Contamana', 'department' => 'Loreto', 'river' => 'Ucayali', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Requena', 'department' => 'Loreto', 'river' => 'Ucayali', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Pucallpa', 'department' => 'Ucayali', 'river' => 'Ucayali', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Puerto Maldonado', 'department' => 'Madre de Dios', 'river' => 'Madre de Dios', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Tarapoto', 'department' => 'San Martín', 'river' => 'Huallaga', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('destination_cities');
    }
};
