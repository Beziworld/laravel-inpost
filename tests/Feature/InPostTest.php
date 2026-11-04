<?php

declare(strict_types=1);

use Beziworld\InPost\Data\Address;
use Beziworld\InPost\Data\Parcel;
use Beziworld\InPost\Data\Peer;
use Beziworld\InPost\Data\ShipmentRequest;
use Beziworld\InPost\Enums\Service;
use Beziworld\InPost\Events\ShipmentStatusChanged;
use Beziworld\InPost\Exceptions\ApiException;
use Beziworld\InPost\Exceptions\InvalidConfiguration;
use Beziworld\InPost\Facades\InPost;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

const SHIPX = 'https://sandbox-api-shipx-pl.easypack24.net';

it('creates a locker shipment', function (): void {
    Http::fake([SHIPX . '/v1/organizations/1234/shipments' => Http::response([
        'href' => 'x', 'id' => 49, 'status' => 'created', 'tracking_number' => null, 'service' => 'inpost_locker_standard',
        'reference' => 'order-1001', 'custom_attributes' => ['target_point' => 'KRA010'], 'created_at' => '2026-11-04T12:00:00.000+01:00',
    ], 201)]);

    $shipment = InPost::createShipment(ShipmentRequest::toLocker('KRA010', 'jan@example.com', '500 100 200', 'medium', 'order-1001', codAmount: 129.99));

    expect($shipment->id)->toBe(49)
        ->and($shipment->status)->toBe('created')
        ->and($shipment->trackingNumber)->toBeNull()
        ->and($shipment->targetPoint)->toBe('KRA010')
        ->and($shipment->isConfirmed())->toBeFalse()
        ->and($shipment->isCancellable())->toBeTrue();

    Http::assertSent(fn(Request $request): bool => $request->hasHeader('Authorization', 'Bearer shipx-token')
        && $request->data() === [
            'service' => 'inpost_locker_standard',
            'receiver' => ['email' => 'jan@example.com', 'phone' => '500100200'],
            'parcels' => [['template' => 'medium']],
            'custom_attributes' => ['target_point' => 'KRA010'],
            'cod' => ['amount' => 129.99, 'currency' => 'PLN'],
            'insurance' => ['amount' => 129.99, 'currency' => 'PLN'],
            'reference' => 'order-1001',
        ]);
});

it('creates a courier shipment with dimensions', function (): void {
    Http::fake(['*' => Http::response(['id' => 50, 'status' => 'created'], 201)]);

    InPost::createShipment(new ShipmentRequest(
        service: Service::CourierStandard,
        receiver: new Peer(email: 'a@b.pl', phone: '500100200', companyName: 'ACME', address: new Address('Prosta', '1', 'Warszawa', '00-001')),
        parcels: [Parcel::dimensions(300, 200, 100, 2.5, 'p1'), Parcel::dimensions(100, 100, 100, 1, 'p2', nonStandard: true)],
        insuranceAmount: 500,
        additionalServices: ['sms', 'email'],
    ));

    Http::assertSent(fn(Request $request): bool => $request['receiver']['address'] === ['street' => 'Prosta', 'building_number' => '1', 'city' => 'Warszawa', 'post_code' => '00-001', 'country_code' => 'PL']
        && $request['parcels'][0] === ['dimensions' => ['length' => 300, 'width' => 200, 'height' => 100, 'unit' => 'mm'], 'weight' => ['amount' => 2.5, 'unit' => 'kg'], 'id' => 'p1']
        && $request['parcels'][1]['is_non_standard'] === true
        && $request['additional_services'] === ['sms', 'email']
        && ! isset($request['custom_attributes']));
});

it('validates shipment requests', function (): void {
    $receiver = new Peer(email: 'a@b.pl', phone: '500100200');

    expect(fn() => new ShipmentRequest(Service::LockerStandard, $receiver, [Parcel::template('small')]))->toThrow(InvalidArgumentException::class, 'target point')
        ->and(fn() => new ShipmentRequest(Service::CourierStandard, $receiver, []))->toThrow(InvalidArgumentException::class)
        ->and(fn() => new ShipmentRequest(Service::CourierStandard, $receiver, [Parcel::template('small')], reference: 'ab'))->toThrow(InvalidArgumentException::class)
        ->and(fn() => Parcel::template('huge'))->toThrow(InvalidArgumentException::class)
        ->and(fn() => Parcel::dimensions(0, 1, 1, 1))->toThrow(InvalidArgumentException::class);
});

