<?php

namespace App\Observers;

use App\Models\FuelTransfer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class FuelTransferObserver
{
    /**
     * Handle the FuelTransfer "creating" event.
     */
    public function creating(FuelTransfer $fuelTransfer): void
    {
        // Generate transfer number if not set
        if (empty($fuelTransfer->transfer_number)) {
            $fuelTransfer->transfer_number = $fuelTransfer->generateTransferNumber();
        }
        
        // Set before levels
        if ($fuelTransfer->fuelStorage) {
            $fuelTransfer->storage_level_before = $fuelTransfer->fuelStorage->current_level;
        }
        
        if ($fuelTransfer->fuelTruck) {
            $fuelTransfer->truck_level_before = $fuelTransfer->fuelTruck->current_level;
        }
    }

    /**
     * Handle the FuelTransfer "created" event.
     */
    public function created(FuelTransfer $fuelTransfer): void
    {
        DB::transaction(function () use ($fuelTransfer) {
            try {
                // Validate transfer before processing
                $this->validateTransfer($fuelTransfer);
                
                // 1. Update storage level (decrease)
                $this->updateStorageLevel($fuelTransfer);
                
                // 2. Update truck level (increase)
                $this->updateTruckLevel($fuelTransfer);
                
                // 3. Update after levels in transfer record
                $this->updateAfterLevels($fuelTransfer);
                
                Log::info('Fuel transfer processed successfully', [
                    'transfer_id' => $fuelTransfer->id,
                    'storage' => $fuelTransfer->fuelStorage->storage_code,
                    'truck' => $fuelTransfer->fuelTruck->truck_code,
                    'amount' => $fuelTransfer->transferred_amount
                ]);
                
            } catch (\Exception $e) {
                Log::error('Error processing fuel transfer', [
                    'transfer_id' => $fuelTransfer->id,
                    'error' => $e->getMessage()
                ]);
                
                throw $e; // Re-throw to rollback transaction
            }
        });
    }

    /**
     * Handle the FuelTransfer "updated" event.
     */
    public function updated(FuelTransfer $fuelTransfer): void
    {
        // Only recalculate if transfer amount changed
        if ($fuelTransfer->wasChanged('transferred_amount')) {
            DB::transaction(function () use ($fuelTransfer) {
                try {
                    // Get original amount to calculate difference
                    $originalAmount = $fuelTransfer->getOriginal('transferred_amount');
                    $newAmount = $fuelTransfer->transferred_amount;
                    $difference = $newAmount - $originalAmount;
                    
                    // Update storage level
                    $storage = $fuelTransfer->fuelStorage;
                    if ($difference > 0) {
                        // Need to remove more fuel from storage
                        if (!$storage->removeFuel($difference)) {
                            throw new \Exception("Insufficient fuel in storage for updated transfer amount");
                        }
                    } else {
                        // Add fuel back to storage
                        $storage->addFuel(abs($difference));
                    }
                    
                    // Update truck level
                    $truck = $fuelTransfer->fuelTruck;
                    if ($difference > 0) {
                        // Add more fuel to truck
                        if (!$truck->addFuel($difference)) {
                            throw new \Exception("Truck capacity exceeded for updated transfer amount");
                        }
                    } else {
                        // Remove fuel from truck
                        if (!$truck->removeFuel(abs($difference))) {
                            throw new \Exception("Insufficient fuel in truck to reduce transfer amount");
                        }
                    }
                    
                    // Update after levels
                    $this->updateAfterLevels($fuelTransfer);
                    
                } catch (\Exception $e) {
                    Log::error('Error updating fuel transfer', [
                        'transfer_id' => $fuelTransfer->id,
                        'error' => $e->getMessage()
                    ]);
                    
                    throw $e;
                }
            });
        }
    }

    /**
     * Handle the FuelTransfer "deleted" event.
     */
    public function deleted(FuelTransfer $fuelTransfer): void
    {
        DB::transaction(function () use ($fuelTransfer) {
            try {
                // Rollback storage level (add fuel back)
                $fuelTransfer->fuelStorage->addFuel($fuelTransfer->transferred_amount);
                
                // Rollback truck level (remove fuel)
                $fuelTransfer->fuelTruck->removeFuel($fuelTransfer->transferred_amount);
                
                Log::info('Fuel transfer deleted and rolled back', [
                    'transfer_id' => $fuelTransfer->id,
                    'amount_restored' => $fuelTransfer->transferred_amount
                ]);
                
            } catch (\Exception $e) {
                Log::error('Error rolling back fuel transfer', [
                    'transfer_id' => $fuelTransfer->id,
                    'error' => $e->getMessage()
                ]);
                
                throw $e;
            }
        });
    }

    /**
     * Validate transfer before processing
     */
    private function validateTransfer(FuelTransfer $fuelTransfer): void
    {
        $storage = $fuelTransfer->fuelStorage;
        $truck = $fuelTransfer->fuelTruck;
        $amount = $fuelTransfer->transferred_amount;

        // Validate transfer amount
        if ($amount <= 0) {
            throw new \Exception("Transfer amount must be greater than 0");
        }

        // Validate storage has sufficient fuel
        if ($storage->current_level < $amount) {
            throw new \Exception("Insufficient fuel in storage {$storage->storage_code}. Available: {$storage->current_level}L, Required: {$amount}L");
        }

        // Validate truck has sufficient capacity
        $availableCapacity = $truck->getRemainingCapacity();
        if ($availableCapacity < $amount) {
            throw new \Exception("Insufficient truck capacity in {$truck->truck_code}. Available: {$availableCapacity}L, Required: {$amount}L");
        }

        // Validate fuel types match (if specified)
        if ($storage->fuel_type !== $truck->fuel_type) {
            Log::warning('Fuel type mismatch in transfer', [
                'storage_type' => $storage->fuel_type,
                'truck_type' => $truck->fuel_type,
                'transfer_id' => $fuelTransfer->id
            ]);
        }

        // Check if truck is active
        if (!$truck->is_active) {
            throw new \Exception("Cannot transfer to inactive truck {$truck->truck_code}");
        }

        // Check if storage is active
        if (!$storage->is_active) {
            throw new \Exception("Cannot transfer from inactive storage {$storage->storage_code}");
        }
    }

    /**
     * Update storage fuel level (decrease)
     */
    private function updateStorageLevel(FuelTransfer $fuelTransfer): void
    {
        $storage = $fuelTransfer->fuelStorage;
        $amount = $fuelTransfer->transferred_amount;

        $success = $storage->removeFuel($amount, "Transfer to {$fuelTransfer->fuelTruck->truck_code}");
        
        if (!$success) {
            throw new \Exception("Failed to update storage level for {$storage->storage_code}");
        }

        // Check for low level warning
        if ($storage->isLowLevel()) {
            Log::warning('Storage low level after transfer', [
                'storage_code' => $storage->storage_code,
                'current_level' => $storage->current_level,
                'minimum_level' => $storage->minimum_level
            ]);
        }
    }

    /**
     * Update truck fuel level (increase)
     */
    private function updateTruckLevel(FuelTransfer $fuelTransfer): void
    {
        $truck = $fuelTransfer->fuelTruck;
        $amount = $fuelTransfer->transferred_amount;

        $success = $truck->addFuel($amount, "Transfer from {$fuelTransfer->fuelStorage->storage_code}");
        
        if (!$success) {
            throw new \Exception("Failed to update truck level for {$truck->truck_code}");
        }
    }

    /**
     * Update after levels in the transfer record
     */
    private function updateAfterLevels(FuelTransfer $fuelTransfer): void
    {
        $fuelTransfer->storage_level_after = $fuelTransfer->fuelStorage->current_level;
        $fuelTransfer->truck_level_after = $fuelTransfer->fuelTruck->current_level;
        $fuelTransfer->save();
    }

    /**
     * Handle the FuelTransfer "saving" event.
     */
    public function saving(FuelTransfer $fuelTransfer): void
    {
        // Additional validation before saving
        if ($fuelTransfer->exists) {
            return; // Skip validation for updates (handled in updated event)
        }

        // Validate before levels match current levels
        if ($fuelTransfer->fuelStorage && 
            $fuelTransfer->storage_level_before !== $fuelTransfer->fuelStorage->current_level) {
            Log::warning('Storage level mismatch before transfer', [
                'recorded_before' => $fuelTransfer->storage_level_before,
                'actual_current' => $fuelTransfer->fuelStorage->current_level,
                'storage_code' => $fuelTransfer->fuelStorage->storage_code
            ]);
        }

        if ($fuelTransfer->fuelTruck && 
            $fuelTransfer->truck_level_before !== $fuelTransfer->fuelTruck->current_level) {
            Log::warning('Truck level mismatch before transfer', [
                'recorded_before' => $fuelTransfer->truck_level_before,
                'actual_current' => $fuelTransfer->fuelTruck->current_level,
                'truck_code' => $fuelTransfer->fuelTruck->truck_code
            ]);
        }
    }

    /**
     * Calculate and log transfer efficiency
     */
    private function logTransferEfficiency(FuelTransfer $fuelTransfer): void
    {
        $efficiency = $fuelTransfer->getTransferEfficiency();
        
        if ($efficiency < 95) {
            Log::warning('Low transfer efficiency detected', [
                'transfer_id' => $fuelTransfer->id,
                'efficiency' => $efficiency,
                'storage_variance' => $fuelTransfer->getStorageLevelChange() + $fuelTransfer->transferred_amount,
                'truck_variance' => $fuelTransfer->getTruckLevelChange() - $fuelTransfer->transferred_amount
            ]);
        }
    }
}