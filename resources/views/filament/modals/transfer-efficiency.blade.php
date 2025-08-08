{{-- resources/views/filament/modals/transfer-efficiency.blade.php --}}

<div class="p-6 space-y-6">
    {{-- Header --}}
    <div class="text-center border-b pb-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            Transfer Efficiency Analysis
        </h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
            {{ $transfer->transfer_summary }}
        </p>
    </div>

    {{-- Transfer Details --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        {{-- Basic Info --}}
        <div class="space-y-4">
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                <h4 class="font-medium text-gray-900 dark:text-white mb-3">Transfer Details</h4>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-400">Transfer Number:</span>
                        <span class="font-medium">{{ $transfer->transfer_number ?: 'Auto-generated' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-400">Date & Time:</span>
                        <span class="font-medium">{{ $transfer->transfer_datetime->format('d/m/Y H:i') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-400">Operator:</span>
                        <span class="font-medium">{{ $transfer->operator_name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-400">Amount:</span>
                        <span class="font-bold text-blue-600">{{ number_format($transfer->transferred_amount, 2) }} L</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Efficiency Metrics --}}
        <div class="space-y-4">
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                <h4 class="font-medium text-gray-900 dark:text-white mb-3">Efficiency Metrics</h4>
                <div class="space-y-3">
                    {{-- Efficiency Score --}}
                    <div class="text-center">
                        <div class="text-3xl font-bold {{ $efficiency >= 99 ? 'text-green-600' : ($efficiency >= 95 ? 'text-blue-600' : ($efficiency >= 90 ? 'text-yellow-600' : 'text-red-600')) }}">
                            {{ number_format($efficiency, 1) }}%
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Transfer Efficiency</div>
                        <div class="mt-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                {{ $efficiency >= 99 ? 'bg-green-100 text-green-800' : ($efficiency >= 95 ? 'bg-blue-100 text-blue-800' : ($efficiency >= 90 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800')) }}">
                                {{ $transfer->efficiency_status }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Level Changes --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        {{-- Storage Changes --}}
        <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4">
            <h4 class="font-medium text-red-900 dark:text-red-100 mb-3 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                </svg>
                Storage Level Changes
            </h4>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-red-700 dark:text-red-300">Before Transfer:</span>
                    <span class="font-medium">{{ number_format($transfer->storage_level_before, 2) }} L</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-red-700 dark:text-red-300">After Transfer:</span>
                    <span class="font-medium">{{ number_format($transfer->storage_level_after, 2) }} L</span>
                </div>
                <div class="flex justify-between border-t pt-2">
                    <span class="text-red-800 dark:text-red-200 font-medium">Net Change:</span>
                    <span class="font-bold text-red-600">{{ number_format($storageChange, 2) }} L</span>
                </div>
            </div>
        </div>

        {{-- Truck Changes --}}
        <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
            <h4 class="font-medium text-green-900 dark:text-green-100 mb-3 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                </svg>
                Truck Level Changes
            </h4>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-green-700 dark:text-green-300">Before Transfer:</span>
                    <span class="font-medium">{{ number_format($transfer->truck_level_before, 2) }} L</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-green-700 dark:text-green-300">After Transfer:</span>
                    <span class="font-medium">{{ number_format($transfer->truck_level_after, 2) }} L</span>
                </div>
                <div class="flex justify-between border-t pt-2">
                    <span class="text-green-800 dark:text-green-200 font-medium">Net Change:</span>
                    <span class="font-bold text-green-600">+{{ number_format($truckChange, 2) }} L</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Variance Analysis --}}
    @php
        $expectedStorageAfter = $transfer->storage_level_before - $transfer->transferred_amount;
        $expectedTruckAfter = $transfer->truck_level_before + $transfer->transferred_amount;
        $storageVariance = $transfer->storage_level_after - $expectedStorageAfter;
        $truckVariance = $transfer->truck_level_after - $expectedTruckAfter;
        $hasVariance = abs($storageVariance) > 0.1 || abs($truckVariance) > 0.1;
    @endphp

    @if($hasVariance)
    <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4 border border-yellow-200 dark:border-yellow-800">
        <h4 class="font-medium text-yellow-900 dark:text-yellow-100 mb-3 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.502 0L4.312 18.5c-.77.833.192 2.5 1.732 2.5z"/>
            </svg>
            Variance Detected
        </h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
                <span class="font-medium text-yellow-800 dark:text-yellow-200">Storage Variance:</span>
                <span class="ml-2 {{ abs($storageVariance) > 1 ? 'text-red-600 font-bold' : 'text-yellow-700' }}">
                    {{ $storageVariance > 0 ? '+' : '' }}{{ number_format($storageVariance, 2) }} L
                </span>
            </div>
            <div>
                <span class="font-medium text-yellow-800 dark:text-yellow-200">Truck Variance:</span>
                <span class="ml-2 {{ abs($truckVariance) > 1 ? 'text-red-600 font-bold' : 'text-yellow-700' }}">
                    {{ $truckVariance > 0 ? '+' : '' }}{{ number_format($truckVariance, 2) }} L
                </span>
            </div>
        </div>
        <p class="text-xs text-yellow-700 dark:text-yellow-300 mt-2">
            Small variances may indicate measurement accuracy or system synchronization issues.
        </p>
    </div>
    @else
    <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 border border-green-200 dark:border-green-800">
        <div class="flex items-center">
            <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span class="font-medium text-green-900 dark:text-green-100">Perfect Transfer</span>
        </div>
        <p class="text-sm text-green-700 dark:text-green-300 mt-1">
            No significant variance detected. Transfer completed with excellent accuracy.
        </p>
    </div>
    @endif

    {{-- Additional Notes --}}
    @if($transfer->notes)
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <h4 class="font-medium text-gray-900 dark:text-white mb-2">Additional Notes</h4>
        <p class="text-sm text-gray-700 dark:text-gray-300">{{ $transfer->notes }}</p>
    </div>
    @endif
</div>