it('reads, lists and cancels shipments', function (): void {
    Http::fake([
        SHIPX . '/v1/shipments/49' => Http::sequence()
            ->push(['id' => 49, 'status' => 'confirmed', 'tracking_number' => '620000000000000000000000'])
            ->push('', 204),
        SHIPX . '/v1/organizations/1234/shipments?*' => Http::response(['count' => 1, 'page' => 1, 'per_page' => 25, 'items' => [['id' => 49, 'status' => 'confirmed']]]),
    ]);

    $shipment = InPost::shipment(49);
    $list = InPost::shipments(['status' => 'confirmed']);
    InPost::cancelShipment(49);

    expect($shipment->isConfirmed())->toBeTrue()
        ->and($shipment->trackingNumber)->toBe('620000000000000000000000')
        ->and($list['count'])->toBe(1)
        ->and($list['items'][0]->id)->toBe(49);

    Http::assertSent(fn(Request $request): bool => str_contains($request->url(), 'shipments?status=confirmed&page=1&per_page=25'));
    Http::assertSent(fn(Request $request): bool => $request->method() === 'DELETE');
});

it('downloads labels', function (): void {
    Http::fake([
        SHIPX . '/v1/shipments/49/label*' => Http::response('%PDF-1.4 label', 200, ['Content-Type' => 'application/pdf']),
        SHIPX . '/v1/organizations/1234/shipments/labels' => Http::response('%PDF-1.4 labels'),
    ]);

    expect(InPost::label(49))->toBe('%PDF-1.4 label')
        ->and(InPost::labels([49, 50], 'zpl'))->toBe('%PDF-1.4 labels')
        ->and(fn() => InPost::label(49, 'png'))->toThrow(InvalidArgumentException::class)
        ->and(fn() => InPost::labels([]))->toThrow(InvalidArgumentException::class);

    Http::assertSent(fn(Request $request): bool => str_contains($request->url(), '/label?format=Pdf&type=A6'));
    Http::assertSent(fn(Request $request): bool => $request->data() === ['format' => 'zpl', 'type' => 'A6', 'shipment_ids' => [49, 50]]);
});

it('orders a courier pickup', function (): void {
    Http::fake(['*' => Http::response(['id' => 7, 'status' => 'new'], 201)]);

    InPost::createDispatchOrder([49], new Address('Prosta', '1', 'Warszawa', '00-001'), 'Jan', '500100200');
    InPost::createDispatchOrder([50], 'POINT-1');

    Http::assertSent(fn(Request $request): bool => $request['shipments'] === ['49'] && ($request['address']['city'] ?? null) === 'Warszawa' && ($request['name'] ?? null) === 'Jan');
    Http::assertSent(fn(Request $request): bool => ($request['dispatch_point_id'] ?? null) === 'POINT-1' && ! isset($request['address']));
});

it('tracks parcels without authentication', function (): void {
    Http::fake([SHIPX . '/v1/tracking/620*' => Http::response([
        'tracking_number' => '620', 'service' => 'inpost_locker_standard', 'status' => 'ready_to_pickup',
        'custom_attributes' => ['target_machine_id' => 'KRA010'],
        'tracking_details' => [
            ['status' => 'ready_to_pickup', 'origin_status' => 'STD', 'agency' => null, 'location' => 'KRA010', 'datetime' => '2026-11-04T10:00:00.000+01:00'],
            ['status' => 'confirmed', 'datetime' => '2026-11-03T10:00:00.000+01:00'],
        ],
    ])]);

    $tracking = InPost::tracking('620');

    expect($tracking->isReadyToPickup())->toBeTrue()
        ->and($tracking->targetPoint)->toBe('KRA010')
        ->and($tracking->events)->toHaveCount(2)
        ->and($tracking->events[0]['at']?->toDateString())->toBe('2026-11-04');

    Http::assertSent(fn(Request $request): bool => ! $request->hasHeader('Authorization'));
});

