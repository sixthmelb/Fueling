{{-- resources/views/filament/modals/session-statistics.blade.php --}}

<div class="p-6 space-y-6">
    {{-- Header --}}
    <div class="text-center border-b pb-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            Session Statistics
        </h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
            {{ $session->display_name }}
        </p>
        <div class="mt-2">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                {{ $session->status === 'Active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                {{ $session->status }}
            </span>
        </div>
    </div>

    {{-- Key Metrics --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 text-center">
            <div class="text-2xl font-bold text-blue-600">{{ $stats['transfers_count'] }}</div>
            <div class="text-sm text-blue-700 dark:text-blue-300">Transfers</div>
        </div>
        <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 text-center">
            <div class="text-2xl font-bold text-green-600">{{ $stats['transactions_count'] }}</div>
            <div class="text-sm text-green-700 dark:text-green-300">Transactions</div>
        </div>
        <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4 text-center">
            <div class="text-2xl font-bold text-purple-600">{{ $stats['unique_units'] }}</div>
            <div class="text-sm text-purple-700 dark:text-purple-300">Units Served</div>
        </div>
        <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4 text-center">
            <div class="text-2xl font-bold text-yellow-600">
                {{ $stats['duration_hours'] ? number_format($stats['duration_hours'], 1) . 'h' : 'Ongoing' }}
            </div>
            <div class="text-sm text-yellow-700 dark:text-yellow-300">Duration</div>
        </div>
    </div>

    {{-- Fuel Movement Summary --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4">
            <h4 class="font-medium text-red-900 dark:text-red-100 mb-3 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                </svg>
                Fuel Transfers
            </h4>
            <div class="text-3xl font-bold text-red-600">{{ number_format($stats['total_transfers'], 0) }}</div>
            <div class="text-sm text-red-700 dark:text-red-300">Liters transferred to trucks</div>
        </div>

        <div class="bg-orange-50 dark:bg-orange-900/20 rounded-lg p-4">
            <h4 class="font-medium text-orange-900 dark:text-orange-100 mb-3 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"/>
                </svg>
                Fuel Consumption
            </h4>
            <div class="text-3xl font-bold text-orange-600">{{ number_format($stats['total_transactions'], 0) }}</div>
            <div class="text-sm text-orange-700 dark:text-orange-300">Liters consumed by units</div>
        </div>
    </div>

    {{-- Most Active Units --}}
    @if($mostActiveUnits->isNotEmpty())
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <h4 class="font-medium text-gray-900 dark:text-white mb-4 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
            </svg>
            Most Active Units
        </h4>
        <div class="space-y-3">
            @foreach($mostActiveUnits as $unitData)
            <div class="flex items-center justify-between bg-white dark:bg-gray-700 rounded-lg p-3">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2v0a2 2 0 01-2-2v-2a2 2 0 00-2-2H8z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="font-medium text-gray-900 dark:text-white">{{ $unitData->unit->unit_code }}</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">{{ $unitData->unit->unit_name }}</div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="font-bold text-gray-900 dark:text-white">{{ number_format($unitData->total_fuel, 1) }}L</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">{{ $unitData->transaction_count }} transactions</div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @else
    <div class="text-center py-8">
        <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2v0a2 2 0 01-2-2v-2a2 2 0 00-2-2H8z"/>
        </svg>
        <p class="text-gray-600 dark:text-gray-400">No unit activity recorded for this session</p>
    </div>
    @endif

    {{-- Session Timeline --}}
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <h4 class="font-medium text-gray-900 dark:text-white mb-3">Session Timeline</h4>
        <div class="space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-600 dark:text-gray-400">Started:</span>
                <span class="font-medium">{{ $session->start_datetime->format('d/m/Y H:i') }}</span>
            </div>
            @if($session->end_datetime)
            <div class="flex justify-between">
                <span class="text-gray-600 dark:text-gray-400">Ended:</span>
                <span class="font-medium">{{ $session->end_datetime->format('d/m/Y H:i') }}</span>
            </div>
            @endif
            @if($session->shift)
            <div class="flex justify-between">
                <span class="text-gray-600 dark:text-gray-400">Shift:</span>
                <span class="font-medium">{{ $session->shift->shift_name }} ({{ $session->shift->time_range }})</span>
            </div>
            @endif
        </div>
    </div>

    {{-- Performance Indicators --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        @php
            $avgTransactionSize = $stats['transactions_count'] > 0 ? $stats['total_transactions'] / $stats['transactions_count'] : 0;
            $transferEfficiency = $stats['transfers_count'] > 0 ? ($stats['total_transactions'] / $stats['total_transfers']) * 100 : 0;
            $unitsPerHour = $stats['duration_hours'] > 0 ? $stats['unique_units'] / $stats['duration_hours'] : 0;
        @endphp

        <div class="text-center p-3 bg-white dark:bg-gray-700 rounded-lg">
            <div class="text-lg font-semibold text-gray-900 dark:text-white">{{ number_format($avgTransactionSize, 1) }}L</div>
            <div class="text-xs text-gray-600 dark:text-gray-400">Avg Transaction Size</div>
        </div>

        @if($stats['total_transfers'] > 0)
        <div class="text-center p-3 bg-white dark:bg-gray-700 rounded-lg">
            <div class="text-lg font-semibold text-gray-900 dark:text-white">{{ number_format($transferEfficiency, 1) }}%</div>
            <div class="text-xs text-gray-600 dark:text-gray-400">Transfer Efficiency</div>
        </div>
        @endif

        @if($stats['duration_hours'] > 0)
        <div class="text-center p-3 bg-white dark:bg-gray-700 rounded-lg">
            <div class="text-lg font-semibold text-gray-900 dark:text-white">{{ number_format($unitsPerHour, 1) }}</div>
            <div class="text-xs text-gray-600 dark:text-gray-400">Units/Hour</div>
        </div>
        @endif
    </div>

    {{-- Notes --}}
    @if($session->notes)
    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
        <h4 class="font-medium text-blue-900 dark:text-blue-100 mb-2">Session Notes</h4>
        <p class="text-sm text-blue-800 dark:text-blue-200">{{ $session->notes }}</p>
    </div>
    @endif
</div>