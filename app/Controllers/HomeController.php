<?php

declare(strict_types=1);

namespace Cliniva\Controllers;

use Cliniva\Core\Controller;
use Cliniva\Core\Response;
use Cliniva\Services\PublicSiteService;

final class HomeController extends Controller
{
    public function index(): void
    {
        $site = new PublicSiteService();
        $this->view('home/index', array_merge($site->publicData(), [
            'title' => 'Cliniva | Centre de santé à Douala',
            'metaDescription' => 'Cliniva facilite l’accueil, les rendez-vous et le suivi des patients dans un centre de santé au Cameroun.',
            'canonicalUrl' => url('/'),
            'ogImage' => asset('img/cliniva-hero.webp'),
        ]));
    }

    public function submitContact(): void
    {
        $site = new PublicSiteService();

        try {
            $accepted = $site->submitContact($_POST);
            flash(
                $accepted ? 'success' : 'warning',
                $accepted
                    ? 'Merci pour votre message. Notre équipe vous répondra dès que possible.'
                    : 'Trop de demandes ont été envoyées récemment. Réessayez un peu plus tard.'
            );
        } catch (\InvalidArgumentException $exception) {
            remember_errors(explode("\n", $exception->getMessage()));
            flash('danger', 'Le message n’a pas pu être envoyé. Vérifiez les champs indiqués.');
        }

        Response::redirect('/#contact');
    }

    public function dashboard(): void
    {
        if (current_role() === 'admin') {
            Response::redirect('/admin');
        }

        if (current_role() === 'patient') {
            Response::redirect('/patient');
        }

        if (current_role() === 'secretaire') {
            Response::redirect('/reception');
        }

        if (current_role() === 'medecin') {
            Response::redirect('/doctor');
        }

        $this->view('home/dashboard', [
            'title' => 'Tableau de bord',
        ]);
    }
}
