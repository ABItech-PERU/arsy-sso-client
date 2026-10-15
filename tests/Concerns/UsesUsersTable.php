<?php

namespace Arsy\SSOClient\Tests\Concerns;

use Arsy\SSOClient\Tests\Fixtures\User;

/**
 * Tabla `users` de Laravel más la migración del paquete, en el sqlite en
 * memoria que cada prueba estrena.
 */
trait UsesUsersTable
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();
        $this->artisan('migrate')->run();
    }

    protected function createLocalUser(array $attributes = []): User
    {
        return User::forceCreate([
            'name' => 'Ana Quispe',
            'email' => 'ana@arsy.test',
            'password' => 'sin-uso',
            ...$attributes,
        ]);
    }
}
