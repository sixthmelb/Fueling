<?php

// ============================================================================
// FIXED: app/Observers/FuelTransferObserver.php
// Problem: Logic error dalam updated() method
// Solution: Fix validation dan update logic
// ============================================================================

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
     * FIXED: Handle the FuelTransfer "updated" event.
     */
    public function updated(FuelTransfer $fuelTransfer): void
    {
        // Only process if transfer amount actually changed
        if (!$fuelTransfer->wasChanged('transferred_amount')) {
            return;
        }

        DB::transaction(function () use ($fuelTransfer) {
            try {
                // Get original amount dan new amount
                $originalAmount = $fuelTransfer->getOriginal('transferred_amount');
                $newAmount = $fuelTransfer->transferred_amount;
                $difference = $newAmount - $originalAmount;
                
                Log::info('Processing transfer update', [
                    'transfer_id' => $fuelTransfer->id,
                    'original_amount' => $originalAmount,
                    'new_amount' => $newAmount,
                    'difference' => $difference
                ]);
                
                $storage = $fuelTransfer->fuelStorage;
                $truck = $fuelTransfer->fuelTruck;
                
                // FIXED: Validate new total amount, bukan difference saja
                if ($difference > 0) {
                    // Increasing transfer amount - need to check if storage has enough fuel
                    if ($storage->current_level < $difference) {
                        throw new \Exception("Insufficient fuel in storage for updated transfer amount. Available: {$storage->current_level}L, Additional needed: {$difference}L");
                    }
                    
                    // Check if truck has capacity for additional fuel
                    if ($truck->getRemainingCapacity() < $difference) {
                        throw new \Exception("Truck capacity exceeded for updated transfer amount. Available capacity: {$truck->getRemainingCapacity()}L, Additional needed: {$difference}L");
                    }
                } else if ($difference < 0) {
                    // Decreasing transfer amount - need to check if truck has enough fuel to remove
                    $amountToRemove = abs($difference);
                    if ($truck->current_level < $amountToRemove) {
                        throw new \Exception("Insufficient fuel in truck to reduce transfer amount. Available: {$truck->current_level}L, Needed to remove: {$amountToRemove}L");
                    }
                }
                
                // Update storage level
                if ($difference > 0) {
                    // Remove more fuel from storage
                    if (!$storage->removeFuel($difference)) {
                        throw new \Exception("Failed to remove additional fuel from storage");
                    }
                } else if ($difference < 0) {
                    // Add fuel back to storage
                    $storage->addFuel(abs($difference));
                }
                
                // Update truck level
                if ($difference > 0) {
                    // Add more fuel to truck
                    if (!$truck->addFuel($difference)) {
                        throw new \Exception("Failed to add additional fuel to truck");
                    }
                } else if ($difference < 0) {
                    // Remove fuel from truck
                    if (!$truck->removeFuel(abs($difference))) {
                        throw new \Exception("Failed to remove fuel from truck");
                    }
                }
                
                // Update after levels in transfer record
                $this->updateAfterLevels($fuelTransfer);
                
                Log::info('Transfer update processed successfully', [
                    'transfer_id' => $fuelTransfer->id,
                    'storage_final_level' => $storage->current_level,
                    'truck_final_level' => $truck->current_level
                ]);
                
            } catch (\Exception $e) {
                Log::error('Error updating fuel transfer', [
                    'transfer_id' => $fuelTransfer->id,
                    'error' => $e->getMessage()
                ]);
                
                throw $e;
            }
        });
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
     * IMPROVED: Validate transfer before processing
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
     * IMPROVED: Handle the FuelTransfer "saving" event.
     */
    public function saving(FuelTransfer $fuelTransfer): void
    {
        // Skip validation for updates (handled in updated event)
        if ($fuelTransfer->exists) {
            return;
        }

        // Pre-validation before saving
        if ($fuelTransfer->transferred_amount <= 0) {
            throw new \Exception("Transfer amount must be greater than 0");
        }

        // Validate before levels match current levels (with some tolerance)
        if ($fuelTransfer->fuelStorage) {
            $storageLevelDiff = abs($fuelTransfer->storage_level_before - $fuelTransfer->fuelStorage->current_level);
            if ($storageLevelDiff > 0.01) {
                Log::warning('Storage level mismatch before transfer', [
                    'recorded_before' => $fuelTransfer->storage_level_before,
                    'actual_current' => $fuelTransfer->fuelStorage->current_level,
                    'storage_code' => $fuelTransfer->fuelStorage->storage_code,
                    'difference' => $storageLevelDiff
                ]);
            }
        }

        if ($fuelTransfer->fuelTruck) {
            $truckLevelDiff = abs($fuelTransfer->truck_level_before - $fuelTransfer->fuelTruck->current_level);
            if ($truckLevelDiff > 0.01) {
                Log::warning('Truck level mismatch before transfer', [
                    'recorded_before' => $fuelTransfer->truck_level_before,
                    'actual_current' => $fuelTransfer->fuelTruck->current_level,
                    'truck_code' => $fuelTransfer->fuelTruck->truck_code,
                    'difference' => $truckLevelDiff
                ]);
            }
        }
    }
}