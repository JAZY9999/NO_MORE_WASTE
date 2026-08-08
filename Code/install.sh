#!/usr/bin/env bash
#
# install.sh -- installe NO MORE WASTE sur un serveur neuf, en une commande.
#
# Le sujet demande une application "packagee pour pouvoir etre aisement
# deployee". Sans ce script, deployer demandait de :
#   1. copier .env.example vers .env et inventer un secret JWT a la main ;
#   2. lancer les conteneurs ;
#   3. attendre que l'API soit prete, sans savoir combien de temps ;
#   4. creer un premier compte administrateur -- ce qui est impossible
#      depuis l'application elle-meme, puisque creer un compte AVEC un role
#      exige deja d'etre administrateur (voir UtilisateursController.php.md).
#      La seule solution etait une requete SQL tapee a la main.
#
# Ce script fait les quatre a la place de la personne qui installe.
#
# REJOUABLE : le relancer sur une installation deja faite ne casse rien --
# il garde le .env existant, et ne recree pas de second compte admin s'il en
# existe deja un. C'est le meme principe que les scripts de tests Python
# (tests/tester-tous-les-endpoints.py), qui doivent pouvoir tourner plusieurs
# fois de suite sans erreur.

set -euo pipefail

# On se place dans le dossier du script, pas celui d'ou il est appele :
# sinon "./install.sh" lance depuis un autre dossier chercherait un
# docker-compose.yml qui n'existe pas la ou on se trouve.
cd "$(dirname "$0")"

echo "=== NO MORE WASTE -- installation ==="
echo

# -----------------------------------------------------------------------
# 1. Verifier que les outils necessaires sont presents
# -----------------------------------------------------------------------
# On verifie AVANT d'aller plus loin : mieux vaut un message clair tout de
# suite qu'une erreur incomprehensible au milieu du script.

if ! command -v docker >/dev/null 2>&1; then
    echo "Docker n'est pas installe. Voir https://docs.docker.com/engine/install/"
    exit 1
fi

if ! docker compose version >/dev/null 2>&1; then
    echo "Le plugin Docker Compose n'est pas disponible (commande 'docker compose')."
    exit 1
fi

if ! command -v curl >/dev/null 2>&1; then
    echo "curl est necessaire (utilise pour verifier que l'API repond, et pour"
    echo "creer le premier compte). Installe-le puis relance ce script."
    exit 1
fi

# -----------------------------------------------------------------------
# 2. Preparer le fichier .env
# -----------------------------------------------------------------------
# On ne touche JAMAIS a un .env qui existe deja : quelqu'un a peut-etre deja
# rempli ses vraies cles Brevo, changer le fichier sous ses pieds serait une
# mauvaise surprise. Le .env n'est cree qu'une seule fois, a la toute
# premiere installation.

if [ -f .env ]; then
    echo "[.env] deja present, conserve tel quel."
else
    echo "[.env] absent : creation depuis .env.example..."
    cp .env.example .env

    # Un secret JWT genere au hasard plutot que la valeur figee du modele.
    # Ce secret sert a signer les jetons de connexion (voir
    # api-go/utils/jwt.go.md) : le laisser identique sur toutes les
    # installations reviendrait a utiliser le meme mot de passe partout.
    #
    # tr -dc restreint aux caracteres alphanumeriques : ca evite tout souci
    # avec le "sed" juste apres (un "/" ou un "|" dans le secret casserait
    # la commande de remplacement).
    secret_genere=$(head -c 48 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 40)
    sed -i "s|JWT_SECRET=change_me_a_generer|JWT_SECRET=${secret_genere}|" .env

    echo "[.env] cree, avec un secret JWT genere aleatoirement."
    echo "        Pense a renseigner SMTP_USER / SMTP_PASSWORD (Brevo) dans .env"
    echo "        si tu veux que les emails (rappels, campagnes) partent reellement."
fi
echo

# On charge les variables du .env dans ce script : NGINX_PORT, POSTGRES_USER
# et POSTGRES_DB nous servent plus bas. "set -a" exporte automatiquement
# toute variable definie ensuite, "set +a" arrete ce comportement -- sans
# lui, TOUTES les variables de l'environnement du script seraient exportees,
# pas seulement celles du .env.
set -a
# shellcheck disable=SC1091
source .env
set +a

