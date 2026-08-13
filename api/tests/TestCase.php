<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Scope test migrations to this app's own database/migrations. The
     * installed Fleetbase engine packages register vendor migration paths
     * through package discovery, and those migrations require MySQL-only
     * features (spatial indexes) that the sqlite test database refuses —
     * while no test exercises a Fleetbase table.
     *
     * Two seams that do NOT work, for the archaeologist who tries them:
     * overriding migrateUsing()/migrateFreshUsing() here loses, because
     * RefreshDatabase is composed into each test class and PHP trait
     * members override inherited ones; and afterApplicationCreated()
     * callbacks run after setUpTraits() in this Laravel version, i.e.
     * after RefreshDatabase has already migrated. refreshApplication()
     * runs before both and no test-class trait redefines it.
     * Production migration behavior is unchanged.
     */
    protected function refreshApplication()
    {
        parent::refreshApplication();

        $migrator = $this->app->make('migrator');

        (function () {
            $this->paths = array_values(array_filter(
                $this->paths,
                static fn ($path) => ! str_contains($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)
            ));
        })->call($migrator);
    }
}
