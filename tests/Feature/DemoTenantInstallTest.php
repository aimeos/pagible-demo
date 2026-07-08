<?php

namespace Tests\Feature;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoTenantInstallTest extends TestCase
{
    use RefreshDatabase;

    private const DEFAULT_TENANT_ID = 'demo.pagible.com';
    private const DEFAULT_TENANT_THEME = 'demo';

    public function test_it_installs_a_pagible_theme_in_a_stancl_domain_tenant(): void
    {
        $tenant = $this->firstTheme();

        if ($tenant === null) {
            $this->markTestSkipped('No installable themes were detected.');
        }

        $this->assertNotNull($tenant, 'No installed themes were detected.');
        $domain = $this->tenantDomain($tenant);

        $this->artisan('demo:install-tenants', [
            '--tenant' => [$tenant],
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('tenants', ['id' => $tenant]);
        $this->assertDatabaseHas('domains', [
            'tenant_id' => $tenant,
            'domain' => $domain,
        ]);
        $this->assertDatabaseHas('cms_pages', [
            'tenant_id' => $tenant,
            'domain' => $domain,
            'theme' => $tenant,
            'path' => '',
        ]);

        $version = Version::withoutTenancy()
            ->where('tenant_id', $tenant)
            ->where('versionable_type', Page::class)
            ->whereNotNull('versionable_id')
            ->firstOrFail();

        $this->assertSame($domain, $version->data->domain);
        $this->assertSame($tenant, $version->data->theme);
    }

    public function test_tenant_domain_renders_the_tenant_theme(): void
    {
        $tenant = $this->firstTheme();

        if ($tenant === null || $tenant === self::DEFAULT_TENANT_THEME) {
            $this->markTestSkipped('No non-default installable themes were detected for a route-render check.');
        }

        $this->assertNotNull($tenant, 'No installed themes were detected.');
        $domain = $this->tenantDomain($tenant);

        $this->artisan('demo:install-tenants', [
            '--tenant' => [$tenant],
            '--force' => true,
        ])->assertExitCode(0);

        $this->get('http://' . $domain . '/')
            ->assertOk()
            ->assertSee('PagibleAI CMS')
            ->assertSee($this->expectedThemeAsset($tenant), false);
    }

    public function test_default_tenant_is_available_on_demo_domain(): void
    {
        $tenant = self::DEFAULT_TENANT_ID;
        $domain = $this->tenantDomain($tenant);

        $this->artisan('demo:install-tenants', [
            '--tenant' => [$tenant],
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('tenants', ['id' => $tenant]);
        $this->assertDatabaseHas('domains', [
            'tenant_id' => $tenant,
            'domain' => $domain,
        ]);

        $this->get('http://' . $domain . '/')
            ->assertOk()
            ->assertSee('PagibleAI CMS')
            ->assertSee($this->expectedThemeAsset(self::DEFAULT_TENANT_THEME), false);
    }

    public function test_default_tenant_can_be_selected_with_the_demo_domain(): void
    {
        $domain = $this->tenantDomain(self::DEFAULT_TENANT_ID);

        $this->artisan('demo:install-tenants', [
            '--tenant' => [$domain],
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('tenants', ['id' => self::DEFAULT_TENANT_ID]);
    }

    public function test_can_select_default_tenant_with_explicit_default_id(): void
    {
        $tenant = self::DEFAULT_TENANT_ID;

        $this->artisan('demo:install-tenants', [
            '--tenant' => [$tenant],
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('tenants', ['id' => self::DEFAULT_TENANT_ID]);
    }

    private function firstTheme(): ?string
    {
        $themes = $this->installedThemes();
        foreach ($themes as $theme) {
            if ($theme !== self::DEFAULT_TENANT_THEME) {
                return $theme;
            }
        }

        return $themes[0] ?? null;
    }

    private function tenantDomain(string $tenant): string
    {
        $value = strtolower(trim($tenant));
        if (str_contains($value, '.')) {
            return $value;
        }

        return strtolower($tenant) . '.' . env('THEME_DOMAIN_SUFFIX', 'themes.pagible.com');
    }

    /**
     * @return array<int, string>
     */
    private function installedThemes(): array
    {
        $paths = glob(base_path('vendor/aimeos/pagible-themes-*'), GLOB_ONLYDIR) ?: [];
        $themes = [];

        foreach ($paths as $path) {
            $slug = strtolower(trim(str_replace('pagible-themes-', '', basename($path))));
            if ($slug !== '') {
                $themes[] = $slug;
            }
        }

        sort($themes, SORT_STRING);
        return array_values(array_unique($themes));
    }

    private function expectedThemeAsset(string $tenant): string
    {
        if ($tenant === self::DEFAULT_TENANT_THEME) {
            return 'vendor/cms/theme/cms.css';
        }

        return 'vendor/cms/' . $tenant . '/cms.css';
    }
}
