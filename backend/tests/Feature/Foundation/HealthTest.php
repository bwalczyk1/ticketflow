<?php

it('reports healthy dependencies', function () {
    $this->getJson('/api/health')
        ->assertOk()
        ->assertExactJson(['status' => 'ok', 'checks' => ['database' => true, 'redis' => true]]);
});

it('has no second health endpoint besides /api/health', function () {
    $this->get('/up')->assertNotFound();
});
