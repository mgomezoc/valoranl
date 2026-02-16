-- ValoraNL — SQL Patch: Indices for improved comparable search
-- Date: 2026-02-15
-- Purpose: Support radius-based geo search (bounding box + Haversine)

-- Index for radius-based bounding box pre-filter on lat/lng.
-- Covers: WHERE status='active' AND price_type='sale' AND property_type='casa'
--         AND lat BETWEEN x1 AND x2 AND lng BETWEEN y1 AND y2
CREATE INDEX ix_listings_geo_search
    ON listings (status, price_type, property_type, lat, lng);
