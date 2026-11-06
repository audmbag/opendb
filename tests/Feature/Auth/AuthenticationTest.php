<?php

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});
