# CONTEXTO DEL PROYECTO (Humanos + IAs + GitHub)

Fecha de corte: 2026-02-17 (actualizado post-normalizacion `property_type`)  
Repositorio: `C:\laragon\www\ValoraNL\valoranl`  
Branch actual: `master`

## 1) Contexto para humanos

### 1.1 Que es este proyecto
ValoraNL es una aplicacion web en CodeIgniter 4 para estimar valor de casas en Nuevo Leon con:
- comparables de la tabla `listings`
- homologacion de PPU (precio por m2)
- ajuste Ross-Heidecke
- rango de valor (+/-10%)
- score de confianza

Flujo principal:
- `GET /` muestra formulario (`app/Views/home.php`)
- `POST /valuacion/estimar` calcula valuacion (`app/Controllers/Home.php` -> `app/Services/ValuationService.php`)

### 1.2 Stack y componentes clave
- Backend: PHP 8.1 + CodeIgniter 4
- Base de datos: MySQL (`valoranl`)
- Frontend: Bootstrap 5 + jQuery (`public/assets/js/home-valuation.js`)
- Integraciones externas:
- ChartisMX API para municipios/colonias
- Nominatim (OpenStreetMap) para geocodificacion
- OpenAI (fallback cuando faltan comparables)

### 1.3 Estado real de la base de datos (medido)
Tablas detectadas:
- `sources` (4)
- `listings` (1271)
- `listing_price_history` (0)
- `listing_status_history` (0)
- `valuations` (0)
- `valuation_comparables` (0)

Calidad y distribucion actual:
- `listings` total: 1271
- activos: 1271
- `price_type='sale'`: 1271
- con `price_amount`: 1271
- con `area_construction_m2 > 0`: 1244
- sin `lat/lng`: 1271
- municipio/colonia nulos o vacios: 0

Top municipios por volumen:
- Monterrey (600)
- SANTIAGO (291)
- SAN PEDRO GARZA GARCIA (131)
- GARCIA (62)
- GUADALUPE (48)

### 1.4 Riesgos/hallazgos importantes
1. Integridad de tipo de propiedad (resuelto):
- Backend filtra comparables por `property_type='casa'` (`Home::estimate` fuerza `casa`).
- Se aplico normalizacion directa en BD: `house -> casa`.
- Estado actual: `property_type='casa'` en 1271/1271, `house` en 0.
- Pool usable para valuacion (con precio y m2): 1244 registros.

2. Geolocalizacion no disponible:
- 1271/1271 listings sin `lat/lng`.
- Impacta busqueda por radio (`radius_1km`, `radius_2km`, `radius_3km`) y precision espacial.

3. Historial/telemetria de valuaciones vacio:
- `valuations` y `valuation_comparables` en 0.
- Sin historico de resultados para auditoria, mejora de modelo o analitica.

4. Tests no ejecutables en este entorno:
- `vendor/bin/phpunit` falla (28/28 errores) por falta de extension PHP `intl` (`Class "Locale" not found`).

## 2) Contexto para IAs (reglas de trabajo)

### 2.1 Archivos de verdad para decisiones tecnicas
- Flujo HTTP: `app/Config/Routes.php`
- Entrada y validacion: `app/Controllers/Home.php`
- Motor de valuacion: `app/Services/ValuationService.php`
- Formula/matematica: `app/Libraries/ValuationMath.php`
- Fallback IA: `app/Libraries/OpenAiValuationService.php`
- Config de factores: `app/Config/Valuation.php`
- Cliente frontend de valuacion: `public/assets/js/home-valuation.js`

### 2.2 Suposiciones que NO deben hacerse
- No asumir que futuras ingestas ya vienen normalizadas; hoy la BD esta en `casa`, pero se debe mantener esta regla en pipeline.
- No asumir disponibilidad de coordenadas en `listings`.
- No asumir que hay historico en `valuations`.
- No asumir que tests pasan localmente sin instalar `intl`.

### 2.3 Prioridad tecnica recomendada para proximos cambios
1. Decidir estrategia de geo:
- poblar `lat/lng` en ingesta, o
- desactivar scopes por radio cuando no haya geodatos.
2. Persistir resultados de valuacion en `valuations` y `valuation_comparables`.
3. Arreglar entorno de pruebas (habilitar extension `intl`) y volver a correr suite.

## 3) Contexto para GitHub

### 3.1 Estado de repo
- Branch: `master`
- Working tree: limpio al momento de generar este documento

### 3.2 Checklist minimo para PRs
- [ ] Confirmar que cambios en valuacion respetan `ValuationMath` + `Config\Valuation`.
- [ ] Incluir impacto en BD (si aplica) y actualizar docs en `contexto/`.
- [ ] Validar flujo de UI (`/` -> submit -> resultado) manualmente.
- [ ] Ejecutar tests (o documentar bloqueo de entorno, p. ej. falta `intl`).
- [ ] Explicar riesgos funcionales (comparables, confianza, fallback IA).

### 3.3 Issues recomendados (orden)
1. `DATA`: ausencia de `lat/lng` invalida scopes por radio.
2. `OPS`: habilitar `intl` para restaurar ejecucion de PHPUnit.
3. `FEATURE`: guardar cada valuacion en `valuations` + comparables usados.
4. `PIPELINE`: asegurar normalizacion de `property_type` a `casa` en cada migracion para evitar regresion.

## 4) Fuentes internas usadas para este contexto
- `README.md`
- `CLAUDE.md`
- `contexto/ValoraNL_Contexto_Maestro_IA.md`
- `contexto/CONTEXTO_DB.md`
- `app/Config/Routes.php`
- `app/Controllers/Home.php`
- `app/Services/ValuationService.php`
- `app/Libraries/ValuationMath.php`
- `app/Libraries/OpenAiValuationService.php`
- `app/Config/Valuation.php`
- `app/Models/ListingModel.php`
- `public/assets/js/home-valuation.js`
- Metadatos y consultas SQL directas a MySQL `valoranl` (information_schema + conteos operativos)
