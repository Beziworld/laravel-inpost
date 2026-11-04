<?php

declare(strict_types=1);

namespace Beziworld\InPost\Http\Controllers;

use Beziworld\InPost\Events\ShipmentStatusChanged;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Receives ShipX webhooks.
 *
 * GET answers 200 so that the URL can be registered in Manager Paczek. POST
 * requests are accepted only from the InPost address range, because ShipX
 * does not sign its webhooks.
 */
final class WebhookController
{
    public function __construct(
        private readonly Dispatcher $events,
        private readonly Config $config,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        if ($request->isMethod('GET')) {
            return new JsonResponse(['status' => 'ok']);
        }

        $allowed = $this->config->get('inpost.webhooks.allowed_ips', []);
        $ranges = is_array($allowed) ? array_values(array_filter($allowed, is_string(...))) : [];

        if ($ranges !== [] && ($request->ip() === null || ! IpUtils::checkIp($request->ip(), $ranges))) {
            return new JsonResponse(['status' => 'forbidden'], 403);
        }

        try {
            $data = json_decode($request->getContent(), true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return new JsonResponse(['status' => 'malformed'], 400);
        }

        $payload = is_array($data) && is_array($data['payload'] ?? null) ? $data['payload'] : [];
        $event = is_array($data) && is_string($data['event'] ?? null) ? $data['event'] : null;

        if ($event === null || ! is_numeric($payload['shipment_id'] ?? null)) {
            return new JsonResponse(['status' => 'malformed'], 400);
        }

        $this->events->dispatch(new ShipmentStatusChanged(
            event: $event,
            shipmentId: (int) $payload['shipment_id'],
            status: is_string($payload['status'] ?? null) ? $payload['status'] : null,
            trackingNumber: is_string($payload['tracking_number'] ?? null) ? $payload['tracking_number'] : null,
            payload: $payload,
        ));

        return new JsonResponse(['status' => 'ok']);
    }
}
