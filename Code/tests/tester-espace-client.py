# -*- coding: utf-8 -*-
"""Teste l'espace client du front-office (routes /mon-espace).

POURQUOI UN SCRIPT SEPARE

La collection Postman est rejouee avec UN seul compte administrateur. Or les
routes /mon-espace exigent d'etre commercant ou benevole -- avec un compte
admin elles repondent 403, ce qui est le comportement attendu mais rend le
test impossible dans le parcours principal.

Ce script monte donc le contexte complet : un compte adherent rattache a une
fiche commercant, un compte benevole rattache a une fiche benevole, puis
verifie ce qui compte vraiment -- l'ISOLATION DES DONNEES.

Usage :
    docker compose up -d
    python tests/tester-espace-client.py
"""
import json, io, os, subprocess, sys, urllib.request, urllib.error

DOSSIER_ICI = os.path.dirname(os.path.abspath(__file__))
# Voir tester-tous-les-endpoints.py : NMW_BASE_URL permet de pointer le
# script vers un autre port que celui du .env de developpement.
BASE = os.environ.get("NMW_BASE_URL", "http://localhost:8080") + "/api"

ADMIN = ("staff2@nomorewaste.fr", "motdepasse123")
COMMERCANT = ("client.test@nomorewaste.fr", "motdepasse123")
BENEVOLE = ("benevole.test@nomorewaste.fr", "motdepasse123")

resultats = []


def appeler(methode, chemin, corps=None, jeton=None):
    donnees = json.dumps(corps).encode("utf-8") if corps is not None else None
    req = urllib.request.Request(BASE + chemin, data=donnees, method=methode)
    if corps is not None:
        req.add_header("Content-Type", "application/json")
    if jeton:
        req.add_header("Authorization", jeton)
    try:
        with urllib.request.urlopen(req) as rep:
            return rep.status, rep.read()
    except urllib.error.HTTPError as e:
        return e.code, e.read()
    except Exception as e:
        return 0, str(e).encode()


def sql(requete):
    r = subprocess.run(
        ["docker", "compose", "exec", "-T", "postgres",
         "psql", "-U", "nmw_user", "-d", "nmw", "-t", "-c", requete],
        cwd=os.path.join(DOSSIER_ICI, ".."), capture_output=True, text=True)
    if r.returncode != 0:
        print("Requete SQL en echec :", r.stderr.strip()[:200])
        sys.exit(1)
    return r.stdout.strip()


def connexion(identifiants):
    code, contenu = appeler("POST", "/auth/login/",
                            {"email": identifiants[0], "mot_de_passe": identifiants[1]})
    if code != 200:
        print("Connexion impossible pour %s (code %s)" % (identifiants[0], code))
        sys.exit(1)
    return json.loads(contenu)["token"]


def verifier(libelle, attendu, obtenu, detail=""):
    ok = obtenu == attendu
    resultats.append((ok, libelle))
    marque = "OK    " if ok else "ECHEC "
    print("  [%s] attendu %-3s obtenu %-3s  %s%s"
          % (marque, attendu, obtenu, libelle, (" | " + detail) if detail and not ok else ""))


# ---------------------------------------------------------------------------
print("=" * 70)
print("PREPARATION DU CONTEXTE")
print("=" * 70)

jeton_admin = connexion(ADMIN)

# Les comptes peuvent deja exister (script rejouable) : 201 ou 409 conviennent.
for email, role in ((COMMERCANT[0], "adherent"), (BENEVOLE[0], "benevole")):
    code, _ = appeler("POST", "/utilisateurs/",
                      {"email": email, "mot_de_passe": "motdepasse123", "role": role},
                      jeton_admin)
    if code not in (201, 409):
        print("Creation du compte %s impossible (code %s)" % (email, code))
        sys.exit(1)

# Une fiche commercant rattachee au compte adherent.
#
# On REUTILISE la fiche si elle existe deja : rien n'empeche de creer deux
# commercants du meme nom, et en creer un nouveau a chaque passage laisserait
# des fiches orphelines derriere soi.
existant = sql("SELECT id FROM commercants "
               "WHERE raison_sociale='Commerce de test espace client' LIMIT 1;")

if existant:
    id_commercant = int(existant)
