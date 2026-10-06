<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_departments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 2)->unique();
            $table->string('name')->unique();
            $table->timestamps();
        });
        Schema::create('geo_provinces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained('geo_departments')->cascadeOnDelete();
            $table->string('code', 4)->unique();
            $table->string('name');
            $table->timestamps();
            $table->unique(['department_id', 'name']);
        });
        Schema::create('geo_districts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained('geo_provinces')->cascadeOnDelete();
            $table->string('code', 6)->unique();
            $table->string('name');
            $table->timestamps();
            $table->unique(['province_id', 'name']);
        });
        Schema::table('ports', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('id')->constrained('geo_departments')->nullOnDelete();
            $table->foreignId('province_id')->nullable()->after('department_id')->constrained('geo_provinces')->nullOnDelete();
            $table->foreignId('district_id')->nullable()->after('province_id')->constrained('geo_districts')->nullOnDelete();
        });

        $now = now();
        $departments = [
            ['id' => 1, 'code' => '16', 'name' => 'Loreto'], ['id' => 2, 'code' => '25', 'name' => 'Ucayali'], ['id' => 3, 'code' => '22', 'name' => 'San Martín'], ['id' => 4, 'code' => '17', 'name' => 'Madre de Dios'],
        ];
        DB::table('geo_departments')->insert(array_map(fn ($row) => $row + ['created_at' => $now, 'updated_at' => $now], $departments));
        $provinces = [
            ['id' => 1, 'department_id' => 1, 'code' => '1601', 'name' => 'Maynas'], ['id' => 2, 'department_id' => 1, 'code' => '1602', 'name' => 'Alto Amazonas'], ['id' => 3, 'department_id' => 1, 'code' => '1603', 'name' => 'Loreto'], ['id' => 4, 'department_id' => 1, 'code' => '1604', 'name' => 'Mariscal Ramón Castilla'], ['id' => 5, 'department_id' => 1, 'code' => '1605', 'name' => 'Requena'], ['id' => 6, 'department_id' => 1, 'code' => '1606', 'name' => 'Ucayali'], ['id' => 7, 'department_id' => 1, 'code' => '1607', 'name' => 'Datem del Marañón'], ['id' => 8, 'department_id' => 1, 'code' => '1608', 'name' => 'Putumayo'],
            ['id' => 9, 'department_id' => 2, 'code' => '2501', 'name' => 'Coronel Portillo'], ['id' => 10, 'department_id' => 2, 'code' => '2502', 'name' => 'Atalaya'], ['id' => 11, 'department_id' => 2, 'code' => '2503', 'name' => 'Padre Abad'], ['id' => 12, 'department_id' => 2, 'code' => '2504', 'name' => 'Purus'],
            ['id' => 13, 'department_id' => 3, 'code' => '2201', 'name' => 'Moyobamba'], ['id' => 14, 'department_id' => 3, 'code' => '2209', 'name' => 'San Martín'], ['id' => 15, 'department_id' => 3, 'code' => '2210', 'name' => 'Tocache'],
            ['id' => 16, 'department_id' => 4, 'code' => '1701', 'name' => 'Tambopata'], ['id' => 17, 'department_id' => 4, 'code' => '1702', 'name' => 'Manu'], ['id' => 18, 'department_id' => 4, 'code' => '1703', 'name' => 'Tahuamanu'],
        ];
        DB::table('geo_provinces')->insert(array_map(fn ($row) => $row + ['created_at' => $now, 'updated_at' => $now], $provinces));
        $districts = [
            ['province_id' => 1, 'code' => '160101', 'name' => 'Iquitos'], ['province_id' => 1, 'code' => '160112', 'name' => 'Belén'], ['province_id' => 1, 'code' => '160113', 'name' => 'Punchana'], ['province_id' => 1, 'code' => '160114', 'name' => 'San Juan Bautista'], ['province_id' => 2, 'code' => '160201', 'name' => 'Yurimaguas'], ['province_id' => 2, 'code' => '160205', 'name' => 'Lagunas'], ['province_id' => 3, 'code' => '160301', 'name' => 'Nauta'], ['province_id' => 3, 'code' => '160302', 'name' => 'Parinari'], ['province_id' => 4, 'code' => '160401', 'name' => 'Ramón Castilla'], ['province_id' => 4, 'code' => '160407', 'name' => 'Pebas'], ['province_id' => 5, 'code' => '160501', 'name' => 'Requena'], ['province_id' => 5, 'code' => '160504', 'name' => 'Jenaro Herrera'], ['province_id' => 6, 'code' => '160601', 'name' => 'Contamana'], ['province_id' => 6, 'code' => '160604', 'name' => 'Inahuaya'], ['province_id' => 7, 'code' => '160701', 'name' => 'Barranca'], ['province_id' => 7, 'code' => '160702', 'name' => 'Andoas'], ['province_id' => 7, 'code' => '160704', 'name' => 'Manseriche'], ['province_id' => 8, 'code' => '160801', 'name' => 'Putumayo'],
            ['province_id' => 9, 'code' => '250101', 'name' => 'Callería'], ['province_id' => 9, 'code' => '250105', 'name' => 'Yarinacocha'], ['province_id' => 9, 'code' => '250107', 'name' => 'Manantay'], ['province_id' => 10, 'code' => '250201', 'name' => 'Raymondi'], ['province_id' => 11, 'code' => '250301', 'name' => 'Padre Abad'], ['province_id' => 12, 'code' => '250401', 'name' => 'Purus'],
            ['province_id' => 13, 'code' => '220101', 'name' => 'Moyobamba'], ['province_id' => 14, 'code' => '220901', 'name' => 'Tarapoto'], ['province_id' => 14, 'code' => '220903', 'name' => 'Morales'], ['province_id' => 14, 'code' => '220907', 'name' => 'La Banda de Shilcayo'], ['province_id' => 15, 'code' => '221001', 'name' => 'Tocache'],
            ['province_id' => 16, 'code' => '170101', 'name' => 'Tambopata'], ['province_id' => 16, 'code' => '170103', 'name' => 'Inambari'], ['province_id' => 17, 'code' => '170201', 'name' => 'Manu'], ['province_id' => 18, 'code' => '170301', 'name' => 'Iñapari'],
        ];
        DB::table('geo_districts')->insert(array_map(fn ($row) => $row + ['created_at' => $now, 'updated_at' => $now], $districts));
    }

    public function down(): void
    {
        Schema::table('ports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('district_id');
            $table->dropConstrainedForeignId('province_id');
            $table->dropConstrainedForeignId('department_id');
        });
        Schema::dropIfExists('geo_districts');
        Schema::dropIfExists('geo_provinces');
        Schema::dropIfExists('geo_departments');
    }
};
