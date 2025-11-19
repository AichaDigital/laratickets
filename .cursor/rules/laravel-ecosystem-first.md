# Laravel Ecosystem First

## Principio Fundamental

**SIEMPRE usar las herramientas nativas de Laravel en lugar de librerías de terceros cuando Laravel provee la funcionalidad.**

Este es un paquete de Laravel y debe usar el ecosistema de Laravel de forma nativa y desacoplada.

## HTTP Requests

### ❌ INCORRECTO - Usar Guzzle directamente
```php
use GuzzleHttp\Client;

$client = new Client();
$response = $client->get('https://api.example.com/endpoint');
```

### ✅ CORRECTO - Usar Laravel Http
```php
use Illuminate\Support\Facades\Http;

$response = Http::get('https://api.example.com/endpoint');
// O con cliente configurado:
$response = Http::withHeaders([
    'Accept' => 'application/json',
])->timeout(30)->get('https://api.example.com/endpoint');
```

## Ventajas de Laravel Http

1. **Testing más fácil**: `Http::fake()` para mockear respuestas
2. **API más limpia**: Sintaxis fluent y expresiva
3. **Mejor manejo de errores**: Integración nativa con excepciones de Laravel
4. **Logging automático**: Se integra con el sistema de logs de Laravel
5. **Retry automático**: `Http::retry(3, 100)` para reintentos
6. **Pool de requests**: Para requests concurrentes
7. **Consistencia**: Mismo estilo que el resto del ecosistema Laravel

## Testing con Http Facade

### ❌ INCORRECTO - Mockear Guzzle
```php
$mock = new MockHandler([new Response(200, [], '{"data":"value"}')]);
$handlerStack = HandlerStack::create($mock);
$client = new Client(['handler' => $handlerStack]);
```

### ✅ CORRECTO - Usar Http::fake()
```php
Http::fake([
    'api.example.com/*' => Http::response(['data' => 'value'], 200),
]);

// El código del provider usa Http::get() normalmente
$result = $provider->verify('VAT123', 'ES');
```

## Otras Herramientas de Laravel a Preferir

- **Cache**: Usar `Cache` facade en lugar de Redis/Memcached directamente
- **Logs**: Usar `Log` facade en lugar de Monolog directamente
- **Validation**: Usar `Validator` facade o Form Requests
- **Events**: Usar sistema de eventos de Laravel
- **Queue**: Usar sistema de colas de Laravel
- **Storage**: Usar `Storage` facade en lugar de `league/flysystem` directamente

## ✅ TODO Completado

- [x] Migrar `IsvatProvider` de Guzzle a `Http` facade ✅
- [x] Migrar `ViesApiProvider` de Guzzle a `Http` facade ✅
- [x] Migrar `ViesRestProvider` de Guzzle a `Http` facade ✅
- [x] `ViesSoapProvider` usa SoapClient nativo de PHP (no requiere migración) ✅
- [x] Migrar `VatlayerProvider` de Guzzle a `Http` facade ✅
- [x] Actualizar todos los tests para usar `Http::fake()` en lugar de Guzzle MockHandler ✅
- [ ] Remover dependencia de `guzzlehttp/guzzle` del `composer.json` (si Laravel Http ya lo incluye como dependencia transitiva)

## Referencias

- [Laravel HTTP Client Documentation](https://laravel.com/docs/11.x/http-client)
- [Testing HTTP Requests](https://laravel.com/docs/11.x/http-client#testing)
