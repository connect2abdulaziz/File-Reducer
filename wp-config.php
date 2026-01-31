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
define( 'DB_NAME', 'cad_reducer' );

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
define( 'AUTH_KEY',         'N<4s)Y(4>(G(f1Q/Yp&URWMPt#:tn2ZT08Mn=b?]y6I<]];Kk`9=FYKA?%IpXt]i' );
define( 'SECURE_AUTH_KEY',  '1Y,:^uUV8u6jrv|JI`>9(XKE.3bxq:dk:r`0kV4t1(+,a44 06j]IWp3}S1?fS1g' );
define( 'LOGGED_IN_KEY',    ',+3,h)*6$ K#o{5brGk>@hCj+*qXk4xW#|1;Z5F(I@SO(b,$<&Jmu.*`%A&8@q<Y' );
define( 'NONCE_KEY',        'hu|Tw~hlzpnJK`;_XIpsWZi^e@H0O/[C`I5r]noyN<#qY7D:(sQmg]U?Snbxn#;s' );
define( 'AUTH_SALT',        'j_f(4Rg,ZCgR-c2#3:>MA^Vhz,GK!d~yFgYt~vw6Nn{~:+|/F,Ie:/C-!J~JCd:b' );
define( 'SECURE_AUTH_SALT', 'cH*XY&V)wcu0b.}v-=#A4<ZL`3kN.ixCHu>eB~cBrP0,co<[]W&j<4D#O.F0sB%W' );
define( 'LOGGED_IN_SALT',   '4glpm)V_:L0]pjWeFjaV.8gF]08yh<_&cntJc^89*=Nwbd~M>FNNx=Uu8s7jQoQ_' );
define( 'NONCE_SALT',       'D$zwVn}Z{/5`<% owFmRHrB?Yi3LXlSU4o7Hb.q[{En;l2)u?yI9@X&-4To~Irl=' );

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



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
