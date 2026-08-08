# -*- coding: utf-8 -*-
"""Rejoue toutes les requetes de la collection Postman contre l'API qui tourne,
et verifie que chaque exemple documente fonctionne vraiment.
"""
import json, io, os, subprocess, urllib.request, urllib.error, sys

# Chemin calcule par rapport a CE fichier : le script marche donc quel que soit
# l'endroit d'ou on le lance, et sur n'importe quelle machine.
DOSSIER_ICI = os.path.dirname(os.path.abspath(__file__))
COLLECTION = os.path.join(DOSSIER_ICI, "..", "api-go", "NO-MORE-WASTE.postman_collection.json")

BASE = "http://localhost:8080/api"

# La relance manuelle envoie un vrai email : elle echoue tant que les
# identifiants SMTP du fichier .env sont les valeurs par defaut.
ECHECS_ATTENDUS = {
    "Relance manuelle (echoue en 502 tant que le SMTP n'est pas configure)",
}

# --- Compte de test utilise par la collection ---
COMPTE_EMAIL = "staff2@nomorewaste.fr"
COMPTE_MOT_DE_PASSE = "motdepasse123"

# Tables a vider avant chaque execution.
#
# On garde volontairement "langues" et "competences" : ce sont des donnees de
# REFERENCE inserees par schema.sql au premier demarrage (fr/en/it/pt, et
# chauffeur/cuisinier/plombier/electricien/bricoleur). Les vider casserait les
# requetes de la collection qui utilisent /competences/1, /competences/2...
#
# "traductions" en revanche est bien videe : c'est une donnee metier, que le
# back-office recree en important les fichiers JSON du front.
TABLES_A_VIDER = [
    "adhesion_rappels", "adhesions", "beneficiaires", "benevole_competences",
    "benevole_documents", "benevoles", "campagne_envois", "campagnes",
    "collectes", "commercants", "creneaux_service", "emplacements_stock",
    "inscriptions_service", "livraison_produits", "livraisons", "produits",
    "services", "sites", "tournee_etapes", "tournees", "traductions",
    "utilisateurs",
]


def executer_sql(requete, description):
    """Execute une requete SQL via psql dans le conteneur Postgres.

    On passe par psql faute d'endpoint prevu pour ces operations
    d'administration (vider des tables, promouvoir un compte).
    """
    resultat = subprocess.run(
        ["docker", "compose", "exec", "-T", "postgres",
         "psql", "-U", "nmw_user", "-d", "nmw", "-c", requete],
        cwd=os.path.join(DOSSIER_ICI, ".."),
        capture_output=True, text=True,
    )
    if resultat.returncode != 0:
        print("%s : echec." % description)
        print(resultat.stderr.strip()[:300])
        print("\nLes conteneurs tournent-ils ? Verifie : docker compose ps")
        sys.exit(1)
    return resultat


def reinitialiser_donnees():
    """Vide les donnees metier pour que le script soit REJOUABLE.

    Le probleme resolu : la collection cree des donnees uniques (un code-barre,
    un email, une competence attribuee...). A la deuxieme execution, l'API
    repondait donc 409 "existe deja" sur sept requetes -- ce qui donnait
    l'impression d'une regression alors que l'API se comportait correctement.

    Un script de test qui ne fonctionne qu'une seule fois ne sert a rien : on
    le lance avant une demo, apres une modification... Il doit repartir propre
    a chaque fois.

    RESTART IDENTITY est le detail indispensable : il remet les compteurs
    d'identifiants a 1. Sans lui, le premier commercant cree porterait le
    numero 6 apres cinq executions, alors que la collection demande
    /commercants/1 -- et tout echouerait en 404.

    CASCADE laisse PostgreSQL gerer l'ordre des suppressions malgre les
    liens entre tables (une livraison depend d'une etape, qui depend d'une
    tournee...).
    """
    executer_sql(
        "TRUNCATE TABLE %s RESTART IDENTITY CASCADE;" % ", ".join(TABLES_A_VIDER),
        "Reinitialisation des donnees",
    )
    print("Donnees metier reinitialisees (langues et competences conservees).")


