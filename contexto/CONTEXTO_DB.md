# CONTEXTO_DB.md — ValoraNL (Guía para IAs)

## 1) Propósito del proyecto
ValoraNL consolida publicaciones inmobiliarias de múltiples fuentes en una base canónica MySQL para:

- búsqueda y análisis de mercado,
- deduplicación entre portales,
- historial de precio/estatus,
- cálculo de valuaciones por comparables con homologación (alineado al Excel de referencia).

La BD canónica está definida en `scrapping/db/valoranl_schema.sql` y se llena desde SQLite usando `scrapping/scrapping/unify_to_mysql.py`.

---

## 2) Flujo de datos (alto nivel)

1. **Scrapers por fuente** generan SQLite local:
   - `casas365_propiedades.db`
   - `gpvivienda_nuevoleon.db`
   - `realtyworld_propiedades.db`
2. **Unificador** (`scrapping/unify_to_mysql.py`) transforma cada registro al modelo canónico:
   - Normaliza `property_type` a minúsculas canónicas (`"casa"`, `"departamento"`, `"terreno"`, etc.)
   - Normaliza `municipality` y `colony` a Title Case con aliases de NL (`"Sta. Catarina"` → `"Santa Catarina"`, `"Mty"` → `"Monterrey"`)
   - Calcula `age_years` desde `ano_construccion` (RealtyWorld) o por regex en descripción/título (todas las fuentes)
   - Valida precios: rechaza ventas < $100K o > $100M MXN, y PPU < $3,000 o > $80,000 /m²
3. Inserta/actualiza en MySQL con deduplicación por `dedupe_hash`.
4. Registra historial de precio y estatus cuando detecta cambios.
5. Desactiva listings no vistos en 30+ días (`--stale-days`).

> Estrategia: **ingesta incremental diaria** (no truncar tablas).

---

## 3) Esquema canónico MySQL (resumen)

### 3.1 Tabla `sources`
Catálogo de orígenes de scraping.

Campos clave:
- `source_code` (único, ej. `casas365`, `gpvivienda`, `realtyworld`)
- `source_name`
- `base_url`
- `is_active`

### 3.2 Tabla `listings`
Entidad principal normalizada (una publicación canónica).

#### Identidad y deduplicación
- `source_id`
- `source_listing_id`
- `url`, `url_normalized`
- `url_hash` (SHA-256 de URL normalizada)
- `fingerprint_hash` (fallback cuando no hay URL confiable)
- `dedupe_hash` (**UNIQUE**): hash efectivo para upsert

#### Estado comercial y precio
- `status` = `active|inactive|sold|unknown`
- `price_type` = `sale|rent|unknown`
- `price_amount`
- `currency`
- `maintenance_fee`

#### Datos físicos del inmueble
- `property_type` — **siempre en minúsculas** (`casa`, `departamento`, `terreno`, etc.)
- `area_construction_m2`
- `area_land_m2` — usado en factor de superficie de homologación
- `bedrooms`
- `bathrooms`
- `half_bathrooms`
- `parking`
- `floors`
- `age_years` — edad en años, calculada por el unificador desde año de construcción o descripción

#### Ubicación
- `street`
- `colony` — normalizada a Title Case, sin sufijos ruidosos
- `municipality` — normalizada a Title Case con aliases de NL
- `state`
- `country`
- `postal_code`
- `lat`, `lng`
- `geo_precision` = `exact|approx|colony|unknown`

#### Campos de contenido y preservación de fuente
- `title`
- `description`
- `images_json`
- `contact_json`
- `amenities_json`
- `details_json` (metadata parseada por fuente)
- `raw_json` (payload original para no perder información)

#### Trazabilidad temporal
- `source_first_seen_at`
- `source_last_seen_at`
- `seen_first_at`
- `seen_last_at`
- `created_at`
- `updated_at`

