<?php
/**
 * Uninstall script for WP2Static GitLab Private Deployment Add-on
 * 
 * This file is executed when the plugin is deleted via the WordPress admin.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Remove from wp2static addons registry
$addons_table = $wpdb->prefix . 'wp2static_addons';
$wpdb->delete(
    $addons_table,
    [ 'slug' => 'wp2static-gitlab-private' ]
);

// Delete stored options
$options = [
    'wp2static_gitlab_private_url',
    'wp2static_gitlab_private_project_id',
    'wp2static_gitlab_private_access_token',
    'wp2static_gitlab_private_branch',
    'wp2static_gitlab_private_deploy_subdir',
    'wp2static_gitlab_private_commit_message',
    'wp2static_gitlab_private_author_name',
    'wp2static_gitlab_private_author_email',
    'wp2static_gitlab_private_delete_orphaned_files',
    'wp2static_gitlab_private_verbose_logging',
];
foreach ( $options as $opt ) {
    delete_option( $opt );
}

// Remove temporary working directories under uploads
$upload_dir = wp_upload_dir();
$work_root = trailingslashit( $upload_dir['basedir'] ) . 'wp2static-gitlab-private/tmp';
if ( is_dir( $work_root ) ) {
    $items = scandir( $work_root );
    if ( is_array( $items ) ) {
        foreach ( $items as $item ) {
            if ( $item === '.' || $item === '..' ) { continue; }
            $path = $work_root . DIRECTORY_SEPARATOR . $item;
            if ( is_dir( $path ) ) {
                // Recursively delete directory
                $rii = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
                foreach ( $rii as $f ) {
                    if ( $f->isDir() ) { @rmdir( $f->getPathname() ); } else { @unlink( $f->getPathname() ); }
                }
                @rmdir( $path );
            } else {
                @unlink( $path );
            }
        }
    }
    @rmdir( $work_root );
}