else:
    code, contenu = appeler("POST", "/commercants/",
                            {"raison_sociale": "Commerce de test espace client",
                             "ville": "Paris", "pays": "France",
                             "email": "contact@commerce-test.fr"}, jeton_admin)
    if code != 201:
        print("Creation du commercant de test impossible (code %s)" % code)
        sys.exit(1)
    id_commercant = json.loads(contenu)["id"]

sql("UPDATE commercants SET utilisateur_id=(SELECT id FROM utilisateurs WHERE email='%s') "
    "WHERE id=%d;" % (COMMERCANT[0], id_commercant))

# Une fiche benevole rattachee au compte benevole.
#
# Meme precaution que pour le commercant : la candidature publique ne verifie
# pas l'unicite de l'email, donc relancer le script creerait une deuxieme
# fiche. On rattacherait alors deux fiches au meme compte -- ce que la
# contrainte UNIQUE de la base refuse, a juste titre.
existant = sql("SELECT id FROM benevoles WHERE email='%s' ORDER BY id LIMIT 1;" % BENEVOLE[0])

if not existant:
    appeler("POST", "/benevoles/candidature/",
            {"nom": "Test", "prenom": "Espace", "email": BENEVOLE[0],
             "telephone": "0600000000", "permis_conduire": True})
    existant = sql("SELECT id FROM benevoles WHERE email='%s' ORDER BY id LIMIT 1;" % BENEVOLE[0])

id_benevole = int(existant)
sql("UPDATE benevoles SET utilisateur_id=(SELECT id FROM utilisateurs WHERE email='%s') "
    "WHERE id=%d;" % (BENEVOLE[0], id_benevole))

jeton_commercant = connexion(COMMERCANT)
jeton_benevole = connexion(BENEVOLE)

print("contexte pret : commercant #%d et benevole rattaches a leur compte\n" % id_commercant)

# ---------------------------------------------------------------------------
print("=" * 70)
print("ESPACE COMMERCANT")
print("=" * 70)

code, contenu = appeler("GET", "/mon-espace/commercant", jeton=jeton_commercant)
verifier("voir sa fiche et ses adhesions", 200, code)

if code == 200:
    donnees = json.loads(contenu)
    # Le point essentiel : c'est bien SA fiche, retrouvee via son seul jeton.
    verifier("la fiche retournee est la sienne", id_commercant,
             donnees["commercant"]["id"])

code, _ = appeler("GET", "/mon-espace/collectes", jeton=jeton_commercant)
verifier("voir ses collectes", 200, code)

code, contenu = appeler("POST", "/mon-espace/collectes",
                        {"date_prevue": "2026-09-15"}, jeton_commercant)
verifier("demander une collecte", 201, code)

if code == 201:
    verifier("la demande est au statut 'demandee'", "demandee",
             json.loads(contenu)["statut"])

code, _ = appeler("POST", "/mon-espace/collectes", {}, jeton_commercant)
verifier("demande sans date refusee", 400, code)

# ---------------------------------------------------------------------------
print()
print("=" * 70)
print("ESPACE BENEVOLE")
print("=" * 70)

code, contenu = appeler("GET", "/mon-espace/benevole", jeton=jeton_benevole)
verifier("voir sa fiche, ses documents et ses competences", 200, code)

if code == 200:
    donnees = json.loads(contenu)
    for cle in ("benevole", "documents", "competences"):
        verifier("la reponse contient '%s'" % cle, True, cle in donnees)

code, _ = appeler("GET", "/mon-espace/planning", jeton=jeton_benevole)
verifier("voir son planning a venir", 200, code)

# ---------------------------------------------------------------------------
print()
print("=" * 70)
print("ISOLATION DES DONNEES  (le point critique)")
print("=" * 70)

code, _ = appeler("GET", "/mon-espace/commercant", jeton=jeton_benevole)
verifier("un benevole n'accede pas a l'espace commercant", 403, code)

code, _ = appeler("GET", "/mon-espace/benevole", jeton=jeton_commercant)
verifier("un commercant n'accede pas a l'espace benevole", 403, code)

code, _ = appeler("GET", "/mon-espace/commercant", jeton=jeton_admin)
verifier("le personnel passe par le back-office, pas par l'espace client", 403, code)

code, _ = appeler("GET", "/mon-espace/collectes")
verifier("sans jeton, tout est refuse", 401, code)

# Le test le plus important : un adherent ne doit pas pouvoir se promouvoir.
code, _ = appeler("POST", "/utilisateurs/",
                  {"email": "pirate@test.fr", "mot_de_passe": "motdepasse123",
                   "role": "admin_back"}, jeton_commercant)
