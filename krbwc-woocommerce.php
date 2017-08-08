<?php
/*

Plugin Name: Karbo for WooCommerce
Plugin URI: https://github.com/Karbovanets/karbo-woocommerce/
Description: Karbo for WooCommerce plugin allows you to accept payments in Karbos for physical and digital products at your WooCommerce-powered online store.
Version: 0.01
Author: KittyCatTech
Author URI: https://github.com/Karbovanets/karbo-woocommerce/
License: BipCot NoGov Software License bipcot.org

*/


// Include everything
include (dirname(__FILE__) . '/krbwc-include-all.php');

//---------------------------------------------------------------------------
// Add hooks and filters

// create custom plugin settings menu
add_action( 'admin_menu',                   'KRBWC_create_menu' );

register_activation_hook(__FILE__,          'KRBWC_activate');
register_deactivation_hook(__FILE__,        'KRBWC_deactivate');
register_uninstall_hook(__FILE__,           'KRBWC_uninstall');

add_filter ('cron_schedules',               'KRBWC__add_custom_scheduled_intervals');
add_action ('KRBWC_cron_action',             'KRBWC_cron_job_worker');     // Multiple functions can be attached to 'KRBWC_cron_action' action

KRBWC_set_lang_file();
//---------------------------------------------------------------------------

//===========================================================================
// activating the default values
function KRBWC_activate()
{
    global  $g_KRBWC__config_defaults;

    $krbwc_default_options = $g_KRBWC__config_defaults;

    // This will overwrite default options with already existing options but leave new options (in case of upgrading to new version) untouched.
    $krbwc_settings = KRBWC__get_settings ();

    foreach ($krbwc_settings as $key=>$value)
    	$krbwc_default_options[$key] = $value;

    update_option (KRBWC_SETTINGS_NAME, $krbwc_default_options);

    // Re-get new settings.
    $krbwc_settings = KRBWC__get_settings ();

    // Create necessary database tables if not already exists...
    KRBWC__create_database_tables ($krbwc_settings);
    KRBWC__SubIns ();

    //----------------------------------
    // Setup cron jobs

    if ($krbwc_settings['enable_soft_cron_job'] && !wp_next_scheduled('KRBWC_cron_action'))
    {
    	$cron_job_schedule_name = $krbwc_settings['soft_cron_job_schedule_name'];
    	wp_schedule_event(time(), $cron_job_schedule_name, 'KRBWC_cron_action');
    }
    //----------------------------------

}
//---------------------------------------------------------------------------
// Cron Subfunctions
function KRBWC__add_custom_scheduled_intervals ($schedules)
{
	$schedules['seconds_30']     = array('interval'=>30,     'display'=>__('Once every 30 seconds'));
	$schedules['minutes_1']      = array('interval'=>1*60,   'display'=>__('Once every 1 minute'));
	$schedules['minutes_2.5']    = array('interval'=>2.5*60, 'display'=>__('Once every 2.5 minutes'));
	$schedules['minutes_5']      = array('interval'=>5*60,   'display'=>__('Once every 5 minutes'));

	return $schedules;
}
//---------------------------------------------------------------------------
//===========================================================================

//===========================================================================
// deactivating
function KRBWC_deactivate ()
{
    // Do deactivation cleanup. Do not delete previous settings in case user will reactivate plugin again...

    //----------------------------------
    // Clear cron jobs
    wp_clear_scheduled_hook ('KRBWC_cron_action');
    //----------------------------------
}
//===========================================================================

//===========================================================================
// uninstalling
function KRBWC_uninstall ()
{
    $krbwc_settings = KRBWC__get_settings();

    if ($krbwc_settings['delete_db_tables_on_uninstall'])
    {
        // delete all settings.
        delete_option(KRBWC_SETTINGS_NAME);

        // delete all DB tables and data.
        KRBWC__delete_database_tables ();
    }
}
//===========================================================================

//===========================================================================
function KRBWC_create_menu()
{

    // create new top-level menu
    // http://www.fileformat.info/info/unicode/char/e3f/index.htm
    add_menu_page (
        __('Woo Karbo', KRBWC_I18N_DOMAIN),                    // Page title
        __('Karbo', KRBWC_I18N_DOMAIN),                        // Menu Title - lower corner of admin menu
        'administrator',                                        // Capability
        'krbwc-settings',                                        // Handle - First submenu's handle must be equal to parent's handle to avoid duplicate menu entry.
        'KRBWC__render_general_settings_page',                   // Function
        plugins_url('/images/karbo_16x.png', __FILE__)      // Icon URL
        );

    add_submenu_page (
        'krbwc-settings',                                        // Parent
        __("WooCommerce Karbo Gateway", KRBWC_I18N_DOMAIN),                   // Page title
        __("General Settings", KRBWC_I18N_DOMAIN),               // Menu Title
        'administrator',                                        // Capability
        'krbwc-settings',                                        // Handle - First submenu's handle must be equal to parent's handle to avoid duplicate menu entry.
        'KRBWC__render_general_settings_page'                    // Function
        );

}
//===========================================================================

//===========================================================================
// load language files
function KRBWC_set_lang_file()
{
    # set the language file
    $currentLocale = get_locale();
    if(!empty($currentLocale))
    {
        $moFile = dirname(__FILE__) . "/lang/" . $currentLocale . ".mo";
        if (@file_exists($moFile) && is_readable($moFile))
        {
            load_textdomain(KRBWC_I18N_DOMAIN, $moFile);
        }

    }
}
//===========================================================================

