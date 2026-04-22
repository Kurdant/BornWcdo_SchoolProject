-- ============================================================
-- WCDO - Borne de Commande McDonald's
-- Script DDL - Création complète de la base de données
-- SGBD : MariaDB 10.11
-- Normalisation : 3NF
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ── 1. CATEGORIE ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `CATEGORIE` (
    `id`    BIGINT       NOT NULL AUTO_INCREMENT,
    `nom`   VARCHAR(100) NOT NULL,
    `image` VARCHAR(255) NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_categorie_nom` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. SAUCE ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `SAUCE` (
    `id`    BIGINT       NOT NULL AUTO_INCREMENT,
    `nom`   VARCHAR(100) NOT NULL,
    `image` VARCHAR(255) NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_sauce_nom` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. TAILLE_BOISSON ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `TAILLE_BOISSON` (
    `id`               BIGINT        NOT NULL AUTO_INCREMENT,
    `nom`              VARCHAR(50)   NOT NULL,
    `volume`           INT           NOT NULL,
    `supplement_prix`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_taille_nom` (`nom`),
    CONSTRAINT `chk_taille_volume`     CHECK (`volume` > 0),
    CONSTRAINT `chk_taille_supplement` CHECK (`supplement_prix` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 4. ADMIN ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ADMIN` (
    `id`           BIGINT       NOT NULL AUTO_INCREMENT,
    `nom`          VARCHAR(100) NOT NULL,
    `email`        VARCHAR(255) NOT NULL,
    `mot_de_passe` VARCHAR(255) NOT NULL,
    `role`         ENUM('administration','preparation','accueil') NOT NULL DEFAULT 'administration',
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_admin_email` (`email`),
    INDEX `idx_admin_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 5. CLIENT ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `CLIENT` (
    `id`               BIGINT       NOT NULL AUTO_INCREMENT,
    `prenom`           VARCHAR(100) NOT NULL,
    `nom`              VARCHAR(100) NOT NULL,
    `email`            VARCHAR(255) NOT NULL,
    `mot_de_passe`     VARCHAR(255) NOT NULL,
    `points_fidelite`  INT          NOT NULL DEFAULT 0,
    `date_creation`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_client_email`  (`email`),
    INDEX `idx_client_points` (`points_fidelite`),
    CONSTRAINT `chk_client_points` CHECK (`points_fidelite` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 6. PRODUIT ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `PRODUIT` (
    `id`            BIGINT        NOT NULL AUTO_INCREMENT,
    `nom`           VARCHAR(200)  NOT NULL,
    `description`   TEXT          NULL,
    `prix`          DECIMAL(10,2) NOT NULL,
    `stock`         INT           NOT NULL DEFAULT 0,
    `id_categorie`  BIGINT        NOT NULL,
    `image`         VARCHAR(255)  NULL,
    `disponible`    TINYINT(1)    NOT NULL DEFAULT 1,
    `date_creation` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_produit_categorie`  (`id_categorie`),
    INDEX `idx_produit_stock`      (`stock`),
    INDEX `idx_produit_nom`        (`nom`),
    INDEX `idx_produit_disponible` (`disponible`),
    CONSTRAINT `fk_produit_categorie` FOREIGN KEY (`id_categorie`)
        REFERENCES `CATEGORIE`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_produit_prix`  CHECK (`prix` > 0),
    CONSTRAINT `chk_produit_stock` CHECK (`stock` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 7. PANIER ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `PANIER` (
    `id`           BIGINT       NOT NULL AUTO_INCREMENT,
    `session_id`   VARCHAR(255) NOT NULL,
    `date_creation` TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `client_id`    BIGINT       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_panier_session` (`session_id`),
    INDEX `idx_panier_client`  (`client_id`),
    CONSTRAINT `fk_panier_client` FOREIGN KEY (`client_id`)
        REFERENCES `CLIENT`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 8. COMMANDE ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `COMMANDE` (
    `id`               BIGINT        NOT NULL AUTO_INCREMENT,
    `numero_commande`  VARCHAR(20)   NOT NULL,
    `numero_chevalet`  INT           NOT NULL,
    `type_commande`    ENUM('sur_place','a_emporter') NOT NULL,
    `mode_paiement`    ENUM('carte','especes')        NOT NULL,
    `montant_total`    DECIMAL(10,2) NOT NULL,
    `statut`           ENUM('en_attente','preparee','livree') NOT NULL DEFAULT 'en_attente',
    `heure_livraison`  TIMESTAMP     NULL DEFAULT NULL,
    `date_creation`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `client_id`        BIGINT        NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_commande_numero`      (`numero_commande`),
    INDEX `idx_commande_date`               (`date_creation`),
    INDEX `idx_commande_client`             (`client_id`),
    INDEX `idx_commande_chevalet`           (`numero_chevalet`),
    INDEX `idx_commande_statut`             (`statut`),
    INDEX `idx_commande_heure_livraison`    (`heure_livraison`),
    CONSTRAINT `fk_commande_client` FOREIGN KEY (`client_id`)
        REFERENCES `CLIENT`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_commande_chevalet` CHECK (`numero_chevalet` BETWEEN 1 AND 999),
    CONSTRAINT `chk_commande_montant`  CHECK (`montant_total` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 8b. MENU ─────────────────────────────────────────────────
-- Un menu est une combinaison de produits vendue à prix fixe
CREATE TABLE IF NOT EXISTS `MENU` (
    `id`            BIGINT        NOT NULL AUTO_INCREMENT,
    `nom`           VARCHAR(200)  NOT NULL,
    `description`   TEXT          NULL,
    `prix`          DECIMAL(10,2) NOT NULL,
    `image`         VARCHAR(255)  NULL,
    `disponible`    TINYINT(1)    NOT NULL DEFAULT 1,
    `date_creation` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_menu_nom`        (`nom`),
    INDEX `idx_menu_disponible` (`disponible`),
    CONSTRAINT `chk_menu_prix` CHECK (`prix` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 8c. MENU_PRODUIT ─────────────────────────────────────────
-- Composition d'un menu : quels produits et en quelle quantité
CREATE TABLE IF NOT EXISTS `MENU_PRODUIT` (
    `id_menu`    BIGINT NOT NULL,
    `id_produit` BIGINT NOT NULL,
    `quantite`   INT    NOT NULL DEFAULT 1,
    PRIMARY KEY (`id_menu`, `id_produit`),
    INDEX `idx_mp_menu`    (`id_menu`),
    INDEX `idx_mp_produit` (`id_produit`),
    CONSTRAINT `fk_mp_menu`    FOREIGN KEY (`id_menu`)
        REFERENCES `MENU`(`id`)    ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT `fk_mp_produit` FOREIGN KEY (`id_produit`)
        REFERENCES `PRODUIT`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_mp_quantite` CHECK (`quantite` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 9. PANIER_PRODUIT ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `PANIER_PRODUIT` (
    `id`            BIGINT        NOT NULL AUTO_INCREMENT,
    `id_panier`     BIGINT        NOT NULL,
    `id_produit`    BIGINT        NOT NULL,
    `quantite`      INT           NOT NULL DEFAULT 1,
    `prix_unitaire` DECIMAL(10,2) NOT NULL,
    `details`       JSON          NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_pp_panier`  (`id_panier`),
    INDEX `idx_pp_produit` (`id_produit`),
    CONSTRAINT `fk_pp_panier`  FOREIGN KEY (`id_panier`)
        REFERENCES `PANIER`(`id`)  ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_pp_produit` FOREIGN KEY (`id_produit`)
        REFERENCES `PRODUIT`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_pp_quantite`      CHECK (`quantite` > 0),
    CONSTRAINT `chk_pp_prix_unitaire` CHECK (`prix_unitaire` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 10. COMMANDE_PRODUIT ────────────────────────────────────
CREATE TABLE IF NOT EXISTS `COMMANDE_PRODUIT` (
    `id`            BIGINT        NOT NULL AUTO_INCREMENT,
    `id_commande`   BIGINT        NOT NULL,
    `id_produit`    BIGINT        NOT NULL,
    `quantite`      INT           NOT NULL DEFAULT 1,
    `prix_unitaire` DECIMAL(10,2) NOT NULL,
    `details`       JSON          NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_cp_commande` (`id_commande`),
    INDEX `idx_cp_produit`  (`id_produit`),
    CONSTRAINT `fk_cp_commande` FOREIGN KEY (`id_commande`)
        REFERENCES `COMMANDE`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_cp_produit`  FOREIGN KEY (`id_produit`)
        REFERENCES `PRODUIT`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_cp_quantite`      CHECK (`quantite` > 0),
    CONSTRAINT `chk_cp_prix_unitaire` CHECK (`prix_unitaire` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- DONNÉES INITIALES (seed)
-- ============================================================

-- Catégories (9)
INSERT IGNORE INTO `CATEGORIE` (`id`, `nom`, `image`) VALUES
    (1, 'Menus',    '/images/categories/menus.png'),
    (2, 'Burgers',  '/images/categories/burgers.png'),
    (3, 'Wraps',    '/images/categories/wraps.png'),
    (4, 'Frites',   '/images/categories/frites.png'),
    (5, 'Boissons', '/images/categories/boissons.png'),
    (6, 'Encas',    '/images/categories/encas.png'),
    (7, 'Desserts', '/images/categories/desserts.png'),
    (8, 'Salades',  '/images/categories/salades.png'),
    (9, 'Sauces',   '/images/categories/sauces.png');

-- Sauces (7)
INSERT IGNORE INTO `SAUCE` (`id`, `nom`, `image`) VALUES
    (1, 'Classic Barbecue',  '/images/sauces/classic-barbecue.png'),
    (2, 'Classic Moutarde',  '/images/sauces/classic-moutarde.png'),
    (3, 'Creamy Deluxe',     '/images/sauces/cremy-deluxe.png'),
    (4, 'Ketchup',           '/images/sauces/ketchup.png'),
    (5, 'Chinoise',          '/images/sauces/sauce-chinoise.png'),
    (6, 'Curry',             '/images/sauces/sauce-curry.png'),
    (7, 'Pommes Frites',     '/images/sauces/sauce-pommes-frite.png');

-- Tailles de boissons
INSERT IGNORE INTO `TAILLE_BOISSON` (`nom`, `volume`, `supplement_prix`) VALUES
    ('30cl', 30, 0.00),
    ('50cl', 50, 0.50);

-- Admins (mot de passe : "admin123" hashé bcrypt)
-- Rôle 'administration' : accès complet back-office
-- Rôle 'preparation'    : gestion préparation commandes
-- Rôle 'accueil'        : saisie et remise des commandes
INSERT IGNORE INTO `ADMIN` (`nom`, `email`, `mot_de_passe`, `role`) VALUES
    ('Administrateur', 'admin@wcdo.fr',       '$2y$12$7.f4IDM4CmfNmqM02ekx0.pAg41NX/GBDRAtiaX7SDZ1Nbll9vp1m', 'administration'),
    ('Hugo Manager',   'hugo@wcdo.fr',         '$2y$12$7.f4IDM4CmfNmqM02ekx0.pAg41NX/GBDRAtiaX7SDZ1Nbll9vp1m', 'administration'),
    ('Chef Cuisine',   'prep@wcdo.fr',          '$2y$12$7.f4IDM4CmfNmqM02ekx0.pAg41NX/GBDRAtiaX7SDZ1Nbll9vp1m', 'preparation'),
    ('Hôtesse Accueil','accueil@wcdo.fr',       '$2y$12$7.f4IDM4CmfNmqM02ekx0.pAg41NX/GBDRAtiaX7SDZ1Nbll9vp1m', 'accueil');

-- Clients (mot de passe : "client123" hashé bcrypt)
INSERT IGNORE INTO `CLIENT` (`prenom`, `nom`, `email`, `mot_de_passe`, `points_fidelite`) VALUES
    ('Jean',   'Dupont',  'jean.dupont@mail.fr',   '$2b$12$ac0mh.AFkpIvJK1BLKsgeu9YfW67H46MYRNbYi2JlBqSq58OzpSBe', 120),
    ('Sophie', 'Martin',  'sophie.martin@mail.fr', '$2b$12$ac0mh.AFkpIvJK1BLKsgeu9YfW67H46MYRNbYi2JlBqSq58OzpSBe', 45);

-- ── Produits (66) ────────────────────────────────────────────

-- Menus (catégorie 1) — 13 produits
INSERT IGNORE INTO `PRODUIT` (`id`, `nom`, `description`, `prix`, `stock`, `id_categorie`, `image`) VALUES
    (1,  'Menu Le 280',                       'Menu complet avec burger Le 280, frites et boisson',                    8.80,  100, 1, '/images/burgers/280.png'),
    (2,  'Menu Big Tasty',                    'Menu complet avec Big Tasty, frites et boisson',                        10.60, 100, 1, '/images/burgers/BIG_TASTY_1_VIANDE.png'),
    (3,  'Menu Big Tasty Bacon',              'Menu complet avec Big Tasty Bacon, frites et boisson',                  10.90, 100, 1, '/images/burgers/BIG_TASTY_BACON_1_VIANDE.png'),
    (4,  'Menu Big Mac',                      'Menu complet avec Big Mac, frites et boisson',                          8.00,  100, 1, '/images/burgers/BIGMAC.png'),
    (5,  'Menu CBO',                          'Menu complet avec CBO, frites et boisson',                             10.90, 100, 1, '/images/burgers/CBO.png'),
    (6,  'Menu MC Chicken',                   'Menu complet avec MC Chicken, frites et boisson',                       9.30,  100, 1, '/images/burgers/MCCHICKEN.png'),
    (7,  'Menu MC Crispy',                    'Menu complet avec MC Crispy, frites et boisson',                        7.20,  100, 1, '/images/burgers/MCCRISPY.png'),
    (8,  'Menu MC Fish',                      'Menu complet avec MC Fish, frites et boisson',                          7.20,  100, 1, '/images/burgers/MCFISH.png'),
    (9,  'Menu Royal Bacon',                  'Menu complet avec Royal Bacon, frites et boisson',                      7.05,  100, 1, '/images/burgers/ROYALBACON.png'),
    (10, 'Menu Royal Cheese',                 'Menu complet avec Royal Cheese, frites et boisson',                     6.40,  100, 1, '/images/burgers/ROYALCHEESE.png'),
    (11, 'Menu Royal Deluxe',                 'Menu complet avec Royal Deluxe, frites et boisson',                     7.40,  100, 1, '/images/burgers/ROYALDELUXE.png'),
    (12, 'Menu Signature BBQ Beef 2 viandes', 'Menu complet avec Signature BBQ Beef double viande, frites et boisson', 13.50, 100, 1, '/images/burgers/SIGNATURE_BBQ_BEEF_(2_VIANDES).png'),
    (13, 'Menu Signature Beef BBQ',           'Menu complet avec Signature Beef BBQ, frites et boisson',               11.90, 100, 1, '/images/burgers/SIGNATURE_BEEF_BBQ_BURGER_(1_VIANDE).png');

-- Burgers (catégorie 2) — 13 produits
INSERT IGNORE INTO `PRODUIT` (`id`, `nom`, `description`, `prix`, `stock`, `id_categorie`, `image`) VALUES
    (14, 'Le 280',                       'Burger Le 280 original',                          6.80,  100, 2, '/images/burgers/280.png'),
    (15, 'Big Tasty',                    'Burger Big Tasty savoureux',                       8.60,  100, 2, '/images/burgers/BIG_TASTY_1_VIANDE.png'),
    (16, 'Big Tasty Bacon',              'Burger Big Tasty avec bacon croustillant',         8.90,  100, 2, '/images/burgers/BIG_TASTY_BACON_1_VIANDE.png'),
    (17, 'Big Mac',                      'Le célèbre Big Mac, double steak',                6.00,  100, 2, '/images/burgers/BIGMAC.png'),
    (18, 'CBO',                          'Chicken Bacon Onion, poulet et bacon',             8.90,  100, 2, '/images/burgers/CBO.png'),
    (19, 'MC Chicken',                   'Burger au poulet pané croustillant',               7.30,  100, 2, '/images/burgers/MCCHICKEN.png'),
    (20, 'MC Crispy',                    'Burger crispy au poulet',                          5.30,  100, 2, '/images/burgers/MCCRISPY.png'),
    (21, 'MC Fish',                      'Burger au filet de poisson pané',                  4.85,  100, 2, '/images/burgers/MCFISH.png'),
    (22, 'Royal Bacon',                  'Burger Royal avec bacon',                          5.10,  100, 2, '/images/burgers/ROYALBACON.png'),
    (23, 'Royal Cheese',                 'Burger Royal au fromage fondant',                  4.40,  100, 2, '/images/burgers/ROYALCHEESE.png'),
    (24, 'Royal Deluxe',                 'Burger Royal Deluxe garni',                        5.40,  100, 2, '/images/burgers/ROYALDELUXE.png'),
    (25, 'Signature BBQ Beef 2 viandes', 'Signature double viande sauce BBQ',                11.40, 100, 2, '/images/burgers/SIGNATURE_BBQ_BEEF_(2_VIANDES).png'),
    (26, 'Signature Beef BBQ',           'Signature Beef sauce BBQ originale',               10.30, 100, 2, '/images/burgers/SIGNATURE_BEEF_BBQ_BURGER_(1_VIANDE).png');

-- Boissons (catégorie 5) — 8 produits
INSERT IGNORE INTO `PRODUIT` (`id`, `nom`, `description`, `prix`, `stock`, `id_categorie`, `image`) VALUES
    (27, 'Coca Cola',         'Coca-Cola classique',                                  1.90, 200, 5, '/images/boissons/coca-cola.png'),
    (28, 'Coca Sans Sucres',  'Coca-Cola zéro sucres',                                1.90, 200, 5, '/images/boissons/coca-sans-sucres.png'),
    (29, 'Eau',               'Eau minérale naturelle',                               1.00, 200, 5, '/images/boissons/eau.png'),
    (30, 'Fanta Orange',      'Fanta goût orange pétillant',                          1.90, 200, 5, '/images/boissons/fanta.png'),
    (31, 'Ice Tea Pêche',     'Thé glacé saveur pêche',                               1.90, 200, 5, '/images/boissons/ice-tea-peche.png'),
    (32, 'Ice Tea Citron',    'Thé vert citron sans sucres',                          1.90, 200, 5, '/images/boissons/the-vert-citron-sans-sucres.png'),
    (33, 'Jus d\'Orange',     'Jus d\'orange pressé',                                 2.10, 200, 5, '/images/boissons/jus-orange.png'),
    (34, 'Jus de Pommes Bio', 'Jus de pommes issu de l\'agriculture biologique',      2.30, 200, 5, '/images/boissons/jus-pomme-bio.png');

-- Frites (catégorie 4) — 5 produits
INSERT IGNORE INTO `PRODUIT` (`id`, `nom`, `description`, `prix`, `stock`, `id_categorie`, `image`) VALUES
    (35, 'Petite Frite',    'Portion de petites frites dorées',        1.45, 200, 4, '/images/frites/PETITE_FRITE.png'),
    (36, 'Moyenne Frite',   'Portion de frites taille moyenne',        2.75, 200, 4, '/images/frites/MOYENNE_FRITE.png'),
    (37, 'Grande Frite',    'Grande portion de frites croustillantes',  3.50, 200, 4, '/images/frites/GRANDE_FRITE.png'),
    (38, 'Potatoes',        'Potatoes croustillantes assaisonnées',    2.15, 200, 4, '/images/frites/POTATOES.png'),
    (39, 'Grande Potatoes', 'Grande portion de potatoes',              3.40, 200, 4, '/images/frites/GRANDE_POTATOES.png');

-- Encas (catégorie 6) — 4 produits
INSERT IGNORE INTO `PRODUIT` (`id`, `nom`, `description`, `prix`, `stock`, `id_categorie`, `image`) VALUES
    (40, 'Cheeseburger', 'Le classique cheeseburger',        2.60,  150, 6, '/images/encas/cheeseburger.png'),
    (41, 'Croc MCdo',    'Croque-monsieur à la française',   3.20,  150, 6, '/images/encas/croc-mc-do.png'),
    (42, 'Nuggets x4',   'Boîte de 4 nuggets croustillants', 4.20,  150, 6, '/images/encas/nuggets_4.png'),
    (43, 'Nuggets x20',  'Boîte de 20 nuggets à partager',   13.00, 100, 6, '/images/encas/nuggets_20.png');

-- Desserts (catégorie 7) — 9 produits
INSERT IGNORE INTO `PRODUIT` (`id`, `nom`, `description`, `prix`, `stock`, `id_categorie`, `image`) VALUES
    (44, 'Brownie',                    'Brownie fondant au chocolat',                2.60, 100, 7, '/images/desserts/brownies.png'),
    (45, 'Cheesecake chocolat M&M\'S', 'Cheesecake aux pépites M&M\'s et chocolat', 3.10, 100, 7, '/images/desserts/cheesecake_choconuts_M&M_s.png'),
    (46, 'Cheesecake Fraise',          'Cheesecake onctueux à la fraise',            3.10, 100, 7, '/images/desserts/cheesecake_fraise.png'),
    (47, 'Cookie',                     'Cookie moelleux aux pépites de chocolat',    3.20, 100, 7, '/images/desserts/cookie.png'),
    (48, 'Donut',                      'Donut glacé et gourmand',                    2.60, 100, 7, '/images/desserts/doghnut.png'),
    (49, 'Macarons',                   'Assortiment de macarons colorés',            2.70, 100, 7, '/images/desserts/macarons.png'),
    (50, 'MC Fleury',                  'Glace MC Fleury onctueuse',                  4.40, 100, 7, '/images/desserts/MCFleury.png'),
    (51, 'Muffin',                     'Muffin moelleux au chocolat',                3.60, 100, 7, '/images/desserts/muffin.png'),
    (52, 'Sunday',                     'Coupe glacée Sunday classique',              1.00, 100, 7, '/images/desserts/sunday.png');

-- Sauces (catégorie 9) — 7 produits
INSERT IGNORE INTO `PRODUIT` (`id`, `nom`, `description`, `prix`, `stock`, `id_categorie`, `image`) VALUES
    (53, 'Classic Barbecue', 'Sauce barbecue classique',        0.70, 300, 9, '/images/sauces/classic-barbecue.png'),
    (54, 'Classic Moutarde', 'Sauce moutarde à l\'ancienne',    0.70, 300, 9, '/images/sauces/classic-moutarde.png'),
    (55, 'Creamy Deluxe',    'Sauce crémeuse deluxe',           0.70, 300, 9, '/images/sauces/cremy-deluxe.png'),
    (56, 'Ketchup',          'Sauce ketchup traditionnelle',    0.70, 300, 9, '/images/sauces/ketchup.png'),
    (57, 'Chinoise',         'Sauce aigre-douce chinoise',      0.70, 300, 9, '/images/sauces/sauce-chinoise.png'),
    (58, 'Curry',            'Sauce curry épicée',              0.70, 300, 9, '/images/sauces/sauce-curry.png'),
    (59, 'Pommes Frites',    'Sauce spéciale pommes frites',    0.70, 300, 9, '/images/sauces/sauce-pommes-frite.png');

-- Salades (catégorie 8) — 3 produits
INSERT IGNORE INTO `PRODUIT` (`id`, `nom`, `description`, `prix`, `stock`, `id_categorie`, `image`) VALUES
    (60, 'Petite Salade',   'Petite salade verte fraîche',       3.30, 100, 8, '/images/salades/PETITE-SALADE.png'),
    (61, 'Cesar Classic',   'Salade César classique au poulet',  8.80, 100, 8, '/images/salades/SALADE_CLASSIC_CAESAR.png'),
    (62, 'Italienne Mozza', 'Salade italienne à la mozzarella',  8.80, 100, 8, '/images/salades/SALADE_ITALIAN_MOZZA.png');

-- Wraps (catégorie 3) — 4 produits
INSERT IGNORE INTO `PRODUIT` (`id`, `nom`, `description`, `prix`, `stock`, `id_categorie`, `image`) VALUES
    (63, 'MC Wrap Chevre',       'Wrap au chèvre fondant',          3.10, 100, 3, '/images/wraps/mcwrap-chevre.png'),
    (64, 'MC Wrap Poulet Bacon', 'Wrap poulet bacon croustillant',  3.30, 100, 3, '/images/wraps/MCWRAP-POULET-BACON.png'),
    (65, 'Ptit Wrap Chevre',     'Petit wrap au chèvre',            2.60, 100, 3, '/images/wraps/PTIT_WRAP_CHEVRE.png'),
    (66, 'Ptit Wrap Ranch',      'Petit wrap sauce ranch',          2.60, 100, 3, '/images/wraps/PTIT_WRAP_RANCH.png');

-- Menus composés (seed)
INSERT IGNORE INTO `MENU` (`id`, `nom`, `description`, `prix`, `image`, `disponible`) VALUES
    (1, 'Menu Big Mac',   'Big Mac + Frites Moyennes + Boisson au choix',   8.00,  '/images/burgers/BIGMAC.png',            1),
    (2, 'Menu Big Tasty', 'Big Tasty + Frites Moyennes + Boisson au choix', 10.60, '/images/burgers/BIG_TASTY_1_VIANDE.png', 1),
    (3, 'Menu MC Crispy', 'MC Crispy + Frites Moyennes + Boisson au choix', 7.20,  '/images/burgers/MCCRISPY.png',           1),
    (4, 'Menu Nuggets',   'Nuggets x4 + Frites Moyennes + Boisson au choix',7.50,  '/images/encas/nuggets_4.png',            1),
    (5, 'Menu CBO',       'CBO + Frites Moyennes + Boisson au choix',       10.90, '/images/burgers/CBO.png',                1);

INSERT IGNORE INTO `MENU_PRODUIT` (`id_menu`, `id_produit`, `quantite`) VALUES
    (1, 17, 1), (1, 36, 1), (1, 27, 1),
    (2, 15, 1), (2, 36, 1), (2, 27, 1),
    (3, 20, 1), (3, 36, 1), (3, 27, 1),
    (4, 42, 1), (4, 36, 1), (4, 27, 1),
    (5, 18, 1), (5, 36, 1), (5, 27, 1);

-- ============================================================
-- FIN DU SCRIPT
-- ============================================================
