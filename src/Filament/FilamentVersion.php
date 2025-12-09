<?php

declare(strict_types=1);

namespace Outlined\Sentinel\Filament;

use Composer\InstalledVersions;

class FilamentVersion
{
    public static function isV4(): bool
    {
        // Check if Filament\Schemas\Schema exists (Filament 4 only)
        return class_exists(\Filament\Schemas\Schema::class);
    }

    public static function isV3(): bool
    {
        return ! self::isV4();
    }

    public static function getMajorVersion(): int
    {
        if (class_exists(InstalledVersions::class)) {
            $version = InstalledVersions::getVersion('filament/filament');
            if ($version && preg_match('/^v?(\d+)/', $version, $matches)) {
                return (int) $matches[1];
            }
        }

        return self::isV4() ? 4 : 3;
    }
}
