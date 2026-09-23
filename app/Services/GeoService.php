<?php

namespace App\Services;

class GeoService
{
    /**
     * Distance en mètres entre deux points GPS (formule de Haversine).
     */
    public static function distanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): int
    {
        $earthRadius = 6371000; // mètres

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lonDelta / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return (int) round($earthRadius * $c);
    }

    public static function isWithinSite(float $lat, float $lon, $site): bool
    {
        $distance = self::distanceMeters($lat, $lon, (float) $site->latitude, (float) $site->longitude);

        return $distance <= (int) $site->radius_m;
    }
}
