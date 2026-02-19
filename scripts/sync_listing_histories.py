import argparse
import os
from datetime import datetime

import pymysql


def parse_args():
    parser = argparse.ArgumentParser(
        description="Sync listing_price_history and listing_status_history from listings."
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Compute inserts without writing them.",
    )
    return parser.parse_args()


def now_str():
    return datetime.now().strftime("%Y-%m-%d %H:%M:%S")


def dt_str(value):
    if value is None:
        return now_str()
    if isinstance(value, datetime):
        return value.strftime("%Y-%m-%d %H:%M:%S")
    return str(value)


def decimal_key(value):
    if value is None:
        return None
    return str(value)


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

        cur.execute(
            """
            SELECT id, status, price_amount, currency, updated_at
            FROM listings
            ORDER BY id ASC
            """
        )
        listings = cur.fetchall()

        cur.execute(
            """
            SELECT p.listing_id, p.status, p.price_amount, p.currency
            FROM listing_price_history p
            INNER JOIN (
                SELECT listing_id, MAX(id) AS max_id
                FROM listing_price_history
                GROUP BY listing_id
            ) x ON x.max_id = p.id
            """
        )
        latest_price_map = {row["listing_id"]: row for row in cur.fetchall()}

        cur.execute(
            """
            SELECT s.listing_id, s.new_status
            FROM listing_status_history s
            INNER JOIN (
                SELECT listing_id, MAX(id) AS max_id
                FROM listing_status_history
                GROUP BY listing_id
            ) x ON x.max_id = s.id
            """
        )
        latest_status_map = {row["listing_id"]: row for row in cur.fetchall()}

        price_inserts = []
        status_inserts = []

        for row in listings:
            listing_id = row["id"]
            status = row["status"] or "unknown"
            price_amount = row["price_amount"]
            currency = (row["currency"] or "MXN")[:3]
            event_at = dt_str(row["updated_at"])
            created_at = now_str()

            last_price = latest_price_map.get(listing_id)
            needs_price = (
                last_price is None
                or (last_price["status"] or "unknown") != status
                or decimal_key(last_price["price_amount"]) != decimal_key(price_amount)
                or (last_price["currency"] or "MXN")[:3] != currency
            )
            if needs_price:
                price_inserts.append(
                    (
                        listing_id,
                        status,
                        price_amount,
                        currency,
                        event_at,
                        created_at,
                    )
                )

            last_status = latest_status_map.get(listing_id)
            if last_status is None:
                status_inserts.append((listing_id, None, status, event_at, created_at))
            else:
                prev = last_status["new_status"] or "unknown"
                if prev != status:
                    status_inserts.append((listing_id, prev, status, event_at, created_at))

        print(f"listings={len(listings)}")
        print(f"price_history_inserts={len(price_inserts)}")
        print(f"status_history_inserts={len(status_inserts)}")
        print(f"dry_run={args.dry_run}")

        if not args.dry_run:
            if price_inserts:
                cur.executemany(
                    """
                    INSERT INTO listing_price_history
                        (listing_id, status, price_amount, currency, captured_at, created_at)
                    VALUES (%s, %s, %s, %s, %s, %s)
                    """,
                    price_inserts,
                )
            if status_inserts:
                cur.executemany(
                    """
                    INSERT INTO listing_status_history
                        (listing_id, old_status, new_status, changed_at, created_at)
                    VALUES (%s, %s, %s, %s, %s)
                    """,
                    status_inserts,
                )
            conn.commit()

            cur.execute("SELECT COUNT(*) AS c FROM listing_price_history")
            print(f"listing_price_history_total={cur.fetchone()['c']}")
            cur.execute("SELECT COUNT(*) AS c FROM listing_status_history")
            print(f"listing_status_history_total={cur.fetchone()['c']}")

    finally:
        conn.close()


if __name__ == "__main__":
    main()
