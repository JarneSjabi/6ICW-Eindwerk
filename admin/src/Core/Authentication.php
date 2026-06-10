<?php

namespace App\Core;

use App\Core\Audit;
use App\Models\User;

class Authentication
{
    private Session $session;
    protected ?User $user = null;
    private int $maxAttempts = 5;
    private int $decayMinutes = 10;

    public function __construct()
    {
        $this->session = new Session();
    }

    public function attempt(string $username, string $password, bool $remember = false): bool
    {
        if ($this->hasTooManyLoginAttempts()) {
            Audit::log('login_attempt_blocked_too_many_attempts', 'auth', null, null, array("attempted_username" => $username));
            NotificationManager::add("Te veel inlogpogingen", "U bent geblokkeerd. Probeer het opnieuw over " . $this->decayMinutes . " minutes.", 'error');
            header('Location: login.php?msg=toomany');
            return false;
        }

        $user = new User();
        $userData = $user->findByUsername($username);

        if (Config::get('MAINTENANCE_MODE') == true) {
            if (!$userData || empty($userData['is_root']) || !$userData['is_root']) {
                Audit::log('login_attempt_blocked_maintenance', 'auth', null, null, array("attempted_username" => $username));
                NotificationManager::add("Het systeem is momenteel in onderhoudsmodus.", "Probeer later opnieuw.", 'error');
                header('Location: login.php?msg=maintenance');
                return false;
            }
        }

        if ($userData && password_verify($password, $userData['password_hash'])) {
            $this->login($user, $userData, $remember);
            $this->clearLoginAttempts();
            return true;
        }

        $this->incrementLoginAttempts();
        Audit::log('login_failed', 'auth', null, null, ['username' => $username, 'ip' => $_SERVER['REMOTE_ADDR'], 'login_attempts' => $this->session->get('login_attempts_' . $this->getLoginAttemptsKey(), 0)]);
        header('Location: login.php?msg=userfail');
        return false;
    }

    public function login(User $user, array $userData, bool $remember = false): void
    {
        $this->session->set('user_id', $userData['id']);
        $this->session->regenerate();

        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $this->storeRememberToken($userData['id'], $token);
            setcookie('remember_token', $token, time() + (86400 * 30), '/', '', true, true);
        }

        $user->updateLastLogin();
        Audit::log('login_success', 'user', Session::get('user_id'));
        NotificationManager::add("Welkom!", $user->getUserGreeting(), 'info');
        header("Location: index.php");
        exit("Login successful");
    }

    public function logout(): void
    {
        Audit::log('logout', 'user', Session::get('user_id'));

        $this->session->remove('user_id');
        $this->removeRememberToken();
        $this->session->regenerate();
    }

    protected function storeRememberToken(int $userId, string $token): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
        $stmt->execute([$token, $userId]);
    }

    protected function removeRememberToken(): void
    {
        if ($this->check()) {
            $db = Database::getConnection();
            $stmt = $db->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
            $stmt->execute([$this->id()]);
        }
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
    }

    protected function hasTooManyLoginAttempts(): bool
    {
        $key = 'login_attempts_' . $this->getLoginAttemptsKey();
        $attempts = $this->session->get($key, 0);
        $lastAttempt = $this->session->get('last_login_attempt');

        if ($lastAttempt && (time() - $lastAttempt) > ($this->decayMinutes * 60)) {
            $this->clearLoginAttempts();
            return false;
        }

        return $attempts >= $this->maxAttempts;
    }

    protected function incrementLoginAttempts(): void
    {
        $key = 'login_attempts_' . $this->getLoginAttemptsKey();
        $attempts = $this->session->get($key, 0);
        $this->session->set($key, $attempts + 1);
        $this->session->set('last_login_attempt', time());
    }

    protected function clearLoginAttempts(): void
    {
        $key = 'login_attempts_' . $this->getLoginAttemptsKey();
        $this->session->remove($key);
        $this->session->remove('last_login_attempt');
    }

    protected function getLoginAttemptsKey(): string
    {
        return md5($_SERVER['REMOTE_ADDR']);
    }

    public function check(): bool
    {
        return $this->session->has('user_id');
    }

    public function id()
    {
        return $this->session->get('user_id');
    }

    public function user()
    {
        if (!$this->check()) {
            return null;
        }

        if ($this->user === null) {
            $this->user = User::find($this->id());
        }

        return $this->user;
    }

    public function hasPermission(string $permission): bool
    {
        if (!$this->check()) {
            return false;
        }

        $user = $this->user();
        if ($user && $user->is_root_user) {
            return true;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT gp.value 
            FROM user_group_permissions gp
            JOIN users u ON u.user_group_id = gp.group_id
            JOIN permissions p ON p.id = gp.permission_id
            WHERE u.id = ? AND p.name = ?
        ");
        $stmt->execute([$this->id(), $permission]);
        $result = $stmt->fetch();

        return $result ? (bool)$result['value'] : false;
    }
}
