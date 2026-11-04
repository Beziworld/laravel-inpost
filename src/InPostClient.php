<?php

declare(strict_types=1);

namespace Beziworld\InPost;

use Beziworld\InPost\Data\Address;
use Beziworld\InPost\Data\Point;
use Beziworld\InPost\Data\Shipment;
use Beziworld\InPost\Data\ShipmentRequest;
use Beziworld\InPost\Data\Tracking;
use Beziworld\InPost\Exceptions\ApiException;
use Beziworld\InPost\Exceptions\InvalidConfiguration;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;

/**
 * Client of the InPost ShipX API.
 *
 * Shipments, labels and dispatch orders need the API token and the
 * organization id; points, tracking and statuses are public.
 */
class InPostClient
{
    private const array LABEL_FORMATS = ['pdf' => 'Pdf', 'zpl' => 'Zpl', 'epl' => 'Epl'];

    public function __construct(
        private readonly HttpFactory $http,
        private readonly InPostConfig $config,
    ) {}

    public function config(): InPostConfig
    {
        return $this->config;
    }

    public function createShipment(ShipmentRequest $request): Shipment
    {
        return Shipment::fromArray($this->json($this->send('POST', $this->organizationPath('shipments'), json: $request->toArray())));
    }

    public function shipment(int $id): Shipment
    {
        return Shipment::fromArray($this->json($this->send('GET', 'v1/shipments/' . $id)));
    }

    /**
     * Shipments of the organization.
     *
     * @param array<string, scalar> $filters e.g. ['status' => 'confirmed', 'tracking_number' => '...', 'created_at_gteq' => '2026-11-01'].
     * @return array{items: list<Shipment>, count: int, page: int, perPage: int}
     */
    public function shipments(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $payload = $this->json($this->send('GET', $this->organizationPath('shipments'), query: $filters + ['page' => $page, 'per_page' => $perPage]));

        return [
            'items' => array_values(array_map(Shipment::fromArray(...), array_filter(is_array($payload['items'] ?? null) ? $payload['items'] : [], is_array(...)))),
            'count' => is_numeric($payload['count'] ?? null) ? (int) $payload['count'] : 0,
            'page' => is_numeric($payload['page'] ?? null) ? (int) $payload['page'] : $page,
            'perPage' => is_numeric($payload['per_page'] ?? null) ? (int) $payload['per_page'] : $perPage,
        ];
    }

    /**
     * Cancels a shipment that has not been confirmed yet (status created or offers_prepared).
     */
    public function cancelShipment(int $id): void
    {
        $this->send('DELETE', 'v1/shipments/' . $id);
    }

    /**
     * Label of a confirmed shipment as binary content.
     *
     * @param string $format "pdf", "zpl" or "epl".
     * @param string $type "A6", "normal" (locker A4) or "dpi300" (ZPL only).
     */
    public function label(int $shipmentId, string $format = 'pdf', string $type = 'A6'): string
    {
        return $this->send('GET', sprintf('v1/shipments/%d/label', $shipmentId), query: ['format' => self::labelFormat($format), 'type' => $type])->body();
    }

    /**
     * Labels of up to 100 shipments in one file (a ZIP archive when services are mixed).
     *
     * @param list<int> $shipmentIds
     */
    public function labels(array $shipmentIds, string $format = 'pdf', string $type = 'A6'): string
    {
        if ($shipmentIds === [] || count($shipmentIds) > 100) {
            throw new InvalidArgumentException('Between 1 and 100 shipment ids are required.');
        }

        return $this->send('POST', $this->organizationPath('shipments/labels'), json: [
            'format' => strtolower(self::labelFormat($format)),
            'type' => $type,
            'shipment_ids' => $shipmentIds,
        ])->body();
    }

    /**
     * Orders a courier pickup of confirmed shipments from the given address or dispatch point.
     *
     * @param list<int> $shipmentIds
     * @return array<array-key, mixed>
     */
    public function createDispatchOrder(array $shipmentIds, Address|string $from, ?string $name = null, ?string $phone = null, ?string $email = null, ?string $comment = null): array
    {
        $body = array_filter([
            'shipments' => array_map(strval(...), $shipmentIds),
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'comment' => $comment,
        ], static fn(mixed $value): bool => $value !== null);

        if ($from instanceof Address) {
            $body['address'] = $from->toArray();
        } else {
            $body['dispatch_point_id'] = $from;
        }

        return $this->json($this->send('POST', $this->organizationPath('dispatch_orders'), json: $body));
    }

    /**
     * Public tracking of a parcel (not available in the sandbox).
     */
    public function tracking(string $trackingNumber): Tracking
    {
        return Tracking::fromArray($this->json($this->send('GET', 'v1/tracking/' . rawurlencode($trackingNumber), authenticated: false)));
    }

