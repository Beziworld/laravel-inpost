<?php

declare(strict_types=1);

namespace Beziworld\InPost\Enums;

/**
 * ShipX shipment services.
 */
enum Service: string
{
    case LockerStandard = 'inpost_locker_standard';
    case LockerAllegro = 'inpost_locker_allegro';
    case LockerPassThru = 'inpost_locker_pass_thru';
    case CourierStandard = 'inpost_courier_standard';
    case CourierExpress1000 = 'inpost_courier_express_1000';
    case CourierExpress1200 = 'inpost_courier_express_1200';
    case CourierExpress1700 = 'inpost_courier_express_1700';
    case CourierC2C = 'inpost_courier_c2c';
    case CourierPalette = 'inpost_courier_palette';
    case CourierAllegro = 'inpost_courier_allegro';
    case LetterAllegro = 'inpost_letter_allegro';
    case CourierAlcohol = 'inpost_courier_alcohol';

    /**
     * Delivery to a parcel locker or pickup point, which requires a target point.
     */
    public function isLocker(): bool
    {
        return str_starts_with($this->value, 'inpost_locker_');
    }
}
