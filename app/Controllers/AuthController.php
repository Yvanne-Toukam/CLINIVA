<?php

declare(strict_types=1);

namespace Cliniva\Controllers;

use Cliniva\Core\Controller;
use Cliniva\Core\Response;
use Cliniva\Models\User;
use Cliniva\Services\AuthService;
use Cliniva\Services\TotpService;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;

final class AuthController extends Controller
{
    private $auth;
    private $users;
    private $totp;

    public function __construct()
    {
        $this->auth = new AuthService();
        $this->users = new User();
        $this->totp = new TotpService();
    }

    public function showRegister(): void
    {
        $this->view('auth/register', ['title' => 'Inscription patient']);
    }

    public function register(): void
    {
        try {
            $result = $this->auth->registerPatient($_POST);
            forget_old();
            flash('success', 'Compte patient cree. Verifiez votre e-mail pour activer le compte.');

            if (!is_production()) {
                flash('info', 'Lien local de verification : ' . $result['verification_link']);
            }

            Response::redirect('/login');
        } catch (\InvalidArgumentException $exception) {
            remember_old($_POST);
            remember_errors(explode("\n", $exception->getMessage()));
            Response::redirect('/register');
        }
    }

    public function verifyEmail(): void
    {
        $email = (string) ($_GET['email'] ?? '');
        $ok = $this->auth->verifyEmail($email, (string) ($_GET['token'] ?? ''));

        if ($ok) {
            $user = $this->users->findByEmail(strtolower(trim($email)));
            if ($user !== null && $user['role'] === 'patient' && (int) $user['is_active'] === 1) {
                $this->completeSuccessfulLogin($user, 'Adresse e-mail vérifiée. ');
            }
        }

        flash($ok ? 'success' : 'danger', $ok ? 'Adresse e-mail vérifiée. Vous pouvez vous connecter.' : 'Lien de vérification invalide ou expiré.');
        Response::redirect('/login');
    }

    public function showLogin(): void
    {
        $this->view('auth/login', ['title' => 'Connexion']);
    }

