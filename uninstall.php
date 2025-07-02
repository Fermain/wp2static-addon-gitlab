<?php
/**
 * Uninstall script for WP2Static GitLab Private Deployment Add-on
 * 
 * This file is executed when the plugin is deleted via the WordPress admin.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Remove plugin options table
global $wpdb;

$table_name = $wpdb->prefix . 'wp2static_gitlab_private_options';
$wpdb->query( "DROP TABLE IF EXISTS $table_name" );

// Remove from wp2static addons registry
$addons_table = $wpdb->prefix . 'wp2static_addons';
$wpdb->delete(
    $addons_table,
    [ 'slug' => 'wp2static-gitlab-private' ]
); 