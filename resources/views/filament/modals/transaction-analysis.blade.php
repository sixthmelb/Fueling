{{-- resources/views/filament/modals/transaction-analysis.blade.php --}}

<div class="p-6 space-y-6">
    {{-- Header --}}
    <div class="text-center border-b pb-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            Fuel Transaction Analysis
        </h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
            {{ $transaction->transaction_summary }}
        </p>
        <div class="mt-2 flex justify-center">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                @switch($transaction->efficiency_color)
                    @case('success') bg-green-100 text-green-800 @break
                    @case('primary') bg-blue-100 text-blue-800 @break
                    @case('warning') bg-yellow-100 text-yellow-800 @break
                    @case('danger') bg-red-100 text-red-800 @break
                    @default bg-gray-100 text-gray-800
                @endswitch">
                {{ $rating }}
            </span>
        </div>
    </div>

    {{-- Transaction Details --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        {{-- Basic Info --}}
        <div class="space-y-4">
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                <h4 class="font-medium text-gray-900 dark:text-white mb-3">Transaction Details</h4>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-400">Unit:</span>
                        <span class="font-medium">{{ $transaction->unit->display_name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-400">Date & Time:</span>
                        <span class="font-medium">{{ $transaction->transaction_datetime->format('d/m/Y H:i') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-400">Operator:</span>
                        <span class="font-medium">{{ $transaction->operator_name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-400">Fuel Source:</span>
                        <span class="font-medium">{{ $transaction->fuel_source_name }}</span>
                    </div>
                    <div class="flex justify-between border-t pt-2">
                        <span class="text-gray-600 dark:text-gray-400">Fuel Amount:</span>
                        <span class="font-bold text-blue-600">{{ number_format($transaction->fuel_amount, 2) }} L</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Meter Readings --}}
        <div class="space-y-4">
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                <h4 class="font-medium text-gray-900 dark:text-white mb-3">Meter Readings</h4>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    {{-- Hour Meter --}}
                    <div class="space-y-2">
                        <div class="font-medium text-gray-700 dark:text-gray-300">Hour Meter</div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Previous:</span>
                            <span>{{ number_format($transaction->previous_hour_meter, 2) }}h</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Current:</span>
                            <span>{{ number_format($transaction->current_hour_meter, 2) }}h</span>
                        </div>
                        <div class="flex justify-between border-t pt-1">
                            <span class="text-gray-800 dark:text-gray-200 font-medium">Difference:</span>
                            <span class="font-bold text-blue-600">{{ number_format($transaction->getHourMeterDiff(), 2) }}h</span>
                        </div>
                    </div>

                    {{-- Odometer --}}
                    <div class="space-y-2">
                        <div class="font-medium text-gray-700 dark:text-gray-300">Odometer</div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Previous:</span>
                            <span>{{ number_format($transaction->previous_odometer, 2) }}km</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Current:</span>
                            <span>{{ number_format($transaction->current_odometer, 2) }}km</span>
                        </div>
                        <div class="flex justify-between border-t pt-1">
                            <span class="text-gray-800 dark:text-gray-200 font-medium">Difference:</span>
                            <span class="font-bold text-blue-600">{{ number_format($transaction->getOdometerDiff(), 2) }}km</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Efficiency Analysis --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        {{-- Per Hour Efficiency --}}
        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 text-center">
            <div class="text-2xl font-bold text-blue-600">
                {{ $transaction->fuel_efficiency_per_hour ? number_format($transaction->fuel_efficiency_per_hour, 2) : '—' }}
            </div>
            <div class="text-sm text-blue-700 dark:text-blue-300">L/hour</div>
            <div class="text-xs text-blue-600 mt-1">Hourly Efficiency</div>
        </div>

        {{-- Per KM Efficiency --}}
        <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 text-center">
            <div class="text-2xl font-bold text-green-600">
                {{ $transaction->fuel_efficiency_per_km ? number_format($transaction->fuel_efficiency_per_km, 2) : '—' }}
            </div>
            <div class="text-sm text-green-700 dark:text-green-300">L/km</div>
            <div class="text-xs text-green-600 mt-1">Distance Efficiency</div>
        </div>

        {{-- Combined Efficiency --}}
        <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4 text-center">
            <div class="text-2xl font-bold text-purple-600">
                {{ $transaction->combined_efficiency ? number_format($transaction->combined_efficiency, 2) : '—' }}
            </div>
            <div class="text-sm text-purple-700 dark:text-purple-300">L/combined</div>
            <div class="text-xs text-purple-600 mt-1">Combined Efficiency</div>
        </div>
    </div>

    {{-- Variance Analysis --}}
    @if($variance !== null)
    @php
        $varianceColor = abs($variance) > 15 ? 'red' : 'yellow';
        $isHighVariance = abs($variance) > 15;
    @endphp
    <div class="bg-{{ $varianceColor }}-50 dark:bg-{{ $varianceColor }}-900/20 rounded-lg p-4 border border-{{ $varianceColor }}-200 dark:border-{{ $varianceColor }}-800">
        <h4 class="font-medium text-{{ $varianceColor }}-900 dark:text-{{ $varianceColor }}-100 mb-3 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 00-2-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            Consumption Variance Analysis
        </h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <div class="text-3xl font-bold text-{{ $varianceColor }}-600">
                    {{ $variance > 0 ? '+' : '' }}{{ number_format($variance, 1) }}%
                </div>
                <div class="text-sm text-{{ $varianceColor }}-700 dark:text-{{ $varianceColor }}-300">
                    vs Expected Consumption
                </div>
            </div>
            <div class="text-sm space-y-1">
                @if(abs($variance) <= 5)
                    <div class="text-green-700 dark:text-green-300">✓ Normal consumption range</div>
                @elseif(abs($variance) <= 15)
                    <div class="text-yellow-700 dark:text-yellow-300">⚠ Moderate variance detected</div>
                @else
                    <div class="text-red-700 dark:text-red-300">⚠ High variance - investigation recommended</div>
                @endif
                
                @if($variance > 15)
                    <div class="text-xs text-red-600">Higher than expected - possible heavy load or inefficiency</div>
                @elseif($variance < -15)
                    <div class="text-xs text-red-600">Lower than expected - check measurement accuracy</div>
                @endif
            </div>
        </div>
    </div>
    @endif

    {{-- Reasonableness Check --}}
    @php
        $reasonableColor = $isReasonable ? 'green' : 'red';
    @endphp
    <div class="bg-{{ $reasonableColor }}-50 dark:bg-{{ $reasonableColor }}-900/20 rounded-lg p-4 border border-{{ $reasonableColor }}-200 dark:border-{{ $reasonableColor }}-800">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <svg class="w-6 h-6 text-{{ $reasonableColor }}-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    @if($isReasonable)
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    @else
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    @endif
                </svg>
                <div>
                    <div class="font-medium text-{{ $reasonableColor }}-900 dark:text-{{ $reasonableColor }}-100">
                        {{ $isReasonable ? 'Reasonable Consumption' : 'Unusual Consumption Pattern' }}
                    </div>
                    <div class="text-sm text-{{ $reasonableColor }}-700 dark:text-{{ $reasonableColor }}-300">
                        {{ $isReasonable ? 'Consumption within expected parameters' : 'Consumption outside normal range for this unit type' }}
                    </div>
                </div>
            </div>
            <div class="text-right">
                <div class="text-lg font-bold text-{{ $reasonableColor }}-600">{{ $rating }}</div>
            </div>
        </div>
    </div>

    {{-- Unit Performance Comparison --}}
    @php
        $unitTypeAvg = $transaction->unit->getAverageConsumptionPerHour();
        $comparison = $transaction->unit->getAverageConsumptionPerKm();
    @endphp

    @if($unitTypeAvg || $comparison)
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <h4 class="font-medium text-gray-900 dark:text-white mb-3">Unit Performance Comparison</h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            @if($unitTypeAvg)
            <div>
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">Unit 30-day Avg (L/hr):</span>
                    <span class="font-medium">{{ number_format($unitTypeAvg, 2) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">This Transaction (L/hr):</span>
                    <span class="font-medium">{{ $transaction->fuel_efficiency_per_hour ? number_format($transaction->fuel_efficiency_per_hour, 2) : '—' }}</span>
                </div>
            </div>
            @endif
            
            @if($comparison)
            <div>
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">Unit 30-day Avg (L/km):</span>
                    <span class="font-medium">{{ number_format($comparison, 2) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">This Transaction (L/km):</span>
                    <span class="font-medium">{{ $transaction->fuel_efficiency_per_km ? number_format($transaction->fuel_efficiency_per_km, 2) : '—' }}</span>
                </div>
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Calculation Details --}}
    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
        <h4 class="font-medium text-blue-900 dark:text-blue-100 mb-3">Calculation Details</h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
                <div class="font-medium text-blue-800 dark:text-blue-200 mb-2">Calculation Method:</div>
                <div class="space-y-1 text-blue-700 dark:text-blue-300">
                    <div>• L/hour = Fuel Amount ÷ Hour Difference</div>
                    <div>• L/km = Fuel Amount ÷ Distance Difference</div>
                    <div>• Combined = Weighted average (70% hour, 30% km)</div>
                </div>
            </div>
            <div>
                <div class="font-medium text-blue-800 dark:text-blue-200 mb-2">Calculation Time:</div>
                <div class="text-blue-700 dark:text-blue-300">
                    {{ $transaction->calculated_at ? $transaction->calculated_at->format('d/m/Y H:i') : 'Not calculated yet' }}
                </div>
                @if($transaction->calculated_at)
                <div class="text-xs text-blue-600 mt-1">
                    {{ $transaction->calculated_at->diffForHumans() }}
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Additional Notes --}}
    @if($transaction->notes)
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <h4 class="font-medium text-gray-900 dark:text-white mb-2">Transaction Notes</h4>
        <p class="text-sm text-gray-700 dark:text-gray-300">{{ $transaction->notes }}</p>
    </div>
    @endif

    {{-- Recommendations --}}
    <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4 border border-yellow-200 dark:border-yellow-800">
        <h4 class="font-medium text-yellow-900 dark:text-yellow-100 mb-2 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
            </svg>
            Recommendations
        </h4>
        <div class="text-sm text-yellow-800 dark:text-yellow-200 space-y-1">
            @if(!$isReasonable)
                <div>• Review unit operating conditions and load factors</div>
                <div>• Consider maintenance check if efficiency consistently poor</div>
            @endif
            @if($variance && abs($variance) > 15)
                <div>• Verify fuel measurement accuracy</div>
                <div>• Check for potential fuel leaks or theft</div>
            @endif
            @if(!$transaction->fuel_efficiency_per_hour && !$transaction->fuel_efficiency_per_km)
                <div>• Recalculate efficiency metrics</div>
            @endif
            @if($isReasonable && $variance && abs($variance) <= 10)
                <div>• Good fuel efficiency - maintain current operating practices</div>
            @endif
        </div>
    </div>
</div>