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
        $admin = User::factory()->admin()->create(['name' => 'Anna Admin', 'username' => 'anna']);
        User::factory()->moderator()->create(['name' => 'Moritz Moderator', 'username' => 'moritz']);
        $user = User::factory()->create(['name' => 'Bea Benutzer', 'username' => 'bea']);
        User::factory()->pending()->create(['name' => 'Neu Nutzer', 'username' => 'neu.nutzer']);

        Note::factory()->count(3)->for($admin)->create();
        Note::factory()->count(2)->for($user)->create();

        $this->command->info('Konten anna, moritz, bea (und wartend: neu.nutzer), Passwort jeweils: '.UserFactory::PASSWORD);
    }
}
