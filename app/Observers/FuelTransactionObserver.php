<?php

namespace App\Observers;

use App\Models\FuelTransaction;
use App\Models\UnitConsumptionSummary;
use Illuminate\Support\Facades\Log;

class FuelTransactionObserver
{
    /**
     * Handle the FuelTransaction "creating" event.
     */
    public function creating(FuelTransaction $fuelTransaction): void
    {
        // Generate transaction number if not set
        if (empty($fuelTransaction->transaction_number)) {
            $fuelTransaction->transaction_number = $fuelTransaction->generateTransactionNumber();
        }
        
        // Set source levels before transaction
        if ($fuelTransaction->fuelSource) {
            $fuelTransaction->source_level_before = $fuelTransaction->fuelSource->current_level;
        }
    }

    /**
     * Handle the FuelTransaction "created" event.
     */
    public function created(FuelTransaction $fuelTransaction): void
    {
        try {
            // 1. Calculate consumption efficiency
            $fuelTransaction->calculateEfficiency();
            
            // 2. Update unit meters
            $this->updateUnitMeters($fuelTransaction);
            
            // 3. Update fuel source level
            $this->updateFuelSourceLevel($fuelTransaction);
            
            // 4. Update/create consumption summary
            $this->updateConsumptionSummary($fuelTransaction);
            
            Log::info('Fuel transaction processed successfully', [
                'transaction_id' => $fuelTransaction->id,
                'unit_code' => $fuelTransaction->unit->unit_code,
                'fuel_amount' => $fuelTransaction->fuel_amount
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error processing fuel transaction', [
                'transaction_id' => $fuelTransaction->id,
                'error' => $e->getMessage()
            ]);
            
            // Re-throw to prevent inconsistent state
            throw $e;
        }
    }

