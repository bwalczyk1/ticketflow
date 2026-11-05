<?php

it('reports healthy dependencies', function () {
    $this->getJson('/api/health')
        ->assertOk()
        ->assertExactJson(['status' => 'ok', 'checks' => ['database' => true, 'redis' => true]]);
});