it('finds parcel lockers', function (): void {
    Http::fake([
        SHIPX . '/v1/points?*' => Http::response(['count' => 1, 'items' => [[
            'name' => 'KRA010', 'type' => ['parcel_locker'], 'status' => 'Operating',
            'location' => ['latitude' => 50.06, 'longitude' => 19.94], 'location_description' => 'Przy sklepie',
            'address_details' => ['city' => 'Kraków', 'post_code' => '30-001', 'street' => 'Długa', 'building_number' => '5'],
            'functions' => ['parcel_collect', 'parcel_send'], 'payment_available' => true, 'location_247' => true, 'distance' => 150,
        ]]]),
        SHIPX . '/v1/points/NOPE' => Http::response(['status' => 404, 'error' => 'resource_not_found'], 404),
    ]);

    $points = InPost::points(['relative_point' => '50.06,19.94', 'max_distance' => 2000, 'payment_available' => true]);

    expect($points[0]->name)->toBe('KRA010')
        ->and($points[0]->isOperating())->toBeTrue()
        ->and($points[0]->address())->toBe('Długa 5, 30-001 Kraków')
        ->and($points[0]->distance)->toBe(150)
        ->and($points[0]->open24h)->toBeTrue()
        ->and(InPost::point('NOPE'))->toBeNull();

    Http::assertSent(fn(Request $request): bool => str_contains($request->url(), 'payment_available=true') && ! $request->hasHeader('Authorization'));
});

it('lists statuses', function (): void {
    Http::fake(['*/v1/statuses*' => Http::response(['items' => [['name' => 'delivered', 'title' => 'Dostarczona', 'description' => '...']]])]);

    expect(InPost::statuses())->toBe(['delivered' => 'Dostarczona']);
});

it('maps errors reported with message or description', function (array $payload, string $expected): void {
    Http::fake(['*' => Http::response($payload, $payload['status'])]);

    expect(fn() => InPost::shipment(1))->toThrow(ApiException::class, $expected);
})->with([
    'live format' => [['status' => 401, 'error' => 'token_invalid', 'message' => 'Token is missing or invalid.', 'details' => []], 'InPost error token_invalid: Token is missing or invalid.'],
    'documented format' => [['status' => 400, 'error' => 'validation_failed', 'description' => 'Invalid data', 'details' => ['email' => ['invalid']]], 'InPost error validation_failed: Invalid data (email: invalid)'],
]);

it('wraps connection errors and requires credentials for protected calls', function (): void {
    Http::fake(fn() => throw new ConnectionException('timeout'));
    expect(fn() => InPost::tracking('1'))->toThrow(ApiException::class, 'Could not connect to InPost');

    config()->set('inpost.token', null);
    app()->forgetInstance(\Beziworld\InPost\InPostConfig::class);
    app()->forgetInstance(\Beziworld\InPost\InPostClient::class);
    InPost::clearResolvedInstances();

    expect(fn() => InPost::shipment(1))->toThrow(InvalidConfiguration::class, 'INPOST_TOKEN');
});

it('renders the geowidget', function (): void {
    $html = (string) InPost::geowidget('pickPoint', 'parcelCollectPayment');

    expect($html)->toContain('https://sandbox-easy-geowidget-sdk.easypack24.net/inpost-geowidget.js')
        ->toContain('<inpost-geowidget onpoint="pickPoint" token="geo-token" language="pl" config="parcelCollectPayment">');
});

describe('webhooks', function (): void {
    beforeEach(fn() => Event::fake([ShipmentStatusChanged::class]));

    it('answers the registration check', function (): void {
        $this->get('/inpost/webhook')->assertOk();
    });

    it('dispatches status changes from the InPost network', function (): void {
        $this->withServerVariables(['REMOTE_ADDR' => '91.216.25.10'])
            ->postJson('/inpost/webhook', ['event_ts' => '2026-11-04 15:08:06 +0100', 'event' => 'shipment_status_changed', 'organization_id' => 1234, 'payload' => ['shipment_id' => 49, 'status' => 'delivered', 'tracking_number' => '620']])
            ->assertOk();

        Event::assertDispatched(ShipmentStatusChanged::class, fn(ShipmentStatusChanged $e): bool => $e->shipmentId === 49 && $e->status === 'delivered' && ! $e->isConfirmed());
    });

    it('rejects requests from other addresses and malformed bodies', function (): void {
        $this->postJson('/inpost/webhook', ['event' => 'shipment_confirmed', 'payload' => ['shipment_id' => 49]])->assertForbidden();
        $this->withServerVariables(['REMOTE_ADDR' => '91.216.25.10'])->postJson('/inpost/webhook', ['event' => 'x'])->assertStatus(400);

        Event::assertNothingDispatched();
    });
});