# -----------------------------------------------------------------------
# 2 bis. Rendre le dossier des traductions modifiable par le conteneur
# -----------------------------------------------------------------------
# Trouve en deployant pour de vrai : l'ecran /back/traductions repond
# "Ecriture impossible dans .../locales/ (verifiez les droits du dossier)"
# des qu'on clique sur "Base vers fichiers" ou "Fichiers vers base".
#
# LA CAUSE, PRECISEMENT
#
# PHP-FPM tourne en "www-data" A L'INTERIEUR du conteneur. Mais
# docker-compose.yml monte "./front-php" DEPUIS LE DISQUE DE L'HOTE
# ("volumes: - ./front-php:/var/www/app") : les permissions qui comptent
# sont donc celles du systeme de fichiers REEL de la machine, pas celles
# fixees dans le Dockerfile -- ce dernier ne les voit jamais, le montage
# les recouvre a chaque demarrage.
#
# Quand le depot est clone en root (le cas courant sur un serveur neuf, y
# compris celui utilise pour ce projet), seul root peut ecrire dans
# app/locales/ par defaut. "www-data", a l'interieur du conteneur, n'est
# ni root ni dans son groupe : ecriture refusee.
#
# "o+w" (write pour "others") plutot qu'un chown vers un utilisateur
# precis : la correspondance exacte entre l'UID de www-data cote conteneur
# et les UID cote hote varie d'une machine a l'autre, alors que "others"
# fonctionne quel que soit cet UID.
chmod -R o+w front-php/app/locales
echo "[locales] rendu modifiable par le conteneur (o+w)."
echo

# -----------------------------------------------------------------------
# 3. Demarrer les conteneurs
# -----------------------------------------------------------------------
# POURQUOI UN "if ! ... ; then" ET NON UN APPEL DIRECT
#
# Avec "set -e" (active en tete de script), une commande qui echoue arrete
# le script SUR-LE-CHAMP, avant meme d'afficher un message a nous -- seule
# l'erreur brute de Docker s'affiche, et tout ce qui suit (l'attente de
# l'API, la creation du compte admin) est saute EN SILENCE.
#
# C'est exactement ce qui s'est passe la premiere fois sur un serveur ou un
# autre programme (ici : code-server) occupait deja le port NGINX_PORT :
# nginx a echoue a demarrer, le script s'est arrete a cette ligne, et la
# partie interactive de l'etape 5 n'a jamais ete atteinte -- sans qu'aucun
# message du script lui-meme ne le dise.
#
# Mettre la commande dans un "if" est la seule maniere standard de recevoir
# la main malgre "set -e" : bash n'applique jamais set -e a une commande
# testee par if/while/until. On peut alors expliquer la panne et arreter
# proprement, plutot que de laisser le script mourir sans un mot.
echo "Demarrage des conteneurs (--build : peut prendre une minute la premiere fois)..."
if ! docker compose up -d --build; then
    echo
    echo "Le demarrage des conteneurs a echoue (voir le message de Docker ci-dessus)."
    echo "Cause frequente : un autre programme occupe deja le port \${NGINX_PORT}"
    echo "(ici : ${NGINX_PORT}). Verifie avec :"
    echo "    ss -tlnp | grep ${NGINX_PORT}"
    echo "Puis soit arrete ce programme, soit change NGINX_PORT dans .env et relance"
    echo "ce script -- il est rejouable, rien de ce qui a deja demarre ne sera perdu."
    exit 1
fi
echo

# -----------------------------------------------------------------------
# 4. Attendre que l'API reponde vraiment
# -----------------------------------------------------------------------
# "docker compose up -d" rend la main des que les conteneurs sont LANCES,
# pas des qu'ils sont PRETS. Postgres met quelques secondes a accepter des
# connexions ; sans cette attente, la creation du compte admin (etape 5)
# echouerait au hasard selon la vitesse de la machine.
#
# On interroge la route de sante de l'API (voir api-go/app.go.md,
# fonction healthCheck) via nginx, exactement comme le ferait un vrai
# navigateur. "curl -f" fait echouer curl si le code HTTP n'est pas un
# succes (donc aussi sur le 503 renvoye tant que la base n'est pas prete).

