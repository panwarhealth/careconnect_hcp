# Database

MySQL 8.0 runs as the `db` service in `docker-compose.yml`. Data lives in
the named volume `db_data`, which is **not** cleared by `docker compose
down` — only by `docker compose down -v`.

## First-boot seed

Any `*.sql`, `*.sql.gz`, or `*.sh` file in `init/` is executed once, on
the first boot of a fresh `db_data` volume, via the MySQL image's
`/docker-entrypoint-initdb.d/` hook. Files run in alphabetical order, so
the dump is named `01-seed.sql` to leave room for future migrations
(`02-...`, `03-...`).

## Where does `01-seed.sql` come from?

- **Today:** copied out of the hosting provider's zip
  (`hcp.carepharma.com.au/database_2026-04-14.sql`) into `db/init/`.
- **Future:** pulled from Azure Blob Storage (Panwar Health tenant) via a
  helper script. See `docs/AZURE_STAGING.md` — the path stays the same,
  only the source changes.

The seed file is gitignored (`db/init/*.sql`) — dumps of a live WP site
contain user data and must never hit git.

## Re-seeding (nuke + rebuild)

```bash
docker compose down -v         # drops db_data
docker compose up -d           # db reimports 01-seed.sql on first boot
```

## Taking a fresh dump from the running container

```bash
docker compose exec -T db \
  mysqldump -u root -p"${MYSQL_ROOT_PASSWORD}" hcp_care > db/dumps/$(date +%Y-%m-%d).sql
```