#### Índices de valuación
- `ix_listings_valuation_main` — (status, price_type, property_type, municipality, colony) para queries de comparables
- `ix_listings_valuation_area` — (status, price_type, property_type, municipality, area_construction_m2) para filtro por área
- `ix_listings_state_type` — (status, price_type, property_type, state) para fallback estatal
- `ix_listings_status_seen` — (status, seen_last_at) para desactivación de stale listings

### 3.3 Tabla `listing_price_history`
Historial de cambios de precio/estatus por listing.

Campos:
- `listing_id`
- `status`
- `price_amount`
- `currency`
- `captured_at`

### 3.4 Tabla `listing_status_history`
Historial de transiciones de estado.

Campos:
- `listing_id`
- `old_status`
- `new_status`
- `changed_at`

### 3.5 Tabla `valuations`
Registro de valuaciones realizadas desde la web (opcional, para análisis).

Campos:
- `request_json`, `result_json` — request/response completo
- `estimated_value`, `estimated_low`, `estimated_high`
- `ppu_aplicado`
- `confidence_score`, `location_scope`, `comparables_count`
- `municipality`, `colony`, `area_construction_m2`, `age_years`, `conservation_level`
- `created_at`

### 3.6 Tabla `valuation_comparables`
Comparables utilizados en cada valuación, con factores de homologación.

Campos:
- `valuation_id`, `listing_id`
- `ppu_bruto`, `ppu_homologado`
- `fre` (factor resultante de homologación)
- `similarity_score`

---

## 4) Reglas de deduplicación y upsert

El unificador calcula:

1. `url_hash` si hay URL normalizada.
2. `fingerprint_hash` con combinación aproximada: municipio + colonia + m2 + precio + recámaras.
3. `dedupe_hash = url_hash` o `fingerprint_hash` (fallback).

El `INSERT ... ON DUPLICATE KEY UPDATE` sobre `dedupe_hash` permite:
- insertar nuevos listings,
- actualizar existentes sin duplicar,
- refrescar `seen_last_at`.

---

## 5) Normalización de datos (convenciones)

### 5.1 property_type
Siempre minúsculas. Mapeo canónico:
- `casa`, `casas`, `house`, `residencia` → `casa`
- `departamento`, `depto`, `apartment` → `departamento`
- `terreno`, `lote`, `land` → `terreno`

### 5.2 municipality
Title Case + aliases de Nuevo León:
- `"Sta. Catarina"`, `"Sta Catarina"` → `"Santa Catarina"`
- `"Mty"`, `"Mty."` → `"Monterrey"`
- `"San Pedro"`, `"SPGG"` → `"San Pedro Garza García"`
- `"Gral. Escobedo"` → `"General Escobedo"`

### 5.3 colony
Title Case. Se eliminan sufijos ruidosos: `", Nuevo León"`, `", N.L."`.

### 5.4 age_years
Inferido por el unificador en este orden de prioridad:
1. Campo directo `ano_construccion` (RealtyWorld): `age = año_actual - ano_construccion`
2. Regex en descripción: `"construida en 2018"`, `"año de construcción: 2015"`
3. Regex en descripción: `"15 años de antigüedad"`
4. `NULL` si no se puede inferir

### 5.5 Validación de precios
Listings con precios fuera de rango se **omiten** (no se insertan):
- Venta: $100,000 - $100,000,000 MXN
- PPU: $3,000 - $80,000 /m²

---

## 6) Mapeo por fuente

### 6.1 Casas365 (`Casas365Mapper`)
- `titulo → title`, `precio → price_amount`
- `construccion_m2 → area_construction_m2`, `terreno_m2 → area_land_m2`
- `recamaras → bedrooms`, `banos → bathrooms`
- `ciudad → municipality` (normalizado), `colonia → colony` (normalizada)
- `tipo → property_type` (normalizado a minúsculas)
- `age_years` inferido de descripción/título
- Tiene coordenadas lat/lng

