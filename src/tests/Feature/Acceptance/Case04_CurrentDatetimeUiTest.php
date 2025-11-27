<?php

namespace Tests\Feature\Acceptance;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Acceptance\Support\AttTestHelpers;

class Case04_CurrentDatetimeUiTest extends TestCase
{
    use RefreshDatabase, AttTestHelpers;

    /** UIに現在日時が表示される（形式一致の存在確認） */
    public function test_stamp_shows_now()
    {
        $this->nowFreeze('2025-11-24 10:15:00');
        $u = $this->makeUser();
        $this->actingAs($u, 'web')
            ->get($this->ROUTE_STAMP)
            ->assertOk()
            ->assertSee('2025')->assertSee('11')->assertSee('24');
    }
}
