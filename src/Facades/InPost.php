<?php

declare(strict_types=1);

namespace Beziworld\InPost\Facades;

use Beziworld\InPost\InPostClient;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Beziworld\InPost\Data\Shipment createShipment(\Beziworld\InPost\Data\ShipmentRequest $request)
 * @method static \Beziworld\InPost\Data\Shipment shipment(int $id)
 * @method static array{items: list<\Beziworld\InPost\Data\Shipment>, count: int, page: int, perPage: int} shipments(array<string, scalar> $filters = [], int $page = 1, int $perPage = 25)
 * @method static void cancelShipment(int $id)
 * @method static string label(int $shipmentId, string $format = 'pdf', string $type = 'A6')
 * @method static string labels(list<int> $shipmentIds, string $format = 'pdf', string $type = 'A6')
 * @method static array<array-key, mixed> createDispatchOrder(list<int> $shipmentIds, \Beziworld\InPost\Data\Address|string $from, ?string $name = null, ?string $phone = null, ?string $email = null, ?string $comment = null)
 * @method static \Beziworld\InPost\Data\Tracking tracking(string $trackingNumber)
 * @method static list<\Beziworld\InPost\Data\Point> points(array<string, scalar> $filters = [], int $page = 1, int $perPage = 25)
 * @method static \Beziworld\InPost\Data\Point|null point(string $name)
 * @method static array<string, string> statuses(string $language = 'pl_PL')
 * @method static \Illuminate\Support\HtmlString geowidget(string $onPoint = 'onInPostPointSelected', string $config = 'parcelCollect', string $language = 'pl')
 *
 * @see InPostClient
 */
final class InPost extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return InPostClient::class;
    }
}
