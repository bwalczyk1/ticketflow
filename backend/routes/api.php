<?php

// Every module keeps its routes in routes/api/<module>.php, so modules never edit a shared file.
foreach (glob(__DIR__.'/api/*.php') as $routes) {
    require $routes;
}
