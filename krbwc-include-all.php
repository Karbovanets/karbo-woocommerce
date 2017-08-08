<?php
/*
Karbo for WooCommerce
https://github.com/Karbovanets/karbo-woocommerce/
*/

//---------------------------------------------------------------------------
// Global definitions
if (!defined('KRBWC_PLUGIN_NAME'))
  {
  define('KRBWC_VERSION',           '0.01');

  //-----------------------------------------------
  define('KRBWC_EDITION',           'Standard');    

  //-----------------------------------------------
  define('KRBWC_SETTINGS_NAME',     'KRBWC-Settings');
  define('KRBWC_PLUGIN_NAME',       'Karbo for WooCommerce');   


  // i18n plugin domain for language files
  define('KRBWC_I18N_DOMAIN',       'krbwc');

  }
//---------------------------------------------------------------------------

//------------------------------------------
// Load wordpress for POSTback, WebHook and API pages that are called by external services directly.
if (defined('KRBWC_MUST_LOAD_WP') && !defined('WP_USE_THEMES') && !defined('ABSPATH'))
   {
   $g_blog_dir = preg_replace ('|(/+[^/]+){4}$|', '', str_replace ('\\', '/', __FILE__)); // For love of the art of regex-ing
   define('WP_USE_THEMES', false);
   require_once ($g_blog_dir . '/wp-blog-header.php');

   // Force-elimination of header 404 for non-wordpress pages.
   header ("HTTP/1.1 200 OK");
   header ("Status: 200 OK");

   require_once ($g_blog_dir . '/wp-admin/includes/admin.php');
   }
//------------------------------------------


// This loads necessary modules
require_once (dirname(__FILE__) . '/libs/forknoteWalletdAPI.php');

require_once (dirname(__FILE__) . '/krbwc-cron.php');
require_once (dirname(__FILE__) . '/krbwc-utils.php');
require_once (dirname(__FILE__) . '/krbwc-admin.php');
require_once (dirname(__FILE__) . '/krbwc-render-settings.php');
require_once (dirname(__FILE__) . '/krbwc-karbo-gateway.php');

?>