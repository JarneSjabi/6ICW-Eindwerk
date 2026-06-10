<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\AwaitingUser;

class AwaitingUserController extends Controller
{
    protected $table = 'awaiting_users';

    protected function acceptAwatingUser(int $au_id)
    {
        try {
            $result = AwaitingUser::acceptAwaitingUser($au_id);
            if ($result) {
                return $this->success('Verzoek geaccepteerd. Gebruiker is aangemaakt en kan inloggen met het gekozen wachtwoord.');
            } else {
                return $this->error('Kon gebruiker niet accepteren');
            }
        } catch (\Exception $e) {
            return $this->error('Fout bij accepteren gebruiker: ' . $e->getMessage());
        }
    }

    protected function denyAwatingUser(int $au_id)
    {
        try {
            $result = AwaitingUser::denyAwaitingUser($au_id);
            if ($result) {
                return $this->success('Verzoek geweigerd');
            } else {
                return $this->error('Kon verzoek niet weigeren');
            }
        } catch (\Exception $e) {
            return $this->error('Fout bij weigeren verzoek: ' . $e->getMessage());
        }
    }

    protected function clearAllAwatingUsers()
    {
        try {
            $result = AwaitingUser::clearAllAwatingUsers();
            if ($result) {
                return $this->success('Lijst van verzoeken leeggemaakt');
            } else {
                return $this->error('Kon verzoeken niet leegmaken');
            }
        } catch (\Exception $e) {
            return $this->error('Fout bij leegmaken verzoeken: ' . $e->getMessage());
        }
    }
}
