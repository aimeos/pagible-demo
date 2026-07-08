## PagibleAI demo

Demo installation using SQLite database and demo pages.

The demo can run as a stancl/tenancy domain-based SaaS setup. It creates one
tenant per installed theme and maps each non-default theme host to
`theme.themes.pagible.com`, with the default theme (`demo`) available at
`demo.pagible.com`.

| Host | Tenant | Theme |
| --- | --- | --- |
| `demo.pagible.com` | `demo` | `demo` |
| `glass.themes.pagible.com` | `glass` | `glass` |
| `paper.themes.pagible.com` | `paper` | `paper` |
| `premium.themes.pagible.com` | `premium` | `premium` |

Install or refresh the demo tenants with:

```bash
php artisan demo:install-tenants --migrate --force
```

The setup uses stancl's domain middleware in the `web` group and keeps Pagible
on shared tables with tenant isolation through the existing `tenant_id` columns.
Set `CMS_MULTIDOMAIN=true`, override `DEMO_TENANT_DOMAIN` for the default
tenant, and override `THEME_DOMAIN_SUFFIX` if your DNS suffix is different.

## License

PagibleAI CMS is open-sourced software licensed under the [LGPL3 license](https://opensource.org/license/lgpl-3-0).
The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/license/MIT).
