<?php

namespace App\Filament\Resources\FuelTransactionResource\Pages;

use App\Filament\Resources\FuelTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewFuelTransaction extends ViewRecord
{
    protected static string $resource = FuelTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
