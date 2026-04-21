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

## Build

```sh
docker compose up -d --build wallos
```