    /**
     * Parcel lockers and pickup points.
     *
     * @param array<string, scalar> $filters e.g. ['city' => 'Kraków'], ['relative_point' => '50.06,19.94', 'max_distance' => 2000],
     *                                       ['type' => 'parcel_locker', 'payment_available' => 'true'].
     * @return list<Point>
     */
    public function points(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $query = array_map(static fn(mixed $value): mixed => is_bool($value) ? ($value ? 'true' : 'false') : $value, $filters)
            + ['page' => $page, 'per_page' => $perPage];
        $payload = $this->json($this->send('GET', 'v1/points', query: $query, authenticated: false));

        return array_values(array_map(Point::fromArray(...), array_filter(is_array($payload['items'] ?? null) ? $payload['items'] : [], is_array(...))));
    }

    /**
     * A single point by name (e.g. "KRA010"), or null when it does not exist.
     */
    public function point(string $name): ?Point
    {
        try {
            return Point::fromArray($this->json($this->send('GET', 'v1/points/' . rawurlencode($name), authenticated: false)));
        } catch (ApiException $exception) {
            if ($exception->httpStatus() === 404) {
                return null;
            }

            throw $exception;
        }
    }

    /**
     * Shipment statuses with their human readable titles.
     *
     * @return array<string, string>
     */
    public function statuses(string $language = 'pl_PL'): array
    {
        $payload = $this->json($this->send('GET', 'v1/statuses', query: ['lang' => $language], authenticated: false));
        $statuses = [];

        foreach (is_array($payload['items'] ?? null) ? $payload['items'] : [] as $item) {
            if (is_array($item) && is_string($item['name'] ?? null)) {
                $statuses[$item['name']] = is_string($item['title'] ?? null) ? $item['title'] : $item['name'];
            }
        }

        return $statuses;
    }

    /**
     * HTML of the Geowidget v5 parcel locker map.
     *
     * @param string $onPoint Name of the global JavaScript function receiving the selected point.
     * @param string $config "parcelCollect", "parcelCollectPayment" (cash on delivery), "parcelCollect247" or "parcelSend".
     */
    public function geowidget(string $onPoint = 'onInPostPointSelected', string $config = 'parcelCollect', string $language = 'pl'): HtmlString
    {
        $token = $this->config->geowidgetToken ?? throw InvalidConfiguration::missing('geowidget_token', 'INPOST_GEOWIDGET_TOKEN');
        $base = $this->config->sandbox ? 'https://sandbox-easy-geowidget-sdk.easypack24.net' : 'https://geowidget.inpost.pl';
        $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return new HtmlString(sprintf(
            '<link rel="stylesheet" href="%1$s/inpost-geowidget.css"/>'
            . '<script src="%1$s/inpost-geowidget.js" defer></script>'
            . '<inpost-geowidget onpoint="%2$s" token="%3$s" language="%4$s" config="%5$s"></inpost-geowidget>',
            $base,
            $escape($onPoint),
            $escape($token),
            $escape($language),
            $escape($config),
        ));
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed>|null $json
     */
    private function send(string $method, string $path, array $query = [], ?array $json = null, bool $authenticated = true): Response
    {
        $request = $this->request($authenticated);
        $options = [];

        if ($query !== []) {
            $options['query'] = $query;
        }

        if ($json !== null) {
            $options['json'] = $json;
        }

        try {
            $response = $request->send($method, $path, $options);
        } catch (ConnectionException $exception) {
            throw ApiException::transport($exception);
        }

        if ($response->failed()) {
            $payload = $response->json();

            throw ApiException::fromResponse(is_array($payload) ? $payload : [], $response->status());
        }

        return $response;
    }

    private function request(bool $authenticated): PendingRequest
    {
        $request = $this->http
            ->createPendingRequest()
            ->baseUrl($this->config->apiUrl() . '/')
            ->acceptJson()
            ->timeout($this->config->timeout)
            ->withHeaders(['X-User-Agent' => 'beziworld-laravel-inpost', 'X-User-Agent-Version' => '1.0']);

        if ($authenticated) {
            $request->withToken($this->config->token ?? throw InvalidConfiguration::missing('token', 'INPOST_TOKEN'));
        }

        return $request;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function json(Response $response): array
    {
        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    private function organizationPath(string $path): string
    {
        $organization = $this->config->organizationId ?? throw InvalidConfiguration::missing('organization_id', 'INPOST_ORGANIZATION_ID');

        return sprintf('v1/organizations/%s/%s', rawurlencode($organization), $path);
    }

    private static function labelFormat(string $format): string
    {
        return self::LABEL_FORMATS[strtolower($format)] ?? throw new InvalidArgumentException(sprintf('Unsupported label format "%s".', $format));
    }
}
