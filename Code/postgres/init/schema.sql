CREATE TABLE utilisateurs (
    id BIGSERIAL PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    mot_de_passe_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL CHECK (role IN ('admin_back', 'staff_back', 'adherent', 'benevole')),
    nom VARCHAR(100),
    prenom VARCHAR(100),
    date_naissance DATE,
    telephone VARCHAR(30),
    actif BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE langues (
    code VARCHAR(5) PRIMARY KEY,
    libelle VARCHAR(50) NOT NULL
);

INSERT INTO langues (code, libelle) VALUES
    ('fr', 'Français'),
    ('en', 'English'),
    ('it', 'Italiano'),
    ('pt', 'Português');

-- Traductions de l'interface, gerees depuis le back-office.
--
-- Chaque ligne = un libelle, dans une langue.
-- Exemple : ('connexion.titre', 'Connexion', 'fr')
--           ('connexion.titre', 'Sign in',   'en')
--
-- La base est la SOURCE DE VERITE. Le front, lui, ne lit pas cette table a
-- chaque affichage de libelle : ce serait des dizaines de requetes par page.
-- Il lit des fichiers JSON (front-php/app/locales/) regeneres depuis la base
-- par un bouton du back-office. La base sert a EDITER, les fichiers a LIRE.
CREATE TABLE traductions (
    id BIGSERIAL PRIMARY KEY,
    cle VARCHAR(100) NOT NULL,
    valeur TEXT NOT NULL,
    code_langue VARCHAR(5) NOT NULL REFERENCES langues(code) ON DELETE CASCADE,

    -- Une meme cle ne peut exister qu'une fois par langue. Sans cette
    -- contrainte, deux enregistrements concurrents pour 'nav.accueil' en
    -- francais rendraient l'affichage imprevisible.
    UNIQUE (cle, code_langue)
);

-- La recherche se fait toujours "toutes les cles d'une langue" au moment de
-- regenerer un fichier JSON : c'est cette colonne qu'on filtre.
CREATE INDEX idx_traductions_langue ON traductions(code_langue);

CREATE TABLE sites (
    id BIGSERIAL PRIMARY KEY,
    ville VARCHAR(100) NOT NULL,
    pays VARCHAR(100) NOT NULL,
    adresse VARCHAR(255),
    code_langue_defaut VARCHAR(5) REFERENCES langues(code)
);

CREATE TABLE commercants (
    id BIGSERIAL PRIMARY KEY,
    raison_sociale VARCHAR(255) NOT NULL,
    siret VARCHAR(20),
    adresse VARCHAR(255),
    ville VARCHAR(100),
    pays VARCHAR(100),
    email VARCHAR(255),
    telephone VARCHAR(30),
    contact_nom VARCHAR(150),
    -- UNIQUE : un compte ne peut etre rattache qu'a UNE fiche.
    -- Sans cette contrainte, deux fiches pouvaient pointer vers le meme
    -- compte, et la recherche "quelle est MA fiche ?" devenait
    -- imprevisible : elle renvoyait l'une ou l'autre selon l'humeur de
    -- la base. NULL reste autorise plusieurs fois (PostgreSQL ne
    -- considere pas deux NULL comme egaux) : une fiche peut donc exister
    -- sans compte associe.
    utilisateur_id BIGINT UNIQUE REFERENCES utilisateurs(id),
    site_id BIGINT REFERENCES sites(id),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE adhesions (
    id BIGSERIAL PRIMARY KEY,
    commercant_id BIGINT NOT NULL REFERENCES commercants(id),
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    statut VARCHAR(20) NOT NULL CHECK (statut IN ('active', 'expiree', 'resiliee', 'en_attente')),
    montant_cotisation NUMERIC(10, 2),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Historique des emails de rappel/relance envoyes pour une adhesion.
-- Remplace l'ancien simple booleen "rappel_envoye" : on a maintenant plusieurs
-- types de rappel possibles (J-30, J-7, relance ex-abonne), donc il faut
-- pouvoir savoir LEQUEL a deja ete envoye, pas juste "un rappel a ete envoye".
CREATE TABLE adhesion_rappels (
    id BIGSERIAL PRIMARY KEY,
    adhesion_id BIGINT NOT NULL REFERENCES adhesions(id),
    type_rappel VARCHAR(30) NOT NULL CHECK (type_rappel IN ('j30', 'j7', 'ex_abonne', 'manuel')),
    date_envoi TIMESTAMPTZ NOT NULL DEFAULT now(),
    email_destinataire VARCHAR(255) NOT NULL
);

CREATE INDEX idx_adhesion_rappels_adhesion_id ON adhesion_rappels(adhesion_id);

-- Une campagne = un segment de commercants defini par des criteres fixes,
-- que le staff peut creer depuis le back-office et declencher pour envoyer
-- un email a tous les commercants qui correspondent aux criteres.
CREATE TABLE campagnes (
    id BIGSERIAL PRIMARY KEY,
    nom VARCHAR(150) NOT NULL,
    sujet_email VARCHAR(255) NOT NULL,
    corps_email TEXT NOT NULL,
    -- criteres de segmentation, tous optionnels (NULL = pas de filtre sur ce critere)
    critere_ville VARCHAR(100),
    critere_pays VARCHAR(100),
    critere_statut_adhesion VARCHAR(20) CHECK (critere_statut_adhesion IN ('active', 'expiree', 'resiliee', 'en_attente')),
    critere_adhesion_expiree_depuis_jours INT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE campagne_envois (
    id BIGSERIAL PRIMARY KEY,
    campagne_id BIGINT NOT NULL REFERENCES campagnes(id),
    commercant_id BIGINT NOT NULL REFERENCES commercants(id),
    date_envoi TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE benevoles (
    id BIGSERIAL PRIMARY KEY,
    -- UNIQUE : un compte ne peut etre rattache qu'a UNE fiche.
    -- Sans cette contrainte, deux fiches pouvaient pointer vers le meme
    -- compte, et la recherche "quelle est MA fiche ?" devenait
    -- imprevisible : elle renvoyait l'une ou l'autre selon l'humeur de
    -- la base. NULL reste autorise plusieurs fois (PostgreSQL ne
    -- considere pas deux NULL comme egaux) : une fiche peut donc exister
    -- sans compte associe.
    utilisateur_id BIGINT UNIQUE REFERENCES utilisateurs(id),
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(255),
    telephone VARCHAR(30),
    adresse VARCHAR(255),
    statut VARCHAR(20) NOT NULL CHECK (statut IN ('candidat', 'en_validation', 'valide', 'refuse', 'inactif')),
    permis_conduire BOOLEAN NOT NULL DEFAULT false,
    date_candidature DATE NOT NULL DEFAULT CURRENT_DATE,
    date_validation DATE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE competences (
    id BIGSERIAL PRIMARY KEY,
    libelle VARCHAR(100) UNIQUE NOT NULL
);

INSERT INTO competences (libelle) VALUES
    ('chauffeur'), ('cuisinier'), ('plombier'), ('electricien'), ('bricoleur');

CREATE TABLE benevole_competences (
    benevole_id BIGINT NOT NULL REFERENCES benevoles(id),
    competence_id BIGINT NOT NULL REFERENCES competences(id),
    PRIMARY KEY (benevole_id, competence_id)
);

CREATE TABLE benevole_documents (
    id BIGSERIAL PRIMARY KEY,
    benevole_id BIGINT NOT NULL REFERENCES benevoles(id),
    type_document VARCHAR(100) NOT NULL,
    chemin_fichier VARCHAR(255),
    valide BOOLEAN NOT NULL DEFAULT false
);

CREATE TABLE emplacements_stock (
    id BIGSERIAL PRIMARY KEY,
    entrepot VARCHAR(100) NOT NULL,
    zone VARCHAR(50),
    rayon VARCHAR(50),
    etagere VARCHAR(50)
);

CREATE TABLE collectes (
    id BIGSERIAL PRIMARY KEY,
    commercant_id BIGINT REFERENCES commercants(id),
    particulier_nom VARCHAR(150),
    particulier_adresse VARCHAR(255),
    benevole_id BIGINT REFERENCES benevoles(id),
    date_prevue TIMESTAMPTZ,
    date_realisee TIMESTAMPTZ,
    statut VARCHAR(20) NOT NULL CHECK (statut IN ('demandee', 'planifiee', 'realisee', 'annulee')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE produits (
    id BIGSERIAL PRIMARY KEY,
    code_barre VARCHAR(50) UNIQUE NOT NULL,
    libelle VARCHAR(255) NOT NULL,
    categorie VARCHAR(100),
    dlc DATE,
    collecte_id BIGINT REFERENCES collectes(id),
    poids_kg NUMERIC(10, 3),
    quantite INT NOT NULL DEFAULT 1,
    emplacement_id BIGINT REFERENCES emplacements_stock(id),
    statut VARCHAR(20) NOT NULL CHECK (statut IN ('en_stock', 'reserve', 'distribue', 'perime')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_produits_code_barre ON produits(code_barre);

CREATE TABLE beneficiaires (
    id BIGSERIAL PRIMARY KEY,
    type VARCHAR(30) NOT NULL CHECK (type IN ('association_caritative', 'particulier_detresse')),
    nom VARCHAR(150) NOT NULL,
    adresse VARCHAR(255),
    ville VARCHAR(100),
    telephone VARCHAR(30),
    contact VARCHAR(150)
);

CREATE TABLE tournees (
    id BIGSERIAL PRIMARY KEY,
    date_tournee DATE NOT NULL,
    benevole_id BIGINT REFERENCES benevoles(id),
    statut VARCHAR(20) NOT NULL CHECK (statut IN ('planifiee', 'en_cours', 'terminee', 'annulee')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE tournee_etapes (
    id BIGSERIAL PRIMARY KEY,
    tournee_id BIGINT NOT NULL REFERENCES tournees(id),
    beneficiaire_id BIGINT NOT NULL REFERENCES beneficiaires(id),
    ordre INT NOT NULL,
    heure_prevue TIME,
    heure_reelle TIME,
    statut VARCHAR(20) NOT NULL CHECK (statut IN ('a_faire', 'livre', 'absent'))
);

CREATE TABLE livraisons (
    id BIGSERIAL PRIMARY KEY,
    tournee_etape_id BIGINT NOT NULL REFERENCES tournee_etapes(id),
    date_livraison TIMESTAMPTZ NOT NULL DEFAULT now(),
    pdf_genere_path VARCHAR(255),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE livraison_produits (
    livraison_id BIGINT NOT NULL REFERENCES livraisons(id),
    produit_id BIGINT NOT NULL REFERENCES produits(id),
    quantite INT NOT NULL DEFAULT 1,
    PRIMARY KEY (livraison_id, produit_id)
);

CREATE TABLE services (
    id BIGSERIAL PRIMARY KEY,
    nom VARCHAR(150) NOT NULL,
    description TEXT,
    competence_requise_id BIGINT REFERENCES competences(id),
    type VARCHAR(30) NOT NULL CHECK (type IN (
        'conseil_anti_gaspi', 'cours_cuisine', 'partage_vehicule',
        'echange_service', 'reparation', 'gardiennage', 'autre'
    )),
    actif BOOLEAN NOT NULL DEFAULT true
);

CREATE TABLE creneaux_service (
    id BIGSERIAL PRIMARY KEY,
    service_id BIGINT NOT NULL REFERENCES services(id),
    benevole_id BIGINT REFERENCES benevoles(id),
    date_creneau DATE NOT NULL,
    heure_debut TIME NOT NULL,
    heure_fin TIME NOT NULL,
    lieu VARCHAR(255),
    capacite_max INT NOT NULL DEFAULT 1,
    statut VARCHAR(20) NOT NULL CHECK (statut IN ('ouvert', 'complet', 'annule', 'realise'))
);

CREATE TABLE inscriptions_service (
    id BIGSERIAL PRIMARY KEY,
    creneau_id BIGINT NOT NULL REFERENCES creneaux_service(id),
    commercant_id BIGINT REFERENCES commercants(id),
    utilisateur_id BIGINT REFERENCES utilisateurs(id),
    date_inscription TIMESTAMPTZ NOT NULL DEFAULT now(),
    statut VARCHAR(20) NOT NULL CHECK (statut IN ('inscrit', 'annule', 'present'))
);
