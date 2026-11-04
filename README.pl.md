# Laravel InPost

[InPost ShipX](https://inpost.pl) dla Laravela: przesyłki do Paczkomatów i kurierem, etykiety, zamawianie kuriera,
śledzenie, wyszukiwanie punktów, mapa Geowidget i webhooki statusów.

[English documentation](README.md)

## Instalacja

```bash
composer require beziworld/laravel-inpost
```

```dotenv
INPOST_TOKEN=token-shipx                # Manager Paczek > Moje konto > API
INPOST_ORGANIZATION_ID=12345
INPOST_SANDBOX=true
INPOST_GEOWIDGET_TOKEN=publiczny-token-geowidgetu
```

Punkty, śledzenie i statusy są publiczne i działają bez tokenu.

## Wybór Paczkomatu w koszyku

```blade
{{ InPost::geowidget('onInPostPointSelected', 'parcelCollect') }}
<input type="hidden" name="paczkomat" id="paczkomat">
<script>
    document.addEventListener('onInPostPointSelected', (e) => document.getElementById('paczkomat').value = e.detail.name);
</script>
```

## Przesyłki

```php
use Beziworld\InPost\Data\ShipmentRequest;
use Beziworld\InPost\Facades\InPost;

$przesylka = InPost::createShipment(ShipmentRequest::toLocker(
    targetPoint: $zamowienie->paczkomat,
    email: $zamowienie->email,
    phone: $zamowienie->telefon,
    template: 'medium',                  // gabaryt B
    reference: $zamowienie->numer,
    codAmount: $zamowienie->pobranie ? $zamowienie->suma : null,
));

// Utworzenie jest asynchroniczne: etykieta jest dostępna po potwierdzeniu przesyłki.
if (InPost::shipment($przesylka->id)->isConfirmed()) {
    $pdf = InPost::label($przesylka->id);
}
```

## Śledzenie i punkty

```php
InPost::tracking('620000000000000000000000')->status;      // "ready_to_pickup"
InPost::points(['relative_point' => '50.0614,19.9366', 'max_distance' => 1000]);
InPost::point('KRA010');
```

## Webhooki

Zarejestruj `https://twoja-aplikacja.pl/inpost/webhook` w Managerze Paczek. Pakiet odpowiada na kontrolę GET,
przyjmuje POST tylko z sieci InPost (91.216.25.0/24) i wysyła event `ShipmentStatusChanged`. Webhooki ShipX nie są
podpisane, więc ważne zmiany potwierdzaj przez `InPost::shipment($id)`.

## Autor

Pakiet tworzy i rozwija **Kamil Ćwiertnia**, [beziworld.eu](https://beziworld.eu), [biuro@beziworld.eu](mailto:biuro@beziworld.eu).
Potrzebujesz integracji wysyłek w sklepie? Zapraszam do kontaktu przez [beziworld.eu](https://beziworld.eu).

## Licencja

MIT. Pakiet nie jest powiązany z InPost S.A.
