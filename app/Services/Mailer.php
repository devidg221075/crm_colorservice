<?php
declare(strict_types=1);

namespace App\Services;

use App\Config;

/** Invio email. I test iniettano una sottoclasse finta; questa implementazione usa mail() di PHP. */
class Mailer
{
    public function invia(string $a, string $oggetto, string $corpo): bool
    {
        if (preg_match('/[\r\n]/', $a . $oggetto) === 1) {
            return false; // niente header injection
        }
        $da = (string) Config::get('mail.from', 'noreply@localhost');
        $headers = "From: $da\r\nContent-Type: text/plain; charset=UTF-8\r\nMIME-Version: 1.0";
        $oggettoCodificato = '=?UTF-8?B?' . base64_encode($oggetto) . '?=';
        return @mail($a, $oggettoCodificato, $corpo, $headers);
    }
}
