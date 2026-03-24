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
    `id`  BIGINT       NOT NULL AUTO_INCREMENT,
    `nom` VARCHAR(100) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_categorie_nom` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. SAUCE ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `SAUCE` (
    `id`  BIGINT       NOT NULL AUTO_INCREMENT,
    `nom` VARCHAR(100) NOT NULL,
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
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_admin_email` (`email`)
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
    `date_creation` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_produit_categorie` (`id_categorie`),
    INDEX `idx_produit_stock`     (`stock`),
    INDEX `idx_produit_nom`       (`nom`),
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
    `date_creation`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `client_id`        BIGINT        NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_commande_numero`   (`numero_commande`),
    INDEX `idx_commande_date`            (`date_creation`),
    INDEX `idx_commande_client`          (`client_id`),
    INDEX `idx_commande_chevalet`        (`numero_chevalet`),
    CONSTRAINT `fk_commande_client` FOREIGN KEY (`client_id`)
        REFERENCES `CLIENT`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_commande_chevalet` CHECK (`numero_chevalet` BETWEEN 1 AND 999),
    CONSTRAINT `chk_commande_montant`  CHECK (`montant_total` > 0)
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

-- Catégories
INSERT IGNORE INTO `CATEGORIE` (`nom`) VALUES
    ('Menu'),
    ('Sandwiches'),
    ('Wraps'),
    ('Frites'),
    ('Boissons Froides'),
    ('Encas'),
    ('Desserts');

-- Sauces
INSERT IGNORE INTO `SAUCE` (`nom`) VALUES
    ('Barbecue'),
    ('Moutarde'),
    ('Cremy-Deluxe'),
    ('Ketchup'),
    ('Chinoise'),
    ('Curry'),
    ('Pomme-Frite');

-- Tailles de boissons
INSERT IGNORE INTO `TAILLE_BOISSON` (`nom`, `volume`, `supplement_prix`) VALUES
    ('30cl', 30, 0.00),
    ('50cl', 50, 0.50);

-- Admins (mot de passe : "admin123" hashé bcrypt)
INSERT IGNORE INTO `ADMIN` (`nom`, `email`, `mot_de_passe`) VALUES
    ('Administrateur', 'admin@wcdo.fr',  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
    ('Hugo Manager',   'hugo@wcdo.fr',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Clients (mot de passe : "client123" hashé bcrypt)
INSERT IGNORE INTO `CLIENT` (`prenom`, `nom`, `email`, `mot_de_passe`, `points_fidelite`) VALUES
    ('Jean',   'Dupont',  'jean.dupont@mail.fr',   '$2b$12$ac0mh.AFkpIvJK1BLKsgeu9YfW67H46MYRNbYi2JlBqSq58OzpSBe', 120),
    ('Sophie', 'Martin',  'sophie.martin@mail.fr', '$2b$12$ac0mh.AFkpIvJK1BLKsgeu9YfW67H46MYRNbYi2JlBqSq58OzpSBe', 45);

-- ── Produits ─────────────────────────────────────────────────
-- Sandwiches (id_categorie = 2)
INSERT IGNORE INTO `PRODUIT` (`nom`, `description`, `prix`, `stock`, `id_categorie`, `image`) VALUES
    ('Big Mac',            '2 steaks, sauce Big Mac, salade, cornichons, oignons, cheddar', 5.40, 100, 2, '/Front/images/sandwiches/BIGMAC.png'),
    ('McChicken',          'Escalope de poulet pané, mayonnaise, salade',                   4.20, 80,  2, '/Front/images/sandwiches/MCCHICKEN.png'),
    ('Royal Cheese',       'Steak bœuf, sauce burger, cheddar fondu, oignons caramélisés', 4.90, 80,  2, '/Front/images/sandwiches/ROYALCHEESE.png'),
    ('Double Cheeseburger','2 steaks bœuf, double cheddar, cornichons, moutarde, ketchup',  3.80, 60,  2, '/Front/images/encas/cheeseburger.png'),
    ('Filet-O-Fish',       'Filet de poisson pané, sauce tartare, cheddar',                 4.50, 50,  2, '/Front/images/sandwiches/MCFISH.png'),
    ('Quarter Pounder',    'Steak 113g, cheddar, oignons, cornichons, moutarde, ketchup',   5.90, 70,  2, '/Front/images/sandwiches/ROYALBACON.png');

-- Wraps (id_categorie = 3)
INSERT IGNORE INTO `PRODUIT` (`nom`, `description`, `prix`, `stock`, `id_categorie`, `image`) VALUES
    ('Wrap Crispy Chicken', 'Poulet croustillant, salade, tomates, sauce Caesar',    4.70, 60, 3, '/Front/images/wraps/MCWRAP-POULET-BACON.png'),
    ('Wrap McChicken',      'Escalope de poulet, mayonnaise, salade, tortilla',      4.20, 50, 3, '/Front/images/wraps/mcwrap-chevre.png');

-- Frites (id_categorie = 4)
INSERT IGNORE INTO `PRODUIT` (`nom`, `description`, `prix`, `stock`, `id_categorie`, `image`) VALUES
    ('Petites Frites', 'Frites croustillantes — portion petite',  1.90, 200, 4, '/Front/images/frites/PETITE_FRITE.png'),
    ('Moyennes Frites','Frites croustillantes — portion moyenne', 2.40, 200, 4, '/Front/images/frites/MOYENNE_FRITE.png'),
    ('Grandes Frites', 'Frites croustillantes — portion grande',  3.10, 200, 4, '/Front/images/frites/GRANDE_FRITE.png');

-- Boissons Froides (id_categorie = 5)
INSERT IGNORE INTO `PRODUIT` (`nom`, `description`, `prix`, `stock`, `id_categorie`, `image`) VALUES
    ('Coca-Cola',        'Soda rafraîchissant',              2.20, 300, 5, '/Front/images/boissons/coca-cola.png'),
    ('Sprite',           'Limonade pétillante citronnée',    2.20, 200, 5, '/Front/images/boissons/the-vert-citron-sans-sucres.png'),
    ('Fanta Orange',     'Soda à l\'orange',                 2.20, 200, 5, '/Front/images/boissons/fanta.png'),
    ('Eau Minérale',     'Eau plate 50cl',                   1.50, 150, 5, '/Front/images/boissons/eau.png'),
    ('Milkshake Vanille','Milkshake crémeux à la vanille',   3.50, 80,  5, '/Front/images/boissons/jus-pomme-bio.png');

-- Encas (id_categorie = 6)
INSERT IGNORE INTO `PRODUIT` (`nom`, `description`, `prix`, `stock`, `id_categorie`, `image`) VALUES
    ('McNuggets x6',  '6 nuggets de poulet croustillants',  4.20, 150, 6, '/Front/images/encas/nuggets_4.png'),
    ('McNuggets x9',  '9 nuggets de poulet croustillants',  5.80, 100, 6, '/Front/images/encas/nuggets_4.png'),
    ('McNuggets x20', '20 nuggets de poulet croustillants', 9.90, 50,  6, '/Front/images/encas/nuggets_20.png'),
    ('McBaguette',    'Baguette, jambon, emmental, salade', 3.90, 80,  6, '/Front/images/encas/croc-mc-do.png');

-- Desserts (id_categorie = 7)
INSERT IGNORE INTO `PRODUIT` (`nom`, `description`, `prix`, `stock`, `id_categorie`, `image`) VALUES
    ('McFlurry Oreo',    'Glace vanille avec éclats de cookies Oreo',  3.20, 60, 7, '/Front/images/desserts/MCFleury.png'),
    ('McFlurry Caramel', 'Glace vanille avec coulis de caramel',        3.20, 60, 7, '/Front/images/desserts/MCFleury.png'),
    ('Apple Pie',        'Chausson aux pommes chaud et croustillant',   1.80, 80, 7, '/Front/images/desserts/cookie.png'),
    ('Sundae Caramel',   'Coupe glacée vanille nappée de caramel',      2.50, 70, 7, '/Front/images/desserts/sunday.png');

-- Menus (id_categorie = 1)
INSERT IGNORE INTO `PRODUIT` (`nom`, `description`, `prix`, `stock`, `id_categorie`, `image`) VALUES
    ('Menu Big Mac',     'Big Mac + Moyennes Frites + Boisson au choix',      9.20, 100, 1, '/Front/images/sandwiches/BIGMAC.png'),
    ('Menu McChicken',   'McChicken + Moyennes Frites + Boisson au choix',    8.50, 100, 1, '/Front/images/sandwiches/MCCHICKEN.png'),
    ('Menu Royal Cheese','Royal Cheese + Moyennes Frites + Boisson au choix', 8.90, 80,  1, '/Front/images/sandwiches/ROYALCHEESE.png'),
    ('Menu McNuggets 9', '9 McNuggets + Moyennes Frites + Boisson au choix',  9.50, 80,  1, '/Front/images/encas/nuggets_4.png');

-- ============================================================
-- FIN DU SCRIPT
-- ============================================================