### 6.2 GPVivienda (`GPViviendaMapper`)
- `modelo/titulo → title`, `precio → price_amount`
- `m2_construidos → area_construction_m2`, `m2_terreno → area_land_m2`
- `fraccionamiento → colony` (normalizada), `ciudad → municipality` (normalizado)
- `property_type` = `"casa"` (fijo)
- `age_years` inferido de descripción/título
- Sin coordenadas lat/lng

### 6.3 RealtyWorld (`RealtyWorldMapper`)
- `property_id → source_listing_id`, `titulo → title`
- `construccion_m2 → area_construction_m2`, `terreno_m2 → area_land_m2`
- `medios_banos → half_bathrooms`
- `ano_construccion → age_years` (cálculo directo + fallback por descripción)
- `ciudad → municipality` (normalizado), `colonia → colony` (normalizada)
- `property_type` = `"casa"` (fijo)
- Sin coordenadas lat/lng

---

## 7) Ejecución operativa

### 7.1 Variables de entorno MySQL
- `MYSQL_HOST` (default: 127.0.0.1)
- `MYSQL_PORT` (default: 3306)
- `MYSQL_USER` (default: root)
- `MYSQL_PASSWORD` (default: vacío)
- `MYSQL_DATABASE` (default: valoranl)
- `LOG_LEVEL` (default: INFO)

### 7.2 Primera vez (BD nueva)
```bash
python scrapping/unify_to_mysql.py --init-schema db/valoranl_schema.sql --migrate
```

### 7.3 Migrar datos (incremental)
```bash
python scrapping/unify_to_mysql.py --migrate
```

### 7.4 Sin desactivar stale
```bash
python scrapping/unify_to_mysql.py --migrate --stale-days 0
```

### 7.5 Flujo diario recomendado
1. Ejecutar scrapers por fuente.
2. Ejecutar `--migrate`.
3. Revisar logs y resumen (listings insertados, actualizados, precios inválidos, stale desactivados).

---

## 8) Pipeline de valuación en la web

La web (`ValuationService`) consume la tabla `listings` así:

1. Filtra: `WHERE status='active' AND price_type='sale' AND property_type='casa' AND municipality=X`
2. Cascada de alcance: colonia → municipio → municipio ampliado → estado
3. Por cada comparable calcula factores de homologación: Zona × Ubicación × Superficie × Edad × Equipamiento × Negociación
4. PPU homologado = PPU bruto × FRe (factor resultante)
5. PPU aplicado = ROUND(AVERAGE(PPUs homologados), -1)
6. Valor = ROUNDUP(PPU aplicado × m² construcción, -3)
7. Rangos = ±10% del valor

**Campos críticos para valuación:**
- `area_construction_m2` — obligatorio (filtra comparables)
- `area_land_m2` — usado en factor de superficie (si disponible)
- `age_years` — usado en factor de edad por comparable
- `price_amount` — base del PPU
- `municipality`, `colony` — filtros de ubicación

---

## 9) Decisiones de diseño

- **No borrar históricos**: la señal temporal de precio y permanencia en mercado es crítica.
- Usar `listing_price_history` para curvas de precio por zona/tipo.
- Usar `seen_first_at`/`seen_last_at` para medir "tiempo en mercado".
- Mantener `raw_json` para enriquecer features futuras sin re-scrapear.
- Listings no vistos en 30+ días se marcan `inactive` automáticamente.
- Precios fuera de rango razonable se omiten en la ingesta.

---

## 10) Checklist para cualquier IA que trabaje este proyecto

1. No proponer truncar/borrar tablas diariamente.
2. Mantener enfoque incremental con historial.
3. Preservar campos no normalizados en JSON (`details_json`, `raw_json`).
4. No romper la deduplicación por `dedupe_hash`.
5. `property_type` siempre en minúsculas (`casa`, no `Casa`).
6. `municipality` y `colony` siempre normalizados (Title Case, aliases de NL).
7. Si se agregan columnas/tablas, documentar aquí y en SQL canónico.
8. Siempre validar impacto en valuación y trazabilidad temporal.
