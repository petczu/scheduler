<?php

namespace App\Filament\Resources\ScanRuns\Schemas;

use Filament\Schemas\Schema;

class ScanRunForm
{
    // Scan runs are created by the scanner and are read-only in the panel.
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([]);
    }
}
