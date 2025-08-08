<?php

namespace App\Filament\Resources\DailySessionResource\Pages;

use App\Filament\Resources\DailySessionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDailySessions extends ListRecords
{
    protected static string $resource = DailySessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