    public function login(): void
    {
        $email = (string) ($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $user = $this->auth->authenticate($email, $password);

        if ($user === null) {
            remember_old(['email' => $email]);
            flash('danger', 'Identifiants invalides ou compte temporairement bloque.');
            Response::redirect('/login');
        }

        if ($user['role'] === 'patient' && empty($user['email_verified_at'])) {
            flash('warning', 'Veuillez verifier votre e-mail avant de vous connecter.');
            Response::redirect('/login');
        }

        if ($user['role'] === 'admin') {
            $_SESSION['pending_2fa_user_id'] = (int) $user['id'];
            session_regenerate_id(true);

            if (empty($user['totp_secret_encrypted'])) {
                $_SESSION['pending_2fa_secret'] = $this->totp->generateSecret();
                Response::redirect('/2fa/setup');
            }

            Response::redirect('/2fa/verify');
        }

        if ($user['role'] === 'admin') {
            unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_secret']);
        }

        $this->completeSuccessfulLogin($user);
    }

    public function logout(): void
    {
        logout_user();
        secure_session_start();
        flash('success', 'Vous etes deconnecte.');
        Response::redirect('/login');
    }

    public function showTwoFactorSetup(): void
    {
        $user = $this->pendingTwoFactorUser();
        $secret = $this->totp->generateSecret();
        $_SESSION['pending_2fa_secret'] = $secret;
        $uri = $this->totp->provisioningUri((string) $user['email'], $secret);
        $qrCode = (new SvgWriter())->write(new QrCode(
            data: $uri,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 240,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        ))->getDataUri();

        $this->view('auth/two_factor_setup', [
            'title' => 'Configuration 2FA',
            'qrCode' => $qrCode,
        ]);
    }

    public function completeTwoFactorSetup(): void
    {
        $user = $this->pendingTwoFactorUser();
        $secret = (string) ($_SESSION['pending_2fa_secret'] ?? '');
        $code = (string) ($_POST['totp_code'] ?? '');

        if ($secret === '' || !$this->totp->verify($secret, $code)) {
            flash('danger', 'Code 2FA invalide.');
            Response::redirect('/2fa/setup');
        }

        $this->users->updateTotpSecret((int) $user['id'], encrypt_sensitive($secret));
        unset($_SESSION['pending_2fa_secret'], $_SESSION['pending_2fa_user_id']);

        $user['totp_secret_encrypted'] = encrypt_sensitive($secret);
        $this->completeSuccessfulLogin($user, 'Double authentification activée. ');
    }

    public function showTwoFactorVerify(): void
    {
        $this->pendingTwoFactorUser();
        $this->view('auth/two_factor_verify', ['title' => 'Verification 2FA']);
    }

    public function verifyTwoFactor(): void
    {
        $user = $this->pendingTwoFactorUser();
        $secret = decrypt_sensitive((string) $user['totp_secret_encrypted']);

        if ($secret === null || !$this->totp->verify($secret, (string) ($_POST['totp_code'] ?? ''))) {
            flash('danger', 'Code 2FA invalide.');
            Response::redirect('/2fa/verify');
        }

        unset($_SESSION['pending_2fa_user_id']);
        $this->completeSuccessfulLogin($user, 'Connexion administrateur confirmée. ');
    }

    public function showForgotPassword(): void
    {
        $this->view('auth/forgot_password', ['title' => 'Mot de passe oublie']);
    }

    public function sendResetLink(): void
    {
        $link = $this->auth->createPasswordReset((string) ($_POST['email'] ?? ''));
        flash('success', 'Si ce compte existe, un lien de reinitialisation vient d etre prepare.');

        if (!is_production() && $link !== null) {
            flash('info', 'Lien local de reinitialisation : ' . $link);
        }

        Response::redirect('/password/forgot');
    }

    public function showResetPassword(): void
    {
        $this->view('auth/reset_password', [
            'title' => 'Nouveau mot de passe',
            'email' => (string) ($_GET['email'] ?? ''),
            'token' => (string) ($_GET['token'] ?? ''),
        ]);
    }

    public function resetPassword(): void
    {
        try {
            $ok = $this->auth->resetPassword(
                (string) ($_POST['email'] ?? ''),
                (string) ($_POST['token'] ?? ''),
                (string) ($_POST['password'] ?? ''),
                (string) ($_POST['password_confirmation'] ?? '')
            );

            flash($ok ? 'success' : 'danger', $ok ? 'Mot de passe mis a jour.' : 'Lien de reinitialisation invalide ou expire.');
            Response::redirect($ok ? '/login' : '/password/forgot');
        } catch (\InvalidArgumentException $exception) {
            remember_errors(explode("\n", $exception->getMessage()));
            Response::redirect('/password/reset?email=' . rawurlencode((string) ($_POST['email'] ?? '')) . '&token=' . rawurlencode((string) ($_POST['token'] ?? '')));
        }
    }

    public function showChangePassword(): void
    {
        $user = current_user();
        if ($user === null) {
            Response::redirect('/login');
        }
        if (empty($user['must_change_password'])) {
            Response::redirect($this->homePathForRole((string) $user['role']));
        }

        $this->view('auth/change_password', ['title' => 'Changer le mot de passe']);
    }

    public function changePassword(): void
    {
        $user = current_user();
        if ($user === null) {
            Response::redirect('/login');
        }
        if (empty($user['must_change_password'])) {
            Response::redirect($this->homePathForRole((string) $user['role']));
        }

        try {
            $this->auth->changeCurrentPassword(
                (int) $user['id'],
                (string) ($_POST['password'] ?? ''),
                (string) ($_POST['password_confirmation'] ?? '')
            );
            $user['must_change_password'] = 0;
            login_user($user);
            flash('success', 'Mot de passe modifié. Votre compte est prêt.');
            Response::redirect($this->homePathForRole((string) $user['role']));
        } catch (\InvalidArgumentException $exception) {
            remember_errors(explode("\n", $exception->getMessage()));
            Response::redirect('/password/change');
        }
    }

    private function pendingTwoFactorUser(): array
    {
        $id = (int) ($_SESSION['pending_2fa_user_id'] ?? 0);
        if ($id <= 0) {
            Response::redirect('/login');
        }

        $user = $this->users->findById($id);
        if ($user === null || $user['role'] !== 'admin') {
            unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_secret']);
            Response::redirect('/login');
        }

        return $user;
    }

    private function completeSuccessfulLogin(array $user, string $prefix = ''): void
    {
        $this->auth->completeLogin($user);
        $name = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
        $salutation = match ($user['sex'] ?? null) {
            'M' => 'M. ',
            'F' => 'Mme ',
            default => '',
        };

        flash('success', $prefix . 'Bienvenue ' . $salutation . $name . '.');
        if (!empty($user['must_change_password'])) {
            Response::redirect('/password/change');
        }
        Response::redirect($this->homePathForRole((string) ($user['role'] ?? '')));
    }

    private function homePathForRole(string $role): string
    {
        return match ($role) {
            'admin' => '/admin',
            'medecin' => '/doctor',
            'secretaire' => '/reception',
            'patient' => '/patient',
            default => '/dashboard',
        };
    }
}
