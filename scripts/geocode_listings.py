import argparse
import json
import os
import time
import urllib.parse
import urllib.request

import pymysql

NOMINATIM_URL = "https://nominatim.openstreetmap.org/search"
DEFAULT_USER_AGENT = "ValoraNL-Geocoder/1.0 (contact: admin@valoranl.local)"


def norm(value):
    if value is None:
        return ""
    return " ".join(str(value).strip().split())


def build_queries(street, colony, municipality, postal_code):
    street = norm(street)
    colony = norm(colony)
    municipality = norm(municipality)
    postal_code = norm(postal_code)

    candidates = []
    if street and colony and municipality:
        candidates.append((f"{street}, {colony}, {municipality}, Nuevo Leon, Mexico", "approx"))
    if street and municipality:
        candidates.append((f"{street}, {municipality}, Nuevo Leon, Mexico", "approx"))
    if colony and municipality:
        candidates.append((f"{colony}, {municipality}, Nuevo Leon, Mexico", "colony"))
    if postal_code and municipality:
        candidates.append((f"{postal_code}, {municipality}, Nuevo Leon, Mexico", "colony"))
    if municipality:
        candidates.append((f"{municipality}, Nuevo Leon, Mexico", "colony"))

    seen = set()
    out = []
    for query, precision in candidates:
        key = query.lower()
        if key in seen:
            continue
        seen.add(key)
        out.append((query, precision))
    return out


def geocode(query, user_agent, timeout=30):
    params = urllib.parse.urlencode(
        {
            "q": query,
            "format": "jsonv2",
            "limit": 1,
            "countrycodes": "mx",
            "addressdetails": 0,
        }
    )
    req = urllib.request.Request(
        f"{NOMINATIM_URL}?{params}",
        headers={"User-Agent": user_agent},
    )
    with urllib.request.urlopen(req, timeout=timeout) as resp:
        payload = json.loads(resp.read().decode("utf-8"))
    if not payload:
        return None
    first = payload[0]
    return float(first["lat"]), float(first["lon"])


def parse_args():
    parser = argparse.ArgumentParser(description="Geocode listings without coordinates.")
    parser.add_argument("--sleep", type=float, default=1.1, help="Seconds between HTTP requests.")
    parser.add_argument("--limit", type=int, default=0, help="Max rows to process (0 = all).")
    parser.add_argument(
        "--user-agent",
        type=str,
        default=DEFAULT_USER_AGENT,
        help="User-Agent for Nominatim requests.",
    )
    return parser.parse_args()


def main():
    args = parse_args()

    conn = pymysql.connect(
        host=os.getenv("MYSQL_HOST", "127.0.0.1"),
        port=int(os.getenv("MYSQL_PORT", "3306")),
        user=os.getenv("MYSQL_USER", "root"),
        password=os.getenv("MYSQL_PASSWORD", ""),
        database=os.getenv("MYSQL_DATABASE", "valoranl"),
        autocommit=False,
    )

    try:
        cur = conn.cursor(pymysql.cursors.DictCursor)

        sql = """
            SELECT id, street, colony, municipality, postal_code
            FROM listings
            WHERE lat IS NULL OR lng IS NULL
            ORDER BY id ASC
        """
        if args.limit > 0:
            sql += " LIMIT %s"
            cur.execute(sql, (args.limit,))
        else:
            cur.execute(sql)
        rows = cur.fetchall()

        print(f"rows_missing_before={len(rows)}")
        if not rows:
            return

        groups = {}
        for row in rows:
            key = (
                norm(row["street"]),
                norm(row["colony"]),
                norm(row["municipality"]),
                norm(row["postal_code"]),
            )
            groups.setdefault(key, []).append(row["id"])

        print(f"unique_location_groups={len(groups)}")

        cache = {}
        processed_groups = 0
        resolved_groups = 0
        unresolved_groups = 0
        updated_rows = 0

        update_sql = """
            UPDATE listings
            SET lat=%s, lng=%s, geo_precision=%s
            WHERE id=%s AND (lat IS NULL OR lng IS NULL)
        """

        for (street, colony, municipality, postal_code), ids in groups.items():
            processed_groups += 1
            hit = None
            precision = "unknown"

            for query, query_precision in build_queries(street, colony, municipality, postal_code):
                key = query.lower()
                if key in cache:
                    result = cache[key]
                else:
                    try:
                        result = geocode(query, args.user_agent)
                    except Exception:
                        result = None
                    cache[key] = result
                    time.sleep(args.sleep)

                if result is not None:
                    hit = result
                    precision = query_precision
                    break

            if hit is None:
                unresolved_groups += 1
            else:
                resolved_groups += 1
                lat, lng = hit
                for listing_id in ids:
                    cur.execute(update_sql, (lat, lng, precision, listing_id))
                    updated_rows += cur.rowcount

            if processed_groups % 20 == 0 or processed_groups == len(groups):
                conn.commit()
                print(
                    f"progress_groups={processed_groups}/{len(groups)} "
                    f"resolved={resolved_groups} unresolved={unresolved_groups} updated_rows={updated_rows}"
                )

        conn.commit()

        cur.execute("SELECT COUNT(*) AS c FROM listings WHERE lat IS NULL OR lng IS NULL")
        missing_after = cur.fetchone()["c"]
        cur.execute(
            """
            SELECT geo_precision, COUNT(*) AS c
            FROM listings
            GROUP BY geo_precision
            ORDER BY c DESC
            """
        )
        precision_rows = cur.fetchall()

        print(f"updated_rows_total={updated_rows}")
        print(f"rows_missing_after={missing_after}")
        print("geo_precision_distribution=", precision_rows)

    finally:
        conn.close()


if __name__ == "__main__":
    main()
