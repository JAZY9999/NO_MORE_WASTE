<?php

namespace App\Controllers\Front;

use App\Middleware\Langue;
use App\Vue;

/** Pages publiques du front-office. */
class AccueilController
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function index(): void
    {
        Vue::afficher('front/accueil', [
            'config' => $this->config,
        ], Langue::t('nav.accueil'));
    }
}
