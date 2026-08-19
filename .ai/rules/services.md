---
paths:
  - 'app/Services/**'
---

# Services

## Payload dolarapi es lista + CA bundle en Windows
dolarapi.com devuelve un array con `moneda`/`fuente`/`promedio` (fuente "oficial" = BCV); el formato viejo `{"usd":{"bcv":...}}` ya no se usa pero se sigue soportando. En Windows, si Guzzle falla con cURL error 60 (SSL), falta el CA bundle: descargar cacert.pem de curl.se y setear `curl.cainfo`/`openssl.cafile` en php.ini.

## Comparar fechas con whereDate, nunca where columna
El cast `'date'` de Eloquent guarda `Y-m-d H:i:s`, así que `where('rate_date', '<=', '2026-08-16')` falla por comparación de strings en SQLite (0 filas) y es frágil en MySQL. Usar siempre `whereDate('col', '<=', $date)` para rangos de fechas.
