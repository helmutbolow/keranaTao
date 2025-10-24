# Kerana Admin (PHP + Postgres)

## Run
```bash
docker compose up -d
# Open: http://localhost:8080
```
The DB is precreated (`kerana`/`kerana`) with empty tables. Start by creating records in reference tables like `customers`, `banks`, `employees`, `contracts`, then use `employee_contracts`, `timesheets`, `invoices_out`, etc.

## Notes
- Generic CRUD generator using Postgres reflection.
- Convention-based relations: any column named `*_uuid` becomes a `<select>` against the table plural of `*` if it has a `uuid` column.
- Label heuristic: uses columns such as `customer`, `employee`, `supplier_name`, `bank`, `name`, or falls back to `uuid`.
- Mobile-first, minimal CSS (no external CDN).

## Access on local network
If your Docker host IP is `192.168.x.x`, browse `http://<host-ip>:8080` from other devices on the LAN.
