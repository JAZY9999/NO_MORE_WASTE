<?php

/**
 * La barre laterale du back-office, decrite une seule fois.
 *
 * POURQUOI UN FICHIER DE CONFIGURATION ET PAS DU HTML
 *
 * Le menu apparait sur les 22 ecrans du back-office. S'il etait ecrit en HTML
 * dans le gabarit, ajouter une entree voudrait dire retrouver la bonne section
 * au milieu de balises. Ici, c'est une ligne dans un tableau.
 *
 * Surtout : les libelles sont des CLES DE TRADUCTION, jamais du texte. C'est
 * ce qui fait que la barre laterale change de langue avec le reste du site.
 * Ecrire "Commercants" en clair ici casserait le multilingue sur toutes les
 * pages du back-office d'un coup -- et le sujet exige un site multilingue.
 *
 * L'ORDRE DES SECTIONS SUIT LE PARCOURS METIER
 *
 * On entre dans le reseau (ceux qui donnent et ceux qui aident), on collecte,
 * on stocke, on distribue. Puis viennent les activites, et enfin
 * l'administration -- ce qui ne releve pas du travail quotidien.
 * C'est plus facile a retenir que l'ordre alphabetique ou l'ordre des tables.
 */

return [

    'sections' => [

        [
            'titre' => 'menu.pilotage',
            'entrees' => [
                [
                    'cle' => 'tableau_de_bord',
                    'libelle' => 'menu.tableau_de_bord',
                    'icone' => 'bi-speedometer2',
                    'url' => '/back',
                ],
            ],
        ],

        [
            'titre' => 'menu.reseau',
            'entrees' => [
                [
                    'cle' => 'commercants',
                    'libelle' => 'menu.commercants',
                    'icone' => 'bi-shop',
                    'url' => '/back/commercants',
                ],
                [
                    'cle' => 'adhesions',
                    'libelle' => 'menu.adhesions',
                    'icone' => 'bi-card-checklist',
                    'url' => '/back/adhesions',
                    // Couleur de la pastille quand un compteur est fourni.
                    // C'est une propriete de l'entree, pas une decision du
                    // controleur : "les adhesions a renouveler" sont toujours
                    // un avertissement, quel que soit l'ecran affiche.
                    'couleur' => 'warning',
                ],
                [
                    'cle' => 'benevoles',
                    'libelle' => 'menu.benevoles',
                    'icone' => 'bi-people',
                    'url' => '/back/benevoles',
                    'couleur' => 'warning',
                ],
                [
                    'cle' => 'beneficiaires',
                    'libelle' => 'menu.beneficiaires',
                    'icone' => 'bi-heart',
                    'url' => '/back/beneficiaires',
                ],
            ],
        ],

        [
            'titre' => 'menu.logistique',
            'entrees' => [
                [
                    'cle' => 'collectes',
                    'libelle' => 'menu.collectes',
                    'icone' => 'bi-basket3',
                    'url' => '/back/collectes',
                ],
                [
                    'cle' => 'stocks',
                    'libelle' => 'menu.stocks',
                    'icone' => 'bi-boxes',
                    'url' => '/back/stocks',
                    'couleur' => 'danger',
                ],
                [
                    'cle' => 'emplacements',
                    'libelle' => 'menu.emplacements',
                    'icone' => 'bi-geo-alt',
                    'url' => '/back/emplacements',
                ],
                [
                    'cle' => 'tournees',
                    'libelle' => 'menu.tournees',
                    'icone' => 'bi-truck',
                    'url' => '/back/tournees',
                    'couleur' => 'info',
                ],
            ],
        ],

        [
            'titre' => 'menu.activites',
            'entrees' => [
                [
                    // "services" et non "creneaux" : c'est le mot du sujet
                    // ("services accessibles aux adherents"). L'ecran gere les
                    // deux -- les services et leurs creneaux -- mais c'est le
                    // service que l'utilisateur vient chercher.
                    'cle' => 'services',
                    'libelle' => 'menu.services',
                    'icone' => 'bi-calendar3',
                    'url' => '/back/services',
                ],
                [
                    'cle' => 'catalogue',
                    'libelle' => 'menu.catalogue',
                    'icone' => 'bi-list-stars',
                    'url' => '/back/catalogue',
                ],
                [
                    'cle' => 'campagnes',
                    'libelle' => 'menu.campagnes',
                    'icone' => 'bi-megaphone',
                    'url' => '/back/campagnes',
                ],
            ],
        ],

        [
            'titre' => 'menu.administration',
            'entrees' => [
                [
                    'cle' => 'utilisateurs',
                    'libelle' => 'menu.utilisateurs',
                    'icone' => 'bi-person-gear',
                    'url' => '/back/utilisateurs',

                    // 'role' : entree visible par ce seul role.
                    //
                    // Sans elle, un membre du personnel voyait "Utilisateurs"
                    // dans son menu, cliquait, et se faisait renvoyer au
                    // tableau de bord. Un lien qui rebondit donne l'impression
                    // d'un site casse.
                    //
                    // C'est du CONFORT, pas de la securite : le controleur
                    // verifie le role de toute facon. Meme raisonnement que le
                    // bouton desactive de la fiche d'un benevole -- on a
                    // besoin des deux.
                    'role' => 'admin_back',
                ],
                [
                    'cle' => 'traductions',
                    'libelle' => 'menu.traductions',
                    'icone' => 'bi-translate',
                    'url' => '/back/traductions',
                ],
            ],
        ],
    ],

    /**
     * Les ecrans qui n'ont pas d'entree propre dans le menu.
     *
     * Une fiche de detail s'ouvre depuis une liste : elle ne merite pas sa
     * ligne dans la barre laterale, mais l'entree parente doit rester
     * surlignee. Sans cette table, ouvrir une fiche ferait perdre toute
     * position dans le menu -- plus rien n'est surligne, on ne sait plus ou
     * l'on se trouve.
     *
     * On indexe par CHEMIN DE VUE (et non par URL) parce que c'est la seule
     * information dont Vue::afficher dispose deja, et parce qu'elle ne change
     * pas si une adresse est renommee.
     *
     * Cette table ne liste que les EXCEPTIONS : pour tous les autres ecrans,
     * le dernier segment du chemin de vue est deja la bonne cle
     * (back/commercants -> commercants). C'est ce qui evite d'avoir a
     * configurer 22 lignes ici.
     */
    'parents' => [
        'back/commercant_detail' => 'commercants',
        'back/commercant_nouveau' => 'commercants',
        'back/benevole_detail' => 'benevoles',
        'back/collecte_detail' => 'collectes',
        'back/tournee_detail' => 'tournees',
        'back/adhesions_config' => 'adhesions',
        'back/adhesions_profils' => 'adhesions',

        // Chaine vide : aucune entree surlignee. "Mon compte" ne fait partie
        // d'aucune rubrique metier, on s'y rend depuis le pied de la barre.
        'back/profil' => '',
    ],
];
