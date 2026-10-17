<?php

namespace Arsy\SSOClient\Tests\Feature\Database;

use Arsy\SSOClient\Tests\TestCase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PasswordNullableMigrationTest extends TestCase
{
    public function test_a_required_password_becomes_nullable(): void
    {
        Schema::create('users', fn (Blueprint $table) => $table->string('password'));

        $this->migration()->up();

        $this->assertTrue($this->passwordColumn()['nullable']);
    }

    public function test_a_nullable_password_is_left_untouched(): void
    {
        Schema::create('users', fn (Blueprint $table) => $table->string('password')->nullable());
        $statements = [];
        DB::listen(function ($query) use (&$statements) {
            $statements[] = $query->sql;
        });

        $this->migration()->up();

        $this->assertSame([], preg_grep('/^\s*(create|alter|drop|insert)\b/i', $statements));
    }

    public function test_a_table_without_password_is_left_untouched(): void
    {
        Schema::create('users', fn (Blueprint $table) => $table->string('email'));

        $this->migration()->up();

        $this->assertFalse(Schema::hasColumn('users', 'password'));
    }

    private function migration(): Migration
    {
        return require __DIR__.'/../../../database/migrations/2026_10_15_000000_make_users_password_nullable.php';
    }

    /**
     * @return array<string, mixed>
     */
    private function passwordColumn(): array
    {
        return collect(Schema::getColumns('users'))->firstWhere('name', 'password');
    }
}
