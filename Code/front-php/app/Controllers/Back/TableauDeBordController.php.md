# `TableauDeBordController.php` — l'accueil du back-office

> Le plus court contrôleur du projet. Il mérite quand même une explication, parce qu'il montre le patron que tous les autres suivent.

## Le patron, en trois temps

```php
public function index(): void
{
    if (!Auth::exigerStaff($this->config)) {
        return;
    }

    Vue::afficher('back/tableau_de_bord', ['config' => $this->config], …);
}
```

1. **La garde d'abord.** Rien ne se passe avant elle.
2. **`return` immédiat** si elle échoue : `exigerStaff()` a déjà envoyé la redirection, continuer produirait une page après les en-têtes.
3. **L'affichage**, et rien d'autre.

Les onze contrôleurs du back-office commencent exactement pareil.

## Le contrôleur décrit, le gabarit dessine

```php
'sous_titre' => Langue::t('connexion.bienvenue') . ', ' . (Auth::utilisateur()['email'] ?? ''),
```

Le contrôleur dit **quoi** afficher dans le bandeau. Il ne dit pas comment : ni classe Bootstrap, ni balise HTML. C'est `blocs/entete_back.php` qui décide de la mise en forme.

Le `?? ''` protège le cas — théoriquement impossible ici, puisque la garde vient de passer — où la session ne contiendrait pas d'e-mail. Sans lui, PHP émettrait un avertissement en haut de la page.

## Ce que le tableau de bord n'affiche pas encore

Les compteurs réels (collectes du jour, bénévoles en attente…) ne sont pas branchés.

C'est un choix, pas un oubli : alimenter les compteurs de la barre latérale demanderait quatre appels API **sur chaque page** du back-office, et une panne de l'API casserait les vingt écrans au lieu d'un seul. La forme est posée ; la donnée arrivera écran par écran.

## Fichiers liés

- [../../middleware/Auth.php.md](../../Middleware/Auth.php.md) — `exigerStaff()`
- [../../views/blocs/entete_back.php.md](../../views/blocs/entete_back.php.md) — qui met en forme le `sous_titre`
- [BenevolesController.php.md](BenevolesController.php.md) — le même patron, sur un écran complet
