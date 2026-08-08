<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'portfolio' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         '~P-y{$wrA11GesHX[Ik!Db/t1pWi,g L!Mb%c_|/M zS^r8F/28Ak=4Nj8vw(!5:' );
define( 'SECURE_AUTH_KEY',  ']?SRi:Pa8;e0mC,R-0{rrJLk=.{0M?v%i#73Jv/qyjzBH.K8bXq<:7S>;{=$.Hb{' );
define( 'LOGGED_IN_KEY',    'by??m},jxmVJNeXgI.*v}vIK=9@?)>fB]y/B9E@I%s0S<Z_~yz#t]q]a]mlm`uth' );
define( 'NONCE_KEY',        'cWa/qJq!:lS>>$scIZN3=t{Ka934)?aH&z2^?5k~bqESR,eUF! .Xx!QT3*WU`35' );
define( 'AUTH_SALT',        'ujx9rR8v:Ss7/v8.>#`%z{b:?}=f^pEx:T#d!/rp_`(gio|][6Yw7CD~15gD;hSc' );
define( 'SECURE_AUTH_SALT', '435h qpswYczQt@}TU,,eCbYqTn!xOw|ipc(<&eV~<G)d-#IX yO+{8~uDv|&zD%' );
define( 'LOGGED_IN_SALT',   '7&.IGwK75BLm; 8/1yy?DBPjY_bfd:y;#tN+B[XE!lQyDV{#u,Iulgr>-G=FYz]w' );
define( 'NONCE_SALT',       '+2_n,7:MOq4-ZKA`MFoUehkYzn$<y?pFRwgdi]S[=]*tVwNX!|;f+[179uP>J#72' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */

define('WP_HOME', 'http://localhost/portfolio');
define('WP_SITEURL', 'http://localhost/portfolio');

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