echo "Attente que l'API reponde..."
tentatives=0
until curl -sf "http://localhost:${NGINX_PORT}/api/" >/dev/null 2>&1; do
    tentatives=$((tentatives + 1))
    if [ "$tentatives" -ge 30 ]; then
        echo
        echo "L'API ne repond toujours pas apres 60 secondes."
        echo "Regarde ce qui se passe : docker compose logs api-go"
        exit 1
    fi
    sleep 2
done
echo "API prete."
echo

# -----------------------------------------------------------------------
# 5. Creer le tout premier compte administrateur
# -----------------------------------------------------------------------
# LE PROBLEME QUE CETTE ETAPE RESOUT :
#
# Creer un compte AVEC choix du role (POST /utilisateurs/) est reserve aux
# administrateurs (voir UtilisateursController.php.md). Sur un serveur tout
# juste installe, aucun compte n'existe encore -- donc personne ne peut
# creer le premier administrateur depuis l'application elle-meme. C'est un
# probleme d'oeuf et de poule qui n'a pas de solution purement applicative.
#
# La solution retenue ici est celle deja utilisee par les scripts de tests
# (tests/tester-tous-les-endpoints.py) : creer un compte normal via la route
# PUBLIQUE d'inscription (POST /auth/register/, qui cree toujours un
# "adherent"), puis le promouvoir en administrateur par une requete SQL
# directe. La difference avec les tests : ici c'est fait UNE fois, a
# l'installation, pas a chaque lancement d'une suite de tests.

compte_admin_existant=$(docker compose exec -T postgres psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -tAc \
    "SELECT count(*) FROM utilisateurs WHERE role='admin_back';" | tr -d '[:space:]')

if [ "$compte_admin_existant" -gt 0 ]; then
    echo "Un compte administrateur existe deja : rien a faire ici."
    admin_email="(deja existant)"
else
    echo "Aucun administrateur : creons le premier compte."
    echo

    read -rp "Email de l'administrateur : " admin_email
    while [[ "$admin_email" != *"@"* ]]; do
        echo "Adresse invalide (pas de @)."
        read -rp "Email de l'administrateur : " admin_email
    done

    while true; do
        read -rsp "Mot de passe (8 caracteres minimum) : " admin_mdp
        echo
        if [ "${#admin_mdp}" -ge 8 ]; then
            break
        fi
        echo "Trop court, reessaie."
    done

    # Etape 1 : creation normale, via la route publique. Le mot de passe est
    # hache par l'API elle-meme (bcrypt) -- ce script ne manipule jamais de
    # mot de passe en clair au-dela de cette requete.
    code_http=$(curl -s -o /dev/null -w "%{http_code}" \
        -X POST "http://localhost:${NGINX_PORT}/api/auth/register/" \
        -H "Content-Type: application/json" \
        -d "{\"email\":\"${admin_email}\",\"mot_de_passe\":\"${admin_mdp}\"}")

    if [ "$code_http" != "201" ]; then
        echo "Erreur lors de la creation du compte (code HTTP ${code_http})."
        echo "Le compte existe peut-etre deja avec un autre role -- verifie manuellement."
        exit 1
    fi

    # Etape 2 : promotion en admin_back, la seule partie qui passe par le SQL
    # direct (aucune route de l'API ne permet de changer le role d'un compte
    # existant -- volontairement, pour ne pas ouvrir cette possibilite en
    # dehors de ce script d'installation).
    docker compose exec -T postgres psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -c \
        "UPDATE utilisateurs SET role='admin_back' WHERE email='${admin_email}';" >/dev/null

    echo "Compte administrateur cree : ${admin_email}"
fi
echo

# -----------------------------------------------------------------------
# 6. Resume
# -----------------------------------------------------------------------
cat <<RESUME
========================================================================
 Installation terminee.
========================================================================

 Site (front-office)  : http://localhost:${NGINX_PORT}/
 Back-office           : http://localhost:${NGINX_PORT}/back
 API (Postman/tests)   : http://localhost:${NGINX_PORT}/api/

 Compte administrateur : ${admin_email}

 Pense a completer SMTP_USER / SMTP_PASSWORD dans .env (cles Brevo) le jour
 ou les emails (rappels d'adhesion, campagnes, plannings) doivent partir
 pour de vrai -- tant qu'ils valent "change_me", le site fonctionne, mais
 aucun email n'est envoye.
========================================================================
RESUME