    /**
     * Handle the FuelTransaction "updated" event.
     */
    public function updated(FuelTransaction $fuelTransaction): void
    {
        // Only recalculate if relevant fields changed
        if ($fuelTransaction->wasChanged([
            'fuel_amount', 'current_hour_meter', 'current_odometer'
        ])) {
            try {
                // Recalculate efficiency with new values
                $fuelTransaction->calculateEfficiency();
                
                // Update unit meters
                $this->updateUnitMeters($fuelTransaction);
                
                // Recalculate consumption summary
                $this->updateConsumptionSummary($fuelTransaction);
                
            } catch (\Exception $e) {
                Log::error('Error updating fuel transaction', [
                    'transaction_id' => $fuelTransaction->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Handle the FuelTransaction "deleted" event.
     */
    public function deleted(FuelTransaction $fuelTransaction): void
    {
        try {
            // Rollback fuel source level
            $this->rollbackFuelSourceLevel($fuelTransaction);
            
            // Update consumption summary (remove this transaction)
            $this->updateConsumptionSummary($fuelTransaction, true);
            
            Log::info('Fuel transaction deleted and rolled back', [
                'transaction_id' => $fuelTransaction->id
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error rolling back fuel transaction', [
                'transaction_id' => $fuelTransaction->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Update unit's current hour meter and odometer
     */
    private function updateUnitMeters(FuelTransaction $fuelTransaction): void
    {
        $fuelTransaction->unit->updateMeters(
            $fuelTransaction->current_hour_meter,
            $fuelTransaction->current_odometer
        );
    }

    /**
     * Update fuel source level after transaction
     */
    private function updateFuelSourceLevel(FuelTransaction $fuelTransaction): void
    {
        $fuelSource = $fuelTransaction->fuelSource;
        
        if (!$fuelSource) {
            return;
        }

        // Validate sufficient fuel
        if ($fuelSource->current_level < $fuelTransaction->fuel_amount) {
            throw new \Exception("Insufficient fuel in {$fuelSource->display_name}. Available: {$fuelSource->current_level}L, Required: {$fuelTransaction->fuel_amount}L");
        }

        // Remove fuel from source
        $success = $fuelSource->removeFuel($fuelTransaction->fuel_amount);
        
        if (!$success) {
            throw new \Exception("Failed to update fuel level for {$fuelSource->display_name}");
        }

        // Update after level in transaction
        $fuelTransaction->source_level_after = $fuelSource->current_level;
        $fuelTransaction->save();
    }

    /**
     * Rollback fuel source level when transaction is deleted
     */
    private function rollbackFuelSourceLevel(FuelTransaction $fuelTransaction): void
    {
        $fuelSource = $fuelTransaction->fuelSource;
        
        if (!$fuelSource) {
            return;
        }

        // Add fuel back to source
        $fuelSource->addFuel($fuelTransaction->fuel_amount);
    }

    /**
     * Update or create consumption summary for the unit/date/shift
     */
    private function updateConsumptionSummary(FuelTransaction $fuelTransaction, bool $isDeleting = false): void
    {
        $unit = $fuelTransaction->unit;
        $session = $fuelTransaction->dailySession;
        $summaryDate = $fuelTransaction->transaction_datetime->toDateString();

        // Daily summary
        $dailySummary = UnitConsumptionSummary::where('unit_id', $unit->id)
            ->where('summary_date', $summaryDate)
            ->where('period_type', 'Daily')
            ->whereNull('shift_id')
            ->first();

        if ($dailySummary) {
            $dailySummary->updateFromTransactions();
        } else if (!$isDeleting) {
            $dailySummary = UnitConsumptionSummary::generateForUnit($unit, $summaryDate);
            if ($dailySummary->total_transactions > 0) {
                $dailySummary->save();
            }
        }

        // Shift summary (if session exists)
        if ($session && $session->shift) {
            $shiftSummary = UnitConsumptionSummary::where('unit_id', $unit->id)
                ->where('summary_date', $summaryDate)
                ->where('period_type', 'Shift')
                ->where('shift_id', $session->shift_id)
                ->first();

            if ($shiftSummary) {
                $shiftSummary->updateFromTransactions();
            } else if (!$isDeleting) {
                $shiftSummary = UnitConsumptionSummary::generateForUnit($unit, $summaryDate, $session->shift);
                if ($shiftSummary->total_transactions > 0) {
                    $shiftSummary->save();
                }
            }
        }
    }

    /**
     * Validate transaction before processing
     */
    private function validateTransaction(FuelTransaction $fuelTransaction): void
    {
        // Validate hour meter progression
        if ($fuelTransaction->current_hour_meter < $fuelTransaction->previous_hour_meter) {
            throw new \Exception("Current hour meter ({$fuelTransaction->current_hour_meter}) cannot be less than previous ({$fuelTransaction->previous_hour_meter})");
        }

        // Validate odometer progression
        if ($fuelTransaction->current_odometer < $fuelTransaction->previous_odometer) {
            throw new \Exception("Current odometer ({$fuelTransaction->current_odometer}) cannot be less than previous ({$fuelTransaction->previous_odometer})");
        }

        // Validate fuel amount
        if ($fuelTransaction->fuel_amount <= 0) {
            throw new \Exception("Fuel amount must be greater than 0");
        }

        // Check if consumption is reasonable (optional warning)
        if (!$fuelTransaction->isReasonableConsumption()) {
            Log::warning('Unusual fuel consumption detected', [
                'transaction_id' => $fuelTransaction->id,
                'unit_code' => $fuelTransaction->unit->unit_code,
                'fuel_amount' => $fuelTransaction->fuel_amount,
                'hour_diff' => $fuelTransaction->getHourMeterDiff(),
                'km_diff' => $fuelTransaction->getOdometerDiff(),
            ]);
        }
    }

    /**
     * Handle the FuelTransaction "saving" event.
     */
    public function saving(FuelTransaction $fuelTransaction): void
    {
        $this->validateTransaction($fuelTransaction);
    }
}