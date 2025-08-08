<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Disable foreign key checks
        //DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Clear existing data
        $this->clearTables();

        // Seed data in order
        $this->seedUnitTypes();
        $this->seedUnits();
        $this->seedFuelStorages();
        $this->seedFuelTrucks();
        $this->seedShifts();
        $this->seedDailySessions();
        $this->seedFuelConsumptionRates();
        $this->seedFuelTransfers();
        $this->seedFuelTransactions();
        $this->seedPhysicalStockChecks();
        $this->seedUnitConsumptionSummaries();
        $this->seedVarianceReports();

        // Re-enable foreign key checks
        //DB::statement('SET FOREIGN_KEY_CHECKS=0;');
    }

    private function clearTables(): void
    {
        $tables = [
            'variance_reports',
            'unit_consumption_summaries',
            'physical_stock_checks',
            'fuel_transactions',
            'fuel_transfers',
            'fuel_consumption_rates',
            'daily_sessions',
            'shifts',
            'fuel_trucks',
            'fuel_storages',
            'units',
            'unit_types',
        ];

        foreach ($tables as $table) {
            DB::table($table)->truncate();
        }
    }

    private function seedUnitTypes(): void
    {
        $unitTypes = [
            [
                'type_code' => 'EXC',
                'type_name' => 'Excavator',
                'description' => 'Heavy duty excavators for mining operations',
                'default_consumption_per_hour' => 15.5,
                'default_consumption_per_km' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type_code' => 'DT',
                'type_name' => 'Dump Truck',
                'description' => 'Large dump trucks for material transportation',
                'default_consumption_per_hour' => 25.0,
                'default_consumption_per_km' => 2.5,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type_code' => 'LD',
                'type_name' => 'Loader',
                'description' => 'Front end loaders for material handling',
                'default_consumption_per_hour' => 18.0,
                'default_consumption_per_km' => 1.8,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type_code' => 'BD',
                'type_name' => 'Bulldozer',
                'description' => 'Bulldozers for earth moving and grading',
                'default_consumption_per_hour' => 22.0,
                'default_consumption_per_km' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type_code' => 'GR',
                'type_name' => 'Grader',
                'description' => 'Motor graders for road maintenance',
                'default_consumption_per_hour' => 12.0,
                'default_consumption_per_km' => 1.2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('unit_types')->insert($unitTypes);
    }

    private function seedUnits(): void
    {
        $units = [
            // Excavators
            ['unit_code' => 'EXC001', 'unit_name' => 'Excavator Komatsu PC400-1', 'unit_type_id' => 1, 'current_hour_meter' => 12847.5, 'current_odometer' => 0, 'brand' => 'Komatsu', 'model' => 'PC400', 'manufacture_year' => 2020, 'fuel_tank_capacity' => 450, 'is_active' => true],
            ['unit_code' => 'EXC002', 'unit_name' => 'Excavator CAT 336-1', 'unit_type_id' => 1, 'current_hour_meter' => 8956.2, 'current_odometer' => 0, 'brand' => 'Caterpillar', 'model' => '336', 'manufacture_year' => 2021, 'fuel_tank_capacity' => 400, 'is_active' => true],
            ['unit_code' => 'EXC003', 'unit_name' => 'Excavator Hitachi ZX470-1', 'unit_type_id' => 1, 'current_hour_meter' => 15634.8, 'current_odometer' => 0, 'brand' => 'Hitachi', 'model' => 'ZX470', 'manufacture_year' => 2019, 'fuel_tank_capacity' => 480, 'is_active' => true],
            
            // Dump Trucks
            ['unit_code' => 'DT001', 'unit_name' => 'Dump Truck CAT 777-1', 'unit_type_id' => 2, 'current_hour_meter' => 9845.3, 'current_odometer' => 125847.2, 'brand' => 'Caterpillar', 'model' => '777', 'manufacture_year' => 2020, 'fuel_tank_capacity' => 1200, 'is_active' => true],
            ['unit_code' => 'DT002', 'unit_name' => 'Dump Truck Komatsu HD605-1', 'unit_type_id' => 2, 'current_hour_meter' => 11256.7, 'current_odometer' => 98765.4, 'brand' => 'Komatsu', 'model' => 'HD605', 'manufacture_year' => 2021, 'fuel_tank_capacity' => 1100, 'is_active' => true],
            ['unit_code' => 'DT003', 'unit_name' => 'Dump Truck Volvo A40G-1', 'unit_type_id' => 2, 'current_hour_meter' => 7892.1, 'current_odometer' => 87456.3, 'brand' => 'Volvo', 'model' => 'A40G', 'manufacture_year' => 2022, 'fuel_tank_capacity' => 1000, 'is_active' => true],
            
            // Loaders
            ['unit_code' => 'LD001', 'unit_name' => 'Loader CAT 980M-1', 'unit_type_id' => 3, 'current_hour_meter' => 6754.9, 'current_odometer' => 45632.1, 'brand' => 'Caterpillar', 'model' => '980M', 'manufacture_year' => 2021, 'fuel_tank_capacity' => 600, 'is_active' => true],
            ['unit_code' => 'LD002', 'unit_name' => 'Loader Komatsu WA470-1', 'unit_type_id' => 3, 'current_hour_meter' => 8934.2, 'current_odometer' => 52341.8, 'brand' => 'Komatsu', 'model' => 'WA470', 'manufacture_year' => 2020, 'fuel_tank_capacity' => 550, 'is_active' => true],
            
            // Bulldozers
            ['unit_code' => 'BD001', 'unit_name' => 'Bulldozer CAT D8T-1', 'unit_type_id' => 4, 'current_hour_meter' => 11458.6, 'current_odometer' => 0, 'brand' => 'Caterpillar', 'model' => 'D8T', 'manufacture_year' => 2019, 'fuel_tank_capacity' => 750, 'is_active' => true],
            ['unit_code' => 'BD002', 'unit_name' => 'Bulldozer Komatsu D155AX-1', 'unit_type_id' => 4, 'current_hour_meter' => 9672.3, 'current_odometer' => 0, 'brand' => 'Komatsu', 'model' => 'D155AX', 'manufacture_year' => 2020, 'fuel_tank_capacity' => 800, 'is_active' => true],
            
            // Graders
            ['unit_code' => 'GR001', 'unit_name' => 'Grader CAT 140M-1', 'unit_type_id' => 5, 'current_hour_meter' => 5832.7, 'current_odometer' => 34567.9, 'brand' => 'Caterpillar', 'model' => '140M', 'manufacture_year' => 2021, 'fuel_tank_capacity' => 400, 'is_active' => true],
        ];

        foreach ($units as $unit) {
            $unit['created_at'] = now();
            $unit['updated_at'] = now();
            DB::table('units')->insert($unit);
        }
    }

    private function seedFuelStorages(): void
    {
        $storages = [
            [
                'storage_code' => 'ST001',
                'storage_name' => 'Main Tank Storage A',
                'capacity' => 50000.00,
                'current_level' => 32500.00,
                'minimum_level' => 5000.00,
                'location' => 'Workshop Area - North',
                'fuel_type' => 'Solar',
                'description' => 'Primary diesel storage tank for heavy equipment',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'storage_code' => 'ST002',
                'storage_name' => 'Secondary Tank Storage B',
                'capacity' => 30000.00,
                'current_level' => 18750.00,
                'minimum_level' => 3000.00,
                'location' => 'Workshop Area - South',
                'fuel_type' => 'Solar',
                'description' => 'Secondary diesel storage for backup supply',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'storage_code' => 'ST003',
                'storage_name' => 'Gasoline Storage Tank',
                'capacity' => 10000.00,
                'current_level' => 6500.00,
                'minimum_level' => 1000.00,
                'location' => 'Light Vehicle Area',
                'fuel_type' => 'Bensin',
                'description' => 'Gasoline storage for light vehicles and generators',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('fuel_storages')->insert($storages);
    }

    private function seedFuelTrucks(): void
    {
        $trucks = [
            [
                'truck_code' => 'FT001',
                'truck_name' => 'Fuel Truck Hino 1',
                'capacity' => 8000.00,
                'current_level' => 5200.00,
                'license_plate' => 'L 9001 AB',
                'driver_name' => 'Bambang Sutrisno',
                'brand' => 'Hino',
                'model' => '500 Series',
                'manufacture_year' => 2020,
                'fuel_type' => 'Solar',
                'notes' => 'Primary fuel delivery truck for mining area',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'truck_code' => 'FT002',
                'truck_name' => 'Fuel Truck Mitsubishi 1',
                'capacity' => 6000.00,
                'current_level' => 3800.00,
                'license_plate' => 'L 9002 CD',
                'driver_name' => 'Suharto Wijaya',
                'brand' => 'Mitsubishi',
                'model' => 'Fuso Fighter',
                'manufacture_year' => 2021,
                'fuel_type' => 'Solar',
                'notes' => 'Secondary fuel delivery truck',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'truck_code' => 'FT003',
                'truck_name' => 'Fuel Truck Isuzu 1',
                'capacity' => 5000.00,
                'current_level' => 2100.00,
                'license_plate' => 'L 9003 EF',
                'driver_name' => 'Ahmad Supriadi',
                'brand' => 'Isuzu',
                'model' => 'Giga',
                'manufacture_year' => 2019,
                'fuel_type' => 'Solar',
                'notes' => 'Backup fuel delivery truck',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('fuel_trucks')->insert($trucks);
    }

    private function seedShifts(): void
    {
        $shifts = [
            [
                'shift_code' => 'PAGI',
                'shift_name' => 'Shift Pagi',
                'start_time' => '07:00:00',
                'end_time' => '15:00:00',
                'description' => 'Shift pagi 07:00 - 15:00',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'shift_code' => 'SIANG',
                'shift_name' => 'Shift Siang',
                'start_time' => '15:00:00',
                'end_time' => '23:00:00',
                'description' => 'Shift siang 15:00 - 23:00',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'shift_code' => 'MALAM',
                'shift_name' => 'Shift Malam',
                'start_time' => '23:00:00',
                'end_time' => '07:00:00',
                'description' => 'Shift malam 23:00 - 07:00',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('shifts')->insert($shifts);
    }

    private function seedDailySessions(): void
    {
        $sessions = [];
        
        // Generate sessions for last 30 days
        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            
            // Morning shift
            $sessions[] = [
                'session_date' => $date->toDateString(),
                'shift_id' => 1,
                'session_name' => $date->format('Y-m-d') . ' Pagi',
                'start_datetime' => $date->copy()->setTime(7, 0, 0),
                'end_datetime' => $date->copy()->setTime(15, 0, 0),
                'status' => $i > 0 ? 'Closed' : 'Active',
                'notes' => 'Normal operations',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            
            // Afternoon shift
            $sessions[] = [
                'session_date' => $date->toDateString(),
                'shift_id' => 2,
                'session_name' => $date->format('Y-m-d') . ' Siang',
                'start_datetime' => $date->copy()->setTime(15, 0, 0),
                'end_datetime' => $date->copy()->setTime(23, 0, 0),
                'status' => $i > 0 ? 'Closed' : 'Active',
                'notes' => 'Normal operations',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            
            // Night shift
            $sessions[] = [
                'session_date' => $date->toDateString(),
                'shift_id' => 3,
                'session_name' => $date->format('Y-m-d') . ' Malam',
                'start_datetime' => $date->copy()->setTime(23, 0, 0),
                'end_datetime' => $date->copy()->addDay()->setTime(7, 0, 0),
                'status' => $i > 0 ? 'Closed' : 'Active',
                'notes' => 'Normal operations',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('daily_sessions')->insert($sessions);
    }

    private function seedFuelConsumptionRates(): void
    {
        $rates = [
            // Excavator rates
            ['unit_type_id' => 1, 'consumption_per_hour' => 14.5, 'consumption_per_km' => 0, 'effective_from' => '2025-01-01', 'work_condition' => 'Light', 'rate_source' => 'Field Test', 'is_active' => true, 'created_by' => 'System'],
            ['unit_type_id' => 1, 'consumption_per_hour' => 15.5, 'consumption_per_km' => 0, 'effective_from' => '2025-01-01', 'work_condition' => 'Normal', 'rate_source' => 'Field Test', 'is_active' => true, 'created_by' => 'System'],
            ['unit_type_id' => 1, 'consumption_per_hour' => 17.2, 'consumption_per_km' => 0, 'effective_from' => '2025-01-01', 'work_condition' => 'Heavy', 'rate_source' => 'Field Test', 'is_active' => true, 'created_by' => 'System'],
            
            // Dump Truck rates
            ['unit_type_id' => 2, 'consumption_per_hour' => 22.0, 'consumption_per_km' => 2.2, 'effective_from' => '2025-01-01', 'work_condition' => 'Light', 'rate_source' => 'Manufacturer', 'is_active' => true, 'created_by' => 'System'],
            ['unit_type_id' => 2, 'consumption_per_hour' => 25.0, 'consumption_per_km' => 2.5, 'effective_from' => '2025-01-01', 'work_condition' => 'Normal', 'rate_source' => 'Manufacturer', 'is_active' => true, 'created_by' => 'System'],
            ['unit_type_id' => 2, 'consumption_per_hour' => 28.5, 'consumption_per_km' => 3.0, 'effective_from' => '2025-01-01', 'work_condition' => 'Heavy', 'rate_source' => 'Field Test', 'is_active' => true, 'created_by' => 'System'],
            
            // Loader rates
            ['unit_type_id' => 3, 'consumption_per_hour' => 16.0, 'consumption_per_km' => 1.6, 'effective_from' => '2025-01-01', 'work_condition' => 'Light', 'rate_source' => 'Manufacturer', 'is_active' => true, 'created_by' => 'System'],
            ['unit_type_id' => 3, 'consumption_per_hour' => 18.0, 'consumption_per_km' => 1.8, 'effective_from' => '2025-01-01', 'work_condition' => 'Normal', 'rate_source' => 'Manufacturer', 'is_active' => true, 'created_by' => 'System'],
            ['unit_type_id' => 3, 'consumption_per_hour' => 20.5, 'consumption_per_km' => 2.1, 'effective_from' => '2025-01-01', 'work_condition' => 'Heavy', 'rate_source' => 'Field Test', 'is_active' => true, 'created_by' => 'System'],
            
            // Bulldozer rates
            ['unit_type_id' => 4, 'consumption_per_hour' => 20.0, 'consumption_per_km' => 0, 'effective_from' => '2025-01-01', 'work_condition' => 'Light', 'rate_source' => 'Manufacturer', 'is_active' => true, 'created_by' => 'System'],
            ['unit_type_id' => 4, 'consumption_per_hour' => 22.0, 'consumption_per_km' => 0, 'effective_from' => '2025-01-01', 'work_condition' => 'Normal', 'rate_source' => 'Manufacturer', 'is_active' => true, 'created_by' => 'System'],
            ['unit_type_id' => 4, 'consumption_per_hour' => 25.5, 'consumption_per_km' => 0, 'effective_from' => '2025-01-01', 'work_condition' => 'Heavy', 'rate_source' => 'Field Test', 'is_active' => true, 'created_by' => 'System'],
            
            // Grader rates
            ['unit_type_id' => 5, 'consumption_per_hour' => 10.5, 'consumption_per_km' => 1.0, 'effective_from' => '2025-01-01', 'work_condition' => 'Light', 'rate_source' => 'Manufacturer', 'is_active' => true, 'created_by' => 'System'],
            ['unit_type_id' => 5, 'consumption_per_hour' => 12.0, 'consumption_per_km' => 1.2, 'effective_from' => '2025-01-01', 'work_condition' => 'Normal', 'rate_source' => 'Manufacturer', 'is_active' => true, 'created_by' => 'System'],
            ['unit_type_id' => 5, 'consumption_per_hour' => 14.0, 'consumption_per_km' => 1.4, 'effective_from' => '2025-01-01', 'work_condition' => 'Heavy', 'rate_source' => 'Field Test', 'is_active' => true, 'created_by' => 'System'],
        ];

        foreach ($rates as $rate) {
            $rate['created_at'] = now();
            $rate['updated_at'] = now();
            DB::table('fuel_consumption_rates')->insert($rate);
        }
    }

    private function seedFuelTransfers(): void
    {
        $transfers = [];
        $transferNumber = 1;
        
        // Generate transfers for last 30 days
        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            
            // 2-3 transfers per day
            $dailyTransfers = rand(2, 3);
            
            for ($j = 0; $j < $dailyTransfers; $j++) {
                $sessionId = (($i * 3) + $j % 3) + 1; // Distribute across sessions
                $storageId = rand(1, 2); // Main storages only
                $truckId = rand(1, 3);
                $amount = rand(3000, 5000);
                
                $transfers[] = [
                    'transfer_number' => 'TRF' . str_pad($transferNumber++, 6, '0', STR_PAD_LEFT),
                    'fuel_storage_id' => $storageId,
                    'fuel_truck_id' => $truckId,
                    'daily_session_id' => $sessionId,
                    'transferred_amount' => $amount,
                    'storage_level_before' => rand(20000, 45000),
                    'storage_level_after' => rand(15000, 40000),
                    'truck_level_before' => rand(1000, 3000),
                    'truck_level_after' => rand(4000, 6000),
                    'transfer_datetime' => $date->copy()->addHours(rand(8, 16)),
                    'operator_name' => ['Budi Santoso', 'Agus Wibowo', 'Eko Prasetyo'][rand(0, 2)],
                    'notes' => 'Regular fuel transfer operation',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::table('fuel_transfers')->insert($transfers);
    }

    private function seedFuelTransactions(): void
    {
        $transactions = [];
        $transactionNumber = 1;
        $operators = ['Supardi', 'Wahyu', 'Joko', 'Rudi', 'Andi', 'Budi', 'Hendra', 'Yanto'];
        
        // Generate transactions for last 30 days
        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            
            // 15-25 transactions per day
            $dailyTransactions = rand(15, 25);
            
            for ($j = 0; $j < $dailyTransactions; $j++) {
                $unitId = rand(1, 12);
                $sessionId = (($i * 3) + $j % 3) + 1;
                
                // Polymorphic source (70% from storage, 30% from truck)
                if (rand(1, 100) <= 70) {
                    $sourceType = 'App\\Models\\FuelStorage';
                    $sourceId = rand(1, 3);
                } else {
                    $sourceType = 'App\\Models\\FuelTruck';
                    $sourceId = rand(1, 3);
                }
                
                // Generate realistic hour meter and odometer
                $prevHourMeter = rand(5000, 15000) + ($j * 0.5);
                $currentHourMeter = $prevHourMeter + rand(5, 12) / 10; // 0.5 - 1.2 hours
                $hourDiff = $currentHourMeter - $prevHourMeter;
                
                // For units with odometer (trucks, loaders, graders)
                $hasOdometer = in_array($unitId, [4, 5, 6, 7, 8, 12]); // DT and LD and GR
                if ($hasOdometer) {
                    $prevOdometer = rand(30000, 120000) + ($j * 5);
                    $currentOdometer = $prevOdometer + rand(5, 25); // 5-25 km
                } else {
                    $prevOdometer = 0;
                    $currentOdometer = 0;
                }
                $odosDiff = $currentOdometer - $prevOdometer;
                
                $fuelAmount = rand(150, 800);
                
                // Calculate efficiency
                $efficiencyPerHour = $hourDiff > 0 ? $fuelAmount / $hourDiff : null;
                $efficiencyPerKm = $odosDiff > 0 ? $fuelAmount / $odosDiff : null;
                $combinedEfficiency = $efficiencyPerHour;
                
                $transactions[] = [
                    'transaction_number' => 'TXN' . str_pad($transactionNumber++, 6, '0', STR_PAD_LEFT),
                    'unit_id' => $unitId,
                    'daily_session_id' => $sessionId,
                    'fuel_source_type' => $sourceType,
                    'fuel_source_id' => $sourceId,
                    'previous_hour_meter' => $prevHourMeter,
                    'current_hour_meter' => $currentHourMeter,
                    'previous_odometer' => $prevOdometer,
                    'current_odometer' => $currentOdometer,
                    'fuel_amount' => $fuelAmount,
                    'source_level_before' => rand(5000, 30000),
                    'source_level_after' => rand(4000, 25000),
                    'fuel_efficiency_per_hour' => $efficiencyPerHour,
                    'fuel_efficiency_per_km' => $efficiencyPerKm,
                    'combined_efficiency' => $combinedEfficiency,
                    'transaction_datetime' => $date->copy()->addHours(rand(7, 22))->addMinutes(rand(0, 59)),
                    'operator_name' => $operators[array_rand($operators)],
                    'notes' => 'Regular fuel consumption',
                    'calculated_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::table('fuel_transactions')->insert($transactions);
    }

    private function seedPhysicalStockChecks(): void
    {
        $checks = [];
        $checkNumber = 1;
        $checkers = ['Supervisor A', 'Supervisor B', 'Inspector C'];
        
        // Generate checks for last 30 days (2-3 checks per day)
        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            
            $dailyChecks = rand(2, 3);
            
            for ($j = 0; $j < $dailyChecks; $j++) {
                // Randomly check storage or truck
                if (rand(1, 100) <= 60) {
                    $checkableType = 'App\\Models\\FuelStorage';
                    $checkableId = rand(1, 3);
                } else {
                    $checkableType = 'App\\Models\\FuelTruck';
                    $checkableId = rand(1, 3);
                }
                
                $systemLevel = rand(5000, 35000);
                $variance = rand(-500, 500); // -500L to +500L variance
                $physicalLevel = $systemLevel + $variance;
                
                // Determine variance status
                $variancePercentage = abs($variance / $systemLevel * 100);
                if ($variancePercentage <= 2) {
                    $status = 'Normal';
                } elseif ($variancePercentage <= 5) {
                    $status = 'Warning';
                } else {
                    $status = 'Critical';
                }
                
                $checks[] = [
                    'check_number' => 'CHK' . str_pad($checkNumber++, 6, '0', STR_PAD_LEFT),
                    'checkable_type' => $checkableType,
                    'checkable_id' => $checkableId,
                    'check_date' => $date->toDateString(),
                    'check_time' => sprintf('%02d:%02d:00', rand(8, 17), rand(0, 59)),
                    'system_level' => $systemLevel,
                    'physical_level' => $physicalLevel,
                    'checker_name' => $checkers[array_rand($checkers)],
                    'check_method' => ['Dipstick', 'Gauge', 'Flow Meter'][rand(0, 2)],
                    'variance_status' => $status,
                    'notes' => $status === 'Normal' ? 'Stock level normal' : 'Variance detected - investigating',
                    'corrective_action' => $status !== 'Normal' ? 'System level adjusted after investigation' : null,
                    'system_adjusted' => $status !== 'Normal' ? true : false,
                    'adjustment_amount' => $status !== 'Normal' ? $variance : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::table('physical_stock_checks')->insert($checks);
    }

    private function seedUnitConsumptionSummaries(): void
    {
        $summaries = [];
        
        // Generate summaries for last 15 days for each unit
        for ($i = 14; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            
            for ($unitId = 1; $unitId <= 12; $unitId++) {
                // Daily summary
                $totalTransactions = rand(1, 3);
                $totalFuel = rand(200, 1200);
                $totalHourDiff = rand(5, 15) / 10; // 0.5 - 1.5 hours
                $totalOdoDiff = in_array($unitId, [4, 5, 6, 7, 8, 12]) ? rand(10, 50) : 0;
                
                $summaries[] = [
                    'unit_id' => $unitId,
                    'summary_date' => $date->toDateString(),
                    'shift_id' => null,
                    'total_transactions' => $totalTransactions,
                    'total_fuel_consumed' => $totalFuel,
                    'total_hour_meter_diff' => $totalHourDiff,
                    'total_odometer_diff' => $totalOdoDiff,
                    'avg_fuel_per_hour' => $totalHourDiff > 0 ? $totalFuel / $totalHourDiff : null,
                    'avg_fuel_per_km' => $totalOdoDiff > 0 ? $totalFuel / $totalOdoDiff : null,
                    'avg_combined_efficiency' => $totalHourDiff > 0 ? $totalFuel / $totalHourDiff : null,
                    'min_efficiency_per_hour' => $totalHourDiff > 0 ? ($totalFuel / $totalHourDiff) * 0.8 : null,
                    'max_efficiency_per_hour' => $totalHourDiff > 0 ? ($totalFuel / $totalHourDiff) * 1.2 : null,
                    'min_efficiency_per_km' => $totalOdoDiff > 0 ? ($totalFuel / $totalOdoDiff) * 0.8 : null,
                    'max_efficiency_per_km' => $totalOdoDiff > 0 ? ($totalFuel / $totalOdoDiff) * 1.2 : null,
                    'period_type' => 'Daily',
                    'first_transaction_at' => $date->copy()->addHours(rand(8, 10)),
                    'last_transaction_at' => $date->copy()->addHours(rand(15, 17)),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                
                // Shift summaries (random for some days)
                if (rand(1, 100) <= 70) { // 70% chance of shift data
                    for ($shiftId = 1; $shiftId <= 3; $shiftId++) {
                        $shiftTransactions = rand(0, 2);
                        if ($shiftTransactions > 0) {
                            $shiftFuel = rand(50, 400);
                            $shiftHourDiff = rand(2, 8) / 10;
                            $shiftOdoDiff = in_array($unitId, [4, 5, 6, 7, 8, 12]) ? rand(5, 20) : 0;
                            
                            $summaries[] = [
                                'unit_id' => $unitId,
                                'summary_date' => $date->toDateString(),
                                'shift_id' => $shiftId,
                                'total_transactions' => $shiftTransactions,
                                'total_fuel_consumed' => $shiftFuel,
                                'total_hour_meter_diff' => $shiftHourDiff,
                                'total_odometer_diff' => $shiftOdoDiff,
                                'avg_fuel_per_hour' => $shiftHourDiff > 0 ? $shiftFuel / $shiftHourDiff : null,
                                'avg_fuel_per_km' => $shiftOdoDiff > 0 ? $shiftFuel / $shiftOdoDiff : null,
                                'avg_combined_efficiency' => $shiftHourDiff > 0 ? $shiftFuel / $shiftHourDiff : null,
                                'min_efficiency_per_hour' => $shiftHourDiff > 0 ? ($shiftFuel / $shiftHourDiff) * 0.9 : null,
                                'max_efficiency_per_hour' => $shiftHourDiff > 0 ? ($shiftFuel / $shiftHourDiff) * 1.1 : null,
                                'min_efficiency_per_km' => $shiftOdoDiff > 0 ? ($shiftFuel / $shiftOdoDiff) * 0.9 : null,
                                'max_efficiency_per_km' => $shiftOdoDiff > 0 ? ($shiftFuel / $shiftOdoDiff) * 1.1 : null,
                                'period_type' => 'Shift',
                                'first_transaction_at' => $this->getShiftStartTime($date, $shiftId),
                                'last_transaction_at' => $this->getShiftEndTime($date, $shiftId),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                    }
                }
            }
        }

        DB::table('unit_consumption_summaries')->insert($summaries);
    }

    private function getShiftStartTime($date, $shiftId): Carbon
    {
        return match($shiftId) {
            1 => $date->copy()->setTime(7, 0),  // Pagi
            2 => $date->copy()->setTime(15, 0), // Siang  
            3 => $date->copy()->setTime(23, 0), // Malam
            default => $date->copy()->setTime(7, 0)
        };
    }

    private function getShiftEndTime($date, $shiftId): Carbon
    {
        return match($shiftId) {
            1 => $date->copy()->setTime(15, 0),  // Pagi
            2 => $date->copy()->setTime(23, 0),  // Siang
            3 => $date->copy()->addDay()->setTime(7, 0), // Malam
            default => $date->copy()->setTime(15, 0)
        };
    }

    private function seedVarianceReports(): void
    {
        $reports = [];
        $reportNumber = 1;
        
        // Generate weekly reports for last 4 weeks
        for ($week = 3; $week >= 0; $week--) {
            $endDate = Carbon::now()->subWeeks($week)->endOfWeek();
            $startDate = $endDate->copy()->startOfWeek();
            
            $totalSystemFuel = rand(150000, 200000);
            $totalVariance = rand(-2000, 2000);
            $totalPhysicalFuel = $totalSystemFuel + $totalVariance;
            
            $reports[] = [
                'report_number' => 'VAR' . str_pad($reportNumber++, 6, '0', STR_PAD_LEFT),
                'report_date' => $endDate->toDateString(),
                'report_type' => 'Weekly',
                'period_start' => $startDate->toDateString(),
                'period_end' => $endDate->toDateString(),
                'total_system_fuel' => $totalSystemFuel,
                'total_physical_fuel' => $totalPhysicalFuel,
                'storage_variance' => rand(-1000, 1000),
                'truck_variance' => rand(-500, 500),
                'total_checks_performed' => rand(12, 18),
                'critical_variances_count' => rand(0, 2),
                'report_status' => $week > 0 ? 'Final' : 'Draft',
                'summary_notes' => 'Weekly variance analysis completed. Overall variance within acceptable limits.',
                'recommended_actions' => abs($totalVariance) > 1000 ? 'Investigate high variance sources and adjust monitoring frequency.' : 'Continue normal monitoring procedures.',
                'prepared_by' => 'System Analyst',
                'reviewed_by' => $week > 0 ? 'Fuel Supervisor' : null,
                'approved_by' => $week > 1 ? 'Operations Manager' : null,
                'approved_at' => $week > 1 ? $endDate->copy()->addDays(2) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        
        // Generate monthly reports for last 2 months
        for ($month = 1; $month >= 0; $month--) {
            $endDate = Carbon::now()->subMonths($month)->endOfMonth();
            $startDate = $endDate->copy()->startOfMonth();
            
            $totalSystemFuel = rand(600000, 800000);
            $totalVariance = rand(-5000, 5000);
            $totalPhysicalFuel = $totalSystemFuel + $totalVariance;
            
            $reports[] = [
                'report_number' => 'VAR' . str_pad($reportNumber++, 6, '0', STR_PAD_LEFT),
                'report_date' => $endDate->toDateString(),
                'report_type' => 'Monthly',
                'period_start' => $startDate->toDateString(),
                'period_end' => $endDate->toDateString(),
                'total_system_fuel' => $totalSystemFuel,
                'total_physical_fuel' => $totalPhysicalFuel,
                'storage_variance' => rand(-3000, 3000),
                'truck_variance' => rand(-2000, 2000),
                'total_checks_performed' => rand(50, 70),
                'critical_variances_count' => rand(2, 8),
                'report_status' => $month > 0 ? 'Approved' : 'Final',
                'summary_notes' => 'Monthly comprehensive variance analysis. Detailed investigation of all significant variances completed.',
                'recommended_actions' => 'Implement enhanced monitoring procedures for high-variance sources. Review calibration of measurement equipment.',
                'prepared_by' => 'Senior Analyst',
                'reviewed_by' => 'Fuel Supervisor',
                'approved_by' => $month > 0 ? 'Operations Manager' : 'Fuel Supervisor',
                'approved_at' => $month > 0 ? $endDate->copy()->addDays(5) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('variance_reports')->insert($reports);
    }
}