def preparer_compte_admin():
    """S'assure que le compte de test existe ET qu'il est administrateur.

    Pourquoi c'est necessaire : apres une reinitialisation de la base
    (docker volume rm code_pgdata), plus aucun compte n'existe. Toutes les
    requetes protegees echoueraient alors en 401, ce qui donnerait l'impression
    que l'API est cassee alors qu'il manque seulement un compte.

    Deux etapes, parce que l'API ne sait pas encore creer un compte staff :
      1. POST /auth/register/  cree le compte avec le role "adherent" ;
      2. une requete SQL le passe en "admin_back".

    L'etape 2 passe par psql dans le conteneur Postgres, faute d'endpoint
    prevu pour ca (c'est le dernier trou connu de l'API, a combler avec
    l'ecran "utilisateurs" du back-office).
    """
    # Etape 1 : creation. Un 409 signifie "existe deja", ce qui nous convient.
    code, _ = appeler(
        "POST",
        BASE + "/auth/register/",
        json.dumps({"email": COMPTE_EMAIL, "mot_de_passe": COMPTE_MOT_DE_PASSE}),
        {"Content-Type": "application/json"},
    )
    if code not in (201, 409):
        print("Impossible de creer le compte de test (code %s)." % code)
        print("L'API repond-elle ? Verifie : docker compose ps")
        sys.exit(1)

    # Etape 2 : promotion en administrateur.
    executer_sql(
        "UPDATE utilisateurs SET role='admin_back' WHERE email='%s';" % COMPTE_EMAIL,
        "Promotion en admin_back",
    )

    print("Compte de test pret : %s (admin_back)\n" % COMPTE_EMAIL)


col = json.load(io.open(COLLECTION, encoding="utf-8"))
token = {"valeur": ""}

ATTENDU_OK = (200, 201, 204)
resultats = []


def appeler(methode, url, corps, entetes):
    donnees = corps.encode("utf-8") if corps else None
    req = urllib.request.Request(url, data=donnees, method=methode)
    for cle, val in entetes.items():
        req.add_header(cle, val)
    try:
        with urllib.request.urlopen(req) as rep:
            return rep.status, rep.read()
    except urllib.error.HTTPError as e:
        return e.code, e.read()
    except Exception as e:
        return 0, str(e).encode()


# --- Preparation ---
#
# L'option --garder-donnees permet de lancer le script SANS vider la base,
# par exemple pour tester sur un jeu de donnees existant. Par defaut on
# reinitialise, pour que le script soit rejouable autant de fois qu'on veut.
if "--garder-donnees" not in sys.argv:
    reinitialiser_donnees()

preparer_compte_admin()

for dossier in col["item"]:
    print("\n" + "=" * 70)
    print(dossier["name"])
    print("=" * 70)
    for item in dossier["item"]:
        req = item["request"]
        url = req["url"]["raw"].replace("{{base_url}}", BASE)
        corps = req.get("body", {}).get("raw")
        entetes = {}
        for h in req.get("header", []):
            valeur = h["value"].replace("{{token}}", token["valeur"])
            entetes[h["key"]] = valeur

        code, contenu = appeler(req["method"], url, corps, entetes)

        # Capture du token apres la connexion
        if url.endswith("/auth/login/") and code == 200:
            token["valeur"] = json.loads(contenu)["token"]

        attendu = item["name"] in ECHECS_ATTENDUS
        ok = code in ATTENDU_OK or attendu
        if attendu:
            marque = "IGNORE"
        elif ok:
            marque = "OK    "
        else:
            marque = "ECHEC "
        resultats.append((ok, dossier["name"], item["name"], code))
        apercu = ""
        if not ok:
            apercu = " | " + contenu.decode("utf-8", "replace")[:110].replace("\n", " ")
        print("  [%s] %3d  %-55s%s" % (marque, code, item["name"][:55], apercu))

echecs = [r for r in resultats if not r[0]]
print("\n" + "=" * 70)
print("TOTAL : %d requetes | %d OK | %d en echec" % (len(resultats), len(resultats) - len(echecs), len(echecs)))
print("=" * 70)
for _, dos, nom, code in echecs:
    print("  ECHEC %3d  %s > %s" % (code, dos, nom))

# --- Menage de sortie ---
#
# La collection cree quelques traductions "demo.*" pour demontrer le CRUD.
# Elles restent en base a la fin du script -- et si on clique ensuite sur
# "Base vers fichiers" dans le back-office, elles remontent dans les fichiers
# de langue du site. On les retire donc ici, une fois les tests passes.
#
# Rappel : ce script VIDE la table des traductions au demarrage. Apres l'avoir
# lance, le site n'a plus aucun libelle en base. Pour les restaurer :
# /back/traductions -> "Fichiers vers base".
if "--garder-donnees" not in sys.argv:
    executer_sql(
        "DELETE FROM traductions WHERE cle LIKE 'demo.%' OR cle LIKE 'test.%';",
        "Menage des traductions de demonstration",
    )
    print("\nTraductions de demonstration supprimees.")
    print("La table des traductions a ete videe : pour restaurer les libelles")
    print("du site, va sur /back/traductions et clique \"Fichiers vers base\".")

# Code de sortie 1 s'il reste un echec : utile si un jour on branche ce script
# sur un outil automatique (il saura que quelque chose ne va pas).
sys.exit(1 if echecs else 0)
