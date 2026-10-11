<?php

namespace Database\Seeders;

use App\Models\Note;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Database\Seeder;

/**
 * Beispieldaten fuer die lokale Entwicklung:  php artisan db:seed
 *
 * Nie in Produktion ausfuehren. Alle Konten haben das Passwort
 * aus UserFactory::PASSWORD.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Anna Admin', 'email' => 'admin@example.com']);
        User::factory()->moderator()->create(['name' => 'Moritz Moderator', 'email' => 'moderator@example.com']);
        $user = User::factory()->create(['name' => 'Bea Benutzer', 'email' => 'user@example.com']);

        Note::factory()->count(3)->for($admin)->create();
        Note::factory()->count(2)->for($user)->create();

        $this->command->info('Konten angelegt, Passwort jeweils: '.UserFactory::PASSWORD);
    }
}
