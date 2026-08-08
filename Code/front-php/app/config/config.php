<?php

/**
 * Reglages centraux du front.
 *
 * Meme idee que config/config.go cote API : on ne code jamais une adresse ou un
 * mot de passe en dur au milieu du code. Tout ce qui peut changer selon la
 * machine (developpement, serveur reel) est lu ici, a un seul endroit.
 */

return [

    // --- Adresse de l'API Go ---
    //
    // ATTENTION : ce n'est PAS http://localhost:8080/api.
    // Ce code s'execute a l'interieur du conteneur front-php. Pour lui,
    // "localhost" designe... lui-meme, pas ta machine. Docker fournit un nom de
    // reseau interne pour chaque conteneur : celui de l'API est "api-go", et son
    // port interne est 8080 (celui du docker-compose, pas celui expose au navigateur).
    //
    // En resume : le navigateur passe par nginx, le PHP parle directement a l'API.
    'api_base_url' => getenv('API_BASE_URL') ?: 'http://api-go:8080',

    // --- Langues ---
    //
    // Le sujet demande un site multilingue : l'association s'est installee a
    // l'etranger (Italie, Portugal, Irlande), a la demande des municipalites.
    'langue_par_defaut' => 'fr',
    'langues_disponibles' => [
        'fr' => 'Francais',
        'en' => 'English',
        'it' => 'Italiano',
        'pt' => 'Portugues',
    ],

    // --- Roles ---
    //
    // Doit rester identique aux roles de l'API (colonne utilisateurs.role).
    // Si les deux listes divergent, un utilisateur peut se connecter mais
    // n'avoir acces a rien.
    'roles_back_office' => ['admin_back', 'staff_back'],

    // Le role administrateur, seul, sans "staff_back".
    //
    // Certaines actions ne se delegent pas : creer un compte, c'est pouvoir
    // se fabriquer un acces. L'ecran des utilisateurs le verifie donc a part,
    // parce que roles_back_office laisserait passer les deux.
    'role_admin_back' => 'admin_back',

    // Les deux roles du front-office, qui ont chacun leur espace personnel.
    // Ils sont declares ici et pas en dur dans Auth.php pour que les quatre
    // roles de l'API restent visibles au meme endroit : sinon, le jour ou un
    // role change de nom cote Go, on en oublie un et la panne ne se voit qu'a
    // l'execution.
    'role_adherent' => 'adherent',
    'role_benevole' => 'benevole',
];
