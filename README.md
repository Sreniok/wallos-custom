# Wallos Custom Build

This repository contains a custom Docker build of [Wallos](https://github.com/ellite/Wallos), an open-source personal subscription tracker.

## Credits

Wallos is created and maintained by the upstream Wallos project:

- Upstream repository: https://github.com/ellite/Wallos
- Website: https://wallosapp.com

This repository contains local customizations on top of Wallos, including dashboard modal actions, mobile/cache handling improvements, and Docker build setup.

## License

Wallos is licensed under the GNU General Public License v3.0. This modified version is distributed under the same license. See [LICENSE.md](LICENSE.md).

## Private Data

Runtime/private data is intentionally excluded from this repository:

- `db/`
- `logos/`
- `app/db/`
- `app/images/uploads/logos/`
- environment files and key material

Keep using Docker volumes for the database and uploaded logos.

## Requirements

- Docker
- Docker Compose v2, available as `docker compose`

## Install

Clone this repository:

```sh
git clone https://github.com/Sreniok/wallos-custom.git
cd wallos-custom
```

Create the persistent data folders:

```sh
mkdir -p db logos backups
```

Review `docker-compose.yml` before starting. By default this custom build:

- builds the image locally as `wallos-custom:latest`
- exposes Wallos on host port `8282`
- stores the SQLite database in `./db`
- stores uploaded logos in `./logos`
- stores scheduled backup archives in `./backups`
- sets the timezone with `TZ`

Start Wallos:

```sh
docker compose up -d --build wallos
```

Open Wallos in your browser:

```text
http://localhost:8282
```

If Wallos is running on another server, replace `localhost` with that server's IP address or domain.

## Updating

Pull the latest repository changes, rebuild, and restart:

```sh
git pull
docker compose up -d --build wallos
```

## Backups

Manual backups are available inside Wallos from:

```text
Settings -> Backup and Restore -> Backup
```

This build also creates a scheduled backup every day at `03:15` container time. By default, backup archives are written to:

```text
./backups
```

You can change the in-container backup path and retention in `docker-compose.yml`:

```yaml
environment:
  WALLOS_BACKUP_PATH: '/var/www/html/backups'
  WALLOS_BACKUP_RETENTION_DAYS: '30'
volumes:
  - './backups:/var/www/html/backups'
```

Backups include the SQLite database and uploaded logos. The `db/`, `logos/`, and `backups/` folders contain private Wallos data and are intentionally not committed to GitHub.

## Mobile/PWA Cache

This build includes cache improvements for the mobile/PWA experience. If your mobile app still shows an older version after updating, open Wallos Settings and use:

```text
Maintenance -> Clear browser cache and reload
```
