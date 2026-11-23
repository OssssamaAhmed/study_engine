<?php

namespace Tests\Feature;

use Tests\TestCase;

class UpdatePackageTest extends TestCase
{
    /** --dry must never write anything, so it is safe to run against the real tree. */
    public function test_dry_run_succeeds_without_writing(): void
    {
        if (! file_exists(base_path('updates/manifest.json'))) {
            $this->markTestSkipped('No update manifest — packages are cut from the primary install only.');
        }

        $before = glob(base_path('updates/update-*'));

        $this->artisan('update:package', ['--dry' => true])->assertSuccessful();

        $this->assertSame($before, glob(base_path('updates/update-*')));
    }
}
