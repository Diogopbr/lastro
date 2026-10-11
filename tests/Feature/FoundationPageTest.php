<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class FoundationPageTest extends TestCase
{
    public function test_entry_renders_the_login_inertia_component(): void
    {
        $this->withoutVite();
        $this->get('/entrar')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/Login'));
        $this->get('/')->assertRedirect('/entrar');
    }
}
