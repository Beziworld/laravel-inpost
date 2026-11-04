# Laravel InPost

[![tests](https://github.com/beziworld/laravel-inpost/actions/workflows/tests.yml/badge.svg)](https://github.com/beziworld/laravel-inpost/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/beziworld/laravel-inpost.svg)](https://packagist.org/packages/beziworld/laravel-inpost)
[![License](https://img.shields.io/packagist/l/beziworld/laravel-inpost.svg)](LICENSE.md)

[InPost ShipX](https://inpost.pl) for Laravel: shipments to parcel lockers (Paczkomaty) and by courier, labels,
courier pickups, tracking, point search, the Geowidget map and status webhooks.

[Polska wersja dokumentacji](README.pl.md)

## Installation

```bash
composer require beziworld/laravel-inpost
php artisan vendor:publish --tag=inpost-config   # optional
```

```dotenv
INPOST_TOKEN=your-shipx-token            # Manager Paczek > My account > API
INPOST_ORGANIZATION_ID=12345
INPOST_SANDBOX=true                      # sandbox-api-shipx-pl.easypack24.net
INPOST_GEOWIDGET_TOKEN=your-public-geo-token
```

Points, tracking and statuses are public and work without a token.

## Parcel locker in the checkout

```blade
{{-- checkout.blade.php --}}
{{ InPost::geowidget('onInPostPointSelected', 'parcelCollect') }}

<input type="hidden" name="inpost_point" id="inpost_point">
<script>
    document.addEventListener('onInPostPointSelected', (event) => {
        document.getElementById('inpost_point').value = event.detail.name;
    });
</script>
```

Use `parcelCollectPayment` for cash on delivery or `parcelCollect247` for weekend deliveries. Validate the choice on
the server:

```php
$point = InPost::point($request->input('inpost_point'));
abort_if($point === null || ! $point->isOperating(), 422, 'Choose an active parcel locker.');
```

## Shipments

```php
use Beziworld\InPost\Data\ShipmentRequest;
use Beziworld\InPost\Facades\InPost;

// Parcel locker, size B, cash on delivery
$shipment = InPost::createShipment(ShipmentRequest::toLocker(
    targetPoint: $order->inpost_point,     // e.g. "KRA010"
    email: $order->email,
    phone: $order->phone,
    template: 'medium',
    reference: $order->number,
    codAmount: $order->cod ? $order->total : null,
));

$order->update(['inpost_shipment_id' => $shipment->id]);
```

Courier shipments and full control:

```php
use Beziworld\InPost\Data\{Address, Parcel, Peer, ShipmentRequest};
use Beziworld\InPost\Enums\Service;

InPost::createShipment(new ShipmentRequest(
    service: Service::CourierStandard,
    receiver: new Peer(email: 'jan@example.com', phone: '500100200', firstName: 'Jan', lastName: 'Kowalski',
        address: new Address('Prosta', '1', 'Warszawa', '00-001')),
    parcels: [Parcel::dimensions(length: 400, width: 300, height: 200, weight: 3.5)],
    insuranceAmount: 500,
    reference: 'order-1001',
    additionalServices: ['sms', 'email'],
));
```

Creation is asynchronous: the shipment starts as `created` and becomes `confirmed` shortly after (with a tracking
number). Download the label once it is confirmed:

```php
$shipment = InPost::shipment($order->inpost_shipment_id);

if ($shipment->isConfirmed()) {
    return response(InPost::label($shipment->id, 'pdf', 'A6'), 200, ['Content-Type' => 'application/pdf']);
}
```

Other calls: `InPost::labels([1, 2, 3])` (up to 100 labels in one file), `InPost::shipments(['status' => 'confirmed'])`,
`InPost::cancelShipment($id)` (only before confirmation) and
`InPost::createDispatchOrder([$id1, $id2], new Address(...), 'Warehouse', '500100200')` for a courier pickup.

## Tracking and points

```php
$tracking = InPost::tracking('620000000000000000000000');
$tracking->status;          // "ready_to_pickup"
$tracking->events;          // newest first: status, location, at

InPost::points(['city' => 'Kraków', 'type' => 'parcel_locker', 'payment_available' => true]);
InPost::points(['relative_point' => '50.0614,19.9366', 'max_distance' => 1000]);   // nearest, with distance in metres
InPost::statuses();         // ['delivered' => 'Dostarczona.', ...]
```

## Webhooks

Register `https://your-app.com/inpost/webhook` in Manager Paczek. The package answers the GET check, accepts POSTs
only from the InPost network (91.216.25.0/24) and dispatches `ShipmentStatusChanged`:

```php
use Beziworld\InPost\Events\ShipmentStatusChanged;

Event::listen(function (ShipmentStatusChanged $event) {
    $order = Order::where('inpost_shipment_id', $event->shipmentId)->first();

    if ($event->status === 'delivered') {
        // ShipX webhooks are not signed: confirm important changes with the API
        $order?->markAsDelivered(InPost::shipment($event->shipmentId)->status === 'delivered');
    }
});
```

Behind a load balancer configure Laravel trusted proxies so that the IP check sees the real sender.

## Exceptions

`ApiException` (`error()` such as `validation_failed`, `details()` with field errors, `httpStatus()`) and
`InvalidConfiguration`, both extending `InPostException`.

## Development

```bash
composer install
composer ci   # pint --test, phpstan (level 8), pest
```

## Author

Created and maintained by **Kamil Ćwiertnia**, [beziworld.eu](https://beziworld.eu).

- E-mail: [biuro@beziworld.eu](mailto:biuro@beziworld.eu)
- Issues: [GitHub issues](https://github.com/beziworld/laravel-inpost/issues)

Need a shipping integration for your shop? Get in touch through [beziworld.eu](https://beziworld.eu).

### Other packages by the author

[laravel-przelewy24](https://github.com/beziworld/laravel-przelewy24) ·
[laravel-payu](https://github.com/beziworld/laravel-payu) ·
[laravel-paynow](https://github.com/beziworld/laravel-paynow) ·
[laravel-tpay](https://github.com/beziworld/laravel-tpay) ·
[laravel-autopay](https://github.com/beziworld/laravel-autopay) ·
[laravel-company-data](https://github.com/beziworld/laravel-company-data)

## License

MIT. See [LICENSE.md](LICENSE.md). This package is not affiliated with InPost S.A.
