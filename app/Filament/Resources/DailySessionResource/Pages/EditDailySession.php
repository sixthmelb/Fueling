<?php

namespace App\Filament\Resources\DailySessionResource\Pages;

use App\Filament\Resources\DailySessionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDailySession extends EditRecord
{
    protected static string $resource = DailySessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
