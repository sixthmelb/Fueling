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
        
        // Set before levels from current state
        if ($fuelTransfer->fuelStorage) {
            // Refresh model to get latest data
            $fuelTransfer->fuelStorage->refresh();
            $fuelTransfer->storage_level_before = $fuelTransfer->fuelStorage->current_level;
        }
        
        if ($fuelTransfer->fuelTruck) {
            // Refresh model to get latest data
            $fuelTransfer->fuelTruck->refresh();
            $fuelTransfer->truck_level_before = $fuelTransfer->fuelTruck->current_level;
        }
        
        Log::info('FuelTransfer creating', [
            'storage_before' => $fuelTransfer->storage_level_before,
            'truck_before' => $fuelTransfer->truck_level_before,
            'amount' => $fuelTransfer->transferred_amount
        ]);
    }

    /**
     * Handle the FuelTransfer "created" event.
     */
    public function created(FuelTransfer $fuelTransfer): void
    {
        DB::transaction(function () use ($fuelTransfer) {
            try {
                Log::info('Processing fuel transfer creation', [
                    'transfer_id' => $fuelTransfer->id,
                    'amount' => $fuelTransfer->transferred_amount,
                    'storage_id' => $fuelTransfer->fuel_storage_id,
                    'truck_id' => $fuelTransfer->fuel_truck_id
                ]);

                // 1. Validate transfer
                $this->validateTransfer($fuelTransfer);
                
                // 2. Get fresh instances to avoid stale data
                $storage = $fuelTransfer->fuelStorage()->lockForUpdate()->first();
                $truck = $fuelTransfer->fuelTruck()->lockForUpdate()->first();
                
                if (!$storage || !$truck) {
                    throw new \Exception('Storage or truck not found');
                }
                
                $amount = $fuelTransfer->transferred_amount;
                
                Log::info('Current levels before transfer', [
                    'storage_current' => $storage->current_level,
                    'truck_current' => $truck->current_level,
                    'transfer_amount' => $amount
                ]);
                
                // 3. Update storage level (decrease)
                $oldStorageLevel = $storage->current_level;
                $newStorageLevel = $oldStorageLevel - $amount;
                
                if ($newStorageLevel < 0) {
                    throw new \Exception("Insufficient fuel in storage. Available: {$oldStorageLevel}L, Required: {$amount}L");
                }
                
                $storage->update(['current_level' => $newStorageLevel]);
                
                // 4. Update truck level (increase)
                $oldTruckLevel = $truck->current_level;
                $newTruckLevel = $oldTruckLevel + $amount;
                
                if ($newTruckLevel > $truck->capacity) {
                    throw new \Exception("Truck capacity exceeded. Capacity: {$truck->capacity}L, Would be: {$newTruckLevel}L");
                }
                
                $truck->update(['current_level' => $newTruckLevel]);
                
                // 5. Update transfer record with actual after levels
                $fuelTransfer->update([
                    'storage_level_after' => $newStorageLevel,
                    'truck_level_after' => $newTruckLevel
                ]);
                
                Log::info('Fuel transfer processed successfully', [
                    'transfer_id' => $fuelTransfer->id,
                    'storage_change' => $oldStorageLevel . ' → ' . $newStorageLevel,
                    'truck_change' => $oldTruckLevel . ' → ' . $newTruckLevel,
                    'amount' => $amount
                ]);
                
            } catch (\Exception $e) {
                Log::error('Error processing fuel transfer', [
                    'transfer_id' => $fuelTransfer->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
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
        // Only process if transfer amount actually changed
        if (!$fuelTransfer->wasChanged('transferred_amount')) {
            return;
        }

        DB::transaction(function () use ($fuelTransfer) {
            try {
                $originalAmount = $fuelTransfer->getOriginal('transferred_amount');
                $newAmount = $fuelTransfer->transferred_amount;
                $difference = $newAmount - $originalAmount;
                
                Log::info('Processing transfer update', [
                    'transfer_id' => $fuelTransfer->id,
                    'original_amount' => $originalAmount,
                    'new_amount' => $newAmount,
                    'difference' => $difference
                ]);
                
                // Get fresh instances with locks
                $storage = $fuelTransfer->fuelStorage()->lockForUpdate()->first();
                $truck = $fuelTransfer->fuelTruck()->lockForUpdate()->first();
                
                // Calculate what the levels should be after the original transfer
                $expectedStorageLevel = $fuelTransfer->storage_level_before - $originalAmount;
                $expectedTruckLevel = $fuelTransfer->truck_level_before + $originalAmount;
                
                // Apply the difference
                $newStorageLevel = $expectedStorageLevel - $difference;
                $newTruckLevel = $expectedTruckLevel + $difference;
                
                // Validate new levels
                if ($newStorageLevel < 0) {
                    throw new \Exception("Insufficient fuel in storage for updated amount. Would be: {$newStorageLevel}L");
                }
                
                if ($newTruckLevel > $truck->capacity) {
                    throw new \Exception("Truck capacity exceeded for updated amount. Would be: {$newTruckLevel}L, Capacity: {$truck->capacity}L");
                }
                
                // Update levels
                $storage->update(['current_level' => $newStorageLevel]);
                $truck->update(['current_level' => $newTruckLevel]);
                
                // Update transfer record
                $fuelTransfer->update([
                    'storage_level_after' => $newStorageLevel,
                    'truck_level_after' => $newTruckLevel
                ]);
                
                Log::info('Transfer update processed successfully', [
                    'transfer_id' => $fuelTransfer->id,
                    'storage_final' => $newStorageLevel,
                    'truck_final' => $newTruckLevel
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
                Log::info('Processing transfer deletion', [
                    'transfer_id' => $fuelTransfer->id,
                    'amount_to_rollback' => $fuelTransfer->transferred_amount
                ]);
                
                // Get fresh instances
                $storage = \App\Models\FuelStorage::lockForUpdate()->find($fuelTransfer->fuel_storage_id);
                $truck = \App\Models\FuelTruck::lockForUpdate()->find($fuelTransfer->fuel_truck_id);
                
                if ($storage && $truck) {
                    // Rollback: add fuel back to storage, remove from truck
                    $storage->increment('current_level', $fuelTransfer->transferred_amount);
                    $truck->decrement('current_level', $fuelTransfer->transferred_amount);
                    
                    Log::info('Transfer deletion rollback completed', [
                        'transfer_id' => $fuelTransfer->id,
                        'storage_new_level' => $storage->fresh()->current_level,
                        'truck_new_level' => $truck->fresh()->current_level
                    ]);
                }
                
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
        $availableCapacity = $truck->capacity - $truck->current_level;
        if ($availableCapacity < $amount) {
            throw new \Exception("Insufficient truck capacity in {$truck->truck_code}. Available: {$availableCapacity}L, Required: {$amount}L");
        }

        // Check if truck is active
        if (!$truck->is_active) {
            throw new \Exception("Cannot transfer to inactive truck {$truck->truck_code}");
        }

        // Check if storage is active
        if (!$storage->is_active) {
            throw new \Exception("Cannot transfer from inactive storage {$storage->storage_code}");
        }

        // Validate fuel types match
        if ($storage->fuel_type !== $truck->fuel_type) {
            Log::warning('Fuel type mismatch in transfer', [
                'storage_type' => $storage->fuel_type,
                'truck_type' => $truck->fuel_type,
                'transfer_id' => $fuelTransfer->id
            ]);
        }
    }

    /**
     * Handle the FuelTransfer "saving" event.
     */
    public function saving(FuelTransfer $fuelTransfer): void
    {
        // Basic validation
        if ($fuelTransfer->transferred_amount <= 0) {
            throw new \Exception("Transfer amount must be greater than 0");
        }

        // Only validate on create, not update (to avoid circular validation)
        if (!$fuelTransfer->exists) {
            // Pre-validate before saving
            if ($fuelTransfer->fuelStorage && $fuelTransfer->fuelTruck) {
                $this->validateTransfer($fuelTransfer);
            }
        }
    }
}