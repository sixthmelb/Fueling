<?php

namespace App\Filament\Resources\FuelTransferResource\Pages;

use App\Filament\Resources\FuelTransferResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFuelTransfers extends ListRecords
{
    protected static string $resource = FuelTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
