-- ValoraNL - Integrity patch for listings deduplication
-- Date: 2026-02-19
-- Goal: prevent exact duplicate listings and expose possible semantic duplicates for review.

-- 1) Prevent duplicate listing identity per source (allows multiple NULL source_listing_id entries).
ALTER TABLE listings
    ADD UNIQUE KEY ux_listings_source_source_listing (source_id, source_listing_id);

-- 2) Prevent duplicate normalized URL hash (allows multiple NULL url_hash entries).
ALTER TABLE listings
    ADD UNIQUE KEY ux_listings_url_hash_unique (url_hash);

-- 3) Review view for potential semantic duplicates (manual curation required).
DROP VIEW IF EXISTS vw_listing_possible_duplicates;
CREATE VIEW vw_listing_possible_duplicates AS
SELECT
    municipality,
    colony,
    ROUND(area_construction_m2, 0) AS area_const_rounded,
    ROUND(price_amount, -3) AS price_rounded,
    COALESCE(bedrooms, -1) AS bedrooms_norm,
    COUNT(*) AS dup_count,
    MIN(id) AS canonical_listing_id,
    GROUP_CONCAT(id ORDER BY id ASC) AS listing_ids
FROM listings
WHERE municipality IS NOT NULL AND municipality <> ''
  AND colony IS NOT NULL AND colony <> ''
  AND area_construction_m2 IS NOT NULL AND area_construction_m2 > 0
  AND price_amount IS NOT NULL AND price_amount > 0
GROUP BY municipality, colony, area_const_rounded, price_rounded, bedrooms_norm
HAVING COUNT(*) > 1;
