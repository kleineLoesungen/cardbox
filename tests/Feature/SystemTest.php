<?php

use App\Mail\TestMail;
use Illuminate\Support\Facades\Mail;

it('zeigt die wirksame Konfiguration ohne Passwörter', function () {
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.username' => 'postbote', 'mail.mailers.smtp.password' => 'streng-geheim']);
    actingAsAdmin();

    $this->get('/admin/system')
        ->assertOk()
        ->assertSee('postbote')
        ->assertSee('Passwort gesetzt')
        ->assertDontSee('streng-geheim');
});

it('verschickt eine Testmail mit Text- und HTML-Fassung', function () {
    Mail::fake();
    $admin = actingAsAdmin(['name' => 'Anna Admin']);

    $this->from('/admin/system')->post('/admin/system/testmail', ['to' => 'ziel@example.com'])
        ->assertRedirect('/admin/system')
        ->assertSessionHas('mail_sent');

    Mail::assertSent(TestMail::class, function (TestMail $mail) {
        $mail->assertSeeInHtml('Anna Admin');
        $mail->assertSeeInText('Anna Admin');

        return $mail->hasTo('ziel@example.com');
    });
});

it('rendert die Testmail mit Markenfarbe und Link', function () {
    $mail = new TestMail(triggeredBy: 'Anna');

    $mail->assertSeeInHtml(config('pwa.theme_color'))
        ->assertSeeInHtml(config('app.url'))
        ->assertSeeInText('Ausgelöst von Anna.');
});