verifier("un adherent ne peut pas creer un compte administrateur", 403, code)

code, _ = appeler("POST", "/utilisateurs/",
                  {"email": "x@test.fr", "mot_de_passe": "motdepasse123",
                   "role": "super_admin"}, jeton_admin)
verifier("un role invente est refuse", 400, code)

# ---------------------------------------------------------------------------
print()
print("=" * 70)
print("AGIR EN SON NOM PROPRE, ET PAS AU NOM D'UN AUTRE")
print("=" * 70)

# Ces trois verifications couvrent des failles reelles, trouvees en portant
# les ecrans de l'espace client -- alors que toutes les suites etaient au vert.
# La regle commune : un identifiant envoye par le client ne designe JAMAIS
# QUI agit. Cette information vient du jeton, et de lui seul.

# On fabrique une deuxieme boutique, celle d'un tiers, pour verifier qu'on ne
# peut pas agir a sa place.
autre = sql("SELECT id FROM commercants WHERE raison_sociale='Boutique d un tiers' LIMIT 1;")
if not autre:
    code, contenu = appeler("POST", "/commercants/",
                            {"raison_sociale": "Boutique d un tiers",
                             "ville": "Lyon"}, jeton_admin)
    autre = json.loads(contenu)["id"] if code == 201 else 0
id_autre_commercant = int(autre)

# Il faut un creneau ouvert pour tester l'inscription.
id_creneau = sql("SELECT id FROM creneaux_service WHERE statut='ouvert' "
                 "ORDER BY id LIMIT 1;")

if id_creneau and id_autre_commercant:
    id_creneau = int(id_creneau)

    # 1. Un adherent s'inscrit en pretendant etre quelqu'un d'autre.
    #    Avant correction : 201, et c'est bien l'autre boutique qui etait
    #    inscrite. Maintenant : 201 aussi, mais c'est LUI qui est inscrit.
    code, _ = appeler("POST", "/creneaux/%d/inscriptions" % id_creneau,
                      {"commercant_id": id_autre_commercant}, jeton_commercant)
    verifier("un adherent peut s'inscrire a un creneau", 201, code)

    inscrit = sql("SELECT commercant_id FROM inscriptions_service "
                  "WHERE creneau_id=%d ORDER BY id DESC LIMIT 1;" % id_creneau)
    verifier("l'inscription est faite en SON nom, pas celui du tiers",
             str(id_commercant), str(inscrit))

    # 2. Le statut ne se choisit pas : on ne s'inscrit pas "present".
    appeler("POST", "/creneaux/%d/inscriptions" % id_creneau,
            {"statut": "present"}, jeton_commercant)
    statut = sql("SELECT statut FROM inscriptions_service "
                 "WHERE creneau_id=%d ORDER BY id DESC LIMIT 1;" % id_creneau)
    verifier("le statut d'inscription est impose par l'API", "inscrit", str(statut))

    # 3. Le personnel, lui, inscrit legitimement autrui -- mais doit dire qui.
    code, _ = appeler("POST", "/creneaux/%d/inscriptions" % id_creneau,
                      {}, jeton_admin)
    verifier("le personnel doit preciser qui il inscrit", 400, code)

# 4. La candidature benevole est PUBLIQUE : un corps forge ne doit pas
#    permettre de s'accrocher au compte d'un autre.
code, contenu = appeler("POST", "/benevoles/candidature/",
                        {"nom": "Forge", "prenom": "Corps", "utilisateur_id": 1})
verifier("une candidature anonyme est acceptee", 201, code)

if code == 201:
    id_forge = json.loads(contenu)["id"]
    rattache = sql("SELECT COALESCE(utilisateur_id, 0) FROM benevoles WHERE id=%d;" % id_forge)
    verifier("le compte vise dans le corps est ignore", "0", str(rattache))
    sql("DELETE FROM benevoles WHERE id=%d;" % id_forge)

# ---------------------------------------------------------------------------
echecs = [r for r in resultats if not r[0]]
print()
print("=" * 70)
print("TOTAL : %d verifications | %d OK | %d en echec"
      % (len(resultats), len(resultats) - len(echecs), len(echecs)))
print("=" * 70)
for _, libelle in echecs:
    print("  ECHEC :", libelle)

sys.exit(1 if echecs else 0)
