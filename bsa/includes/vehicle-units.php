<?php
/**
 * bsa/includes/vehicle-units.php
 * Shared vehicle types/units used by the vehicle request form, availability
 * check, save endpoint and approvals.
 */

if (!function_exists('bsaVehicleTypes')) {
    function bsaVehicleTypes(): array
    {
        return ['Pickup Truck', 'Utility Van', 'Commuter Van'];
    }
}

if (!function_exists('bsaVehicleSubtypes')) {
    /** Units available under a vehicle type (empty when the type has no sub-options). */
    function bsaVehicleSubtypes(string $vehicleType): array
    {
        return $vehicleType === 'Pickup Truck' ? ['FOTON', 'STRADA'] : [];
    }
}

if (!function_exists('bsaVehicleUnitKey')) {
    /** Key used to identify one bookable unit: "Pickup Truck|FOTON", "Utility Van|", ... */
    function bsaVehicleUnitKey(string $vehicleType, ?string $subtype): string
    {
        return $vehicleType . '|' . (($subtype ?? '') !== '' ? strtoupper($subtype) : '');
    }
}

if (!function_exists('bsaVehicleUnitLabel')) {
    /** Human-readable label, e.g. "Pickup Truck (FOTON)". */
    function bsaVehicleUnitLabel(string $vehicleType, ?string $subtype): string
    {
        return (($subtype ?? '') !== '') ? $vehicleType . ' (' . strtoupper($subtype) . ')' : $vehicleType;
    }
}
