<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un usuario SSO no tiene contraseña local, así que `users.password` pasa a
 * nullable cuando la tabla de la app tiene esa columna.
 */
return new class extends Migration
{
    public function up(): void
    {
        $password = collect(Schema::getColumns('users'))->firstWhere('name', 'password');

        // Alterar una columna ya nullable reescribe la tabla y borra su comentario.
        if ($password === null || $password['nullable']) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * Volver a NOT NULL fallaría en cuanto exista un usuario SSO.
     */
    public function down(): void
    {
    }
};
