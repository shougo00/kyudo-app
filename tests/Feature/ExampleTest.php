<?php

it('returns a successful response', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('kyudoScore')
        ->assertSee('弓道部・道場の');
});
