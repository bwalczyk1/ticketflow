<?php

arch('services do not know HTTP')
    ->expect('App\Services')
    ->not->toUse(['Illuminate\Http', 'request', 'response']);

arch('controllers do not touch the database directly')
    ->expect('App\Http\Controllers')
    ->not->toUse(['Illuminate\Support\Facades\DB']);

arch('models do not depend on services')
    ->expect('App\Models')
    ->not->toUse('App\Services');

arch('enums are enums')
    ->expect('App\Enums')
    ->toBeEnums();

arch('no debugging leftovers')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

arch('env is read only in config files')
    ->expect('env')
    ->not->toBeUsed();

arch('domain exceptions extend the base class')
    ->expect('App\Exceptions\Domain')
    ->toExtend('App\Exceptions\Domain\DomainException')
    ->ignoring('App\Exceptions\Domain\DomainException');

arch('contracts are interfaces')
    ->expect('App\Contracts')
    ->toBeInterfaces();

arch('data objects are readonly')
    ->expect('App\Data')
    ->toBeReadonly();
