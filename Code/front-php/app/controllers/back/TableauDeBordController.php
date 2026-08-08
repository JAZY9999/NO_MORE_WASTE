<?php

namespace App\Controllers\Back;

use App\Middleware\Auth;
use App\Middleware\Langue;
use App\Vue;

/** Accueil du back-office. */
class TableauDeBordController
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function index(): void
    {
        if (!Auth::exigerStaff($this->config)) {
            return;
        }

        Vue::afficher('back/tableau_de_bord', [
            'config' => $this->config,
        ], Langue::t('back.titre'), [
            // Le bandeau est dessine par le gabarit : le controleur decrit ce
            // qu'il veut y voir, il ne le met pas en forme lui-meme.
            'sous_titre' => Langue::t('connexion.bienvenue') . ', '
                . (Auth::utilisateur()['email'] ?? ''),
        ]);
    }
}
