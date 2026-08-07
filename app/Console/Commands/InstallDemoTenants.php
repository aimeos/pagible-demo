<?php

namespace App\Console\Commands;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Tenancy as CmsTenancy;
use App\Models\Tenant;
use Aimeos\Cms\Commands\Demo;
use Illuminate\Console\Command;

class InstallDemoTenants extends Command
{
    private const THEME_PACKAGE_PREFIX = 'pagible-themes-';
    private const DEFAULT_TENANT_ID = 'demo.pagible.com';
    private const DEFAULT_TENANT_THEME = 'demo';

    protected $signature = 'demo:install-tenants
        {--tenant=* : Limit the install to one or more configured tenant IDs}
        {--migrate : Run database migrations before seeding}
        {--force : Force migrations and seeding in production}';

    protected $description = 'Create stancl tenants/domains and seed one Pagible theme per tenant';

    public function handle(): int
    {
        if ($this->laravel->isProduction() && ! $this->option('force')) {
            $this->error('Use --force to install demo tenants in production.');

            return self::FAILURE;
        }

        if ($this->option('migrate')) {
            $result = $this->call('migrate', ['--force' => true]);

            if ($result !== self::SUCCESS) {
                return $result;
            }
        }

        $tenants = $this->selectedTenants();

        if ($tenants === []) {
            $this->error('No demo tenants are configured.');

            return self::FAILURE;
        }

        foreach ($tenants as $tenantConfig) {
            $tenant = Tenant::query()->updateOrCreate(
                ['id' => $tenantConfig['id']],
                ['theme' => $tenantConfig['theme']],
            );

            $tenant->domains()->updateOrCreate(
                ['domain' => $tenantConfig['domain']],
                ['domain' => $tenantConfig['domain']],
            );

            $this->line(sprintf(
                'Seeding [%s] at [%s] with theme [%s] ...',
                $tenantConfig['id'],
                $tenantConfig['domain'],
                $tenantConfig['theme'],
            ));

            $this->seedTenant($tenantConfig);
            $this->updatePageDomains($tenantConfig);
        }

        $this->newLine();
        $this->info('Demo tenants installed.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{id: string, domain: string, theme: string}>
     */
    private function selectedTenants(): array
    {
        $tenants = $this->configuredTenants();
        $selected = array_filter(array_unique(array_map(
            fn (string $tenant): string => $this->normalizeTenantSelector($tenant),
            array_map('strval', (array) $this->option('tenant'))
        )));

        if ($selected === []) {
            return array_values($tenants);
        }

        $unknown = array_diff($selected, array_keys($tenants));

        if ($unknown !== []) {
            $this->error('Unknown demo tenant(s): ' . implode(', ', $unknown));

            return [];
        }

        return array_values(array_intersect_key($tenants, array_flip($selected)));
    }

    /**
     * Normalize tenant selector input.
     *
     * @param string $tenant Tenant selector from CLI input.
     * @return string Normalized tenant id.
     */
    private function normalizeTenantSelector(string $tenant): string
    {
        $value = strtolower(trim($tenant));

        if ($value === '' || str_contains($value, "\0")) {
            return '';
        }

        $parts = parse_url($value);
        $candidate = strtolower((string) ($parts['host'] ?? $parts['path'] ?? $value));
        $candidate = preg_replace('/:\d+$/', '', $candidate) ?? '';

        return $candidate === $value ? $value : $candidate;
    }

    /**
     * @return array<string, array{id: string, domain: string, theme: string}>
     */
    private function configuredTenants(): array
    {
        $tenants = [];
        $defaultTenantId = $this->defaultTenantId();
        $defaultDomain = $this->tenantDomain($defaultTenantId);

        $tenants[$defaultTenantId] = [
            'id' => $defaultTenantId,
            'domain' => preg_replace('/:\\d+$/', '', $defaultDomain) ?: $defaultDomain,
            'theme' => self::DEFAULT_TENANT_THEME,
        ];

        foreach ($this->installedThemes() as $tenantId) {
            if (isset($tenants[$tenantId])) {
                continue;
            }

            $domain = $this->tenantDomain($tenantId);

            $tenants[$tenantId] = [
                'id' => $tenantId,
                'domain' => preg_replace('/:\d+$/', '', $domain) ?: $domain,
                'theme' => $tenantId,
            ];
        }

        return $tenants;
    }

    /**
     * @return array<int, string>
     */
    private function installedThemes(): array
    {
        $themes = [];
        $paths = glob(base_path('vendor/aimeos/' . self::THEME_PACKAGE_PREFIX . '*'), GLOB_ONLYDIR) ?: [];

        foreach ($paths as $path) {
            $slug = strtolower(trim(str_replace(self::THEME_PACKAGE_PREFIX, '', basename($path))));

            if ($slug !== '') {
                $themes[] = $slug;
            }
        }

        sort($themes, SORT_STRING);

        return array_values(array_unique($themes));
    }

    private function tenantDomainSuffix(): string
    {
        return strtolower(trim((string) env('THEME_DOMAIN_SUFFIX', 'themes.pagible.com'), ".\t\n\r\0\x0B "));
    }

    private function defaultTenantId(): string
    {
        return strtolower(trim((string) env('DEMO_TENANT_DOMAIN', self::DEFAULT_TENANT_ID)));
    }

    private function tenantDomain(string $tenantId): string
    {
        $tenantId = strtolower(trim($tenantId));

        if (str_contains($tenantId, '.')) {
            return $tenantId;
        }

        return sprintf('%s.%s', $tenantId, $this->tenantDomainSuffix());
    }

    /**
     * @param array{id: string, domain: string, theme: string} $tenant
     */
    private function seedTenant(array $tenant): void
    {
        $this->setEnv('CMS_TENANT', $tenant['id']);
        $this->setEnv('CMS_THEME', $tenant['theme']);
        $this->refreshCmsTenant($tenant['id']);

        Demo::make($tenant['theme'], $tenant['id'])->seed();
    }

    /**
     * @param array{id: string, domain: string, theme: string} $tenant
     */
    private function updatePageDomains(array $tenant): void
    {
        $this->refreshCmsTenant($tenant['id']);

        Page::withoutSyncingToSearch(function () use ($tenant): void {
            Page::withoutTenancy()
                ->where('tenant_id', $tenant['id'])
                ->where('editor', 'demo')
                ->update([
                    'domain' => $tenant['domain'],
                    'theme' => $tenant['theme'],
                ]);
        });

        Version::withoutTenancy()
            ->where('tenant_id', $tenant['id'])
            ->where('versionable_type', Page::class)
            ->each(function (Version $version) use ($tenant): void {
                $data = $version->data ?? new \stdClass();
                $data->domain = $tenant['domain'];
                $data->theme = $tenant['theme'];

                $version->forceFill(['data' => $data])->saveQuietly();
            });

        Page::query()->searchable();
    }

    private function refreshCmsTenant(string $tenantId): void
    {
        CmsTenancy::$callback = static fn (): string => $tenantId;
        $this->laravel->forgetInstance(CmsTenancy::class);
    }

    private function setEnv(string $key, string $value): void
    {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
