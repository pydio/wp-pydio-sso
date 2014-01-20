<?php
/**
 * @author		Charles du Jeu <charles@ajaxplorer.info>
 * @copyright	Copyright (c) 2014, Charles du Jeu
 * @license		https://www.gnu.org/licenses/lgpl-2.1.html LGPLv2.1
 * @package		Pydio\WP_Pydio_Bridge\Uninstall
 * 
 * @since		2.0.0
 */

if ( !defined( 'ABSPATH' ) && !defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}
// @todo DocBlock
if ( is_multisite() ) {
	
    global $wpdb;
    $blogs = $wpdb->get_results( "SELECT blog_id FROM {$wpdb->blogs}", ARRAY_A );
	
    if ( $blogs ) {
        foreach( $blogs as $blog ) {
            switch_to_blog( $blog['blog_id'] );
            delete_option( 'pydio_settings' );
        }
        restore_current_blog();
    }
	
} else {
	
    delete_option( 'pydio_settings' );
	
}
