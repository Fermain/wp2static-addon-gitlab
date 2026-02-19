<?php
/**
 * Plugin Name: WP2Static Add-on: GitLab Private Deployment
 * Plugin URI: https://wp2static.com
 * Description: Deploy your static site to a private GitLab repository.
 * Version: 1.7.2
 * Author: Fermain
 * Text Domain: wp2static-addon-gitlab-private
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WP2STATIC_GITLAB_PRIVATE_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP2STATIC_GITLAB_PRIVATE_VERSION', '1.6.1' );

// Simple autoloader
spl_autoload_register( function ( $class ) {
    if ( strpos( $class, 'WP2StaticGitLabPrivate\\' ) === 0 ) {
        $class_file = str_replace( 'WP2StaticGitLabPrivate\\', '', $class );
        $file = WP2STATIC_GITLAB_PRIVATE_PATH . 'src/' . $class_file . '.php';
        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }
} );

// Initialize plugin
add_action( 'plugins_loaded', function() {
    // Register with WP2Static if available
    if ( class_exists( 'WP2Static\Addons' ) ) {
        \WP2Static\Addons::registerAddon(
            'wp2static-gitlab-private',
            'deploy',
            'GitLab Private',
            'https://wp2static.com',
            'Deploy your static site to a private GitLab repository'
        );
    }

    // Initialize the deployer
    if ( class_exists( 'WP2StaticGitLabPrivate\GitLabPrivateDeployer' ) ) {
        new WP2StaticGitLabPrivate\GitLabPrivateDeployer();
    }
}, 20 );

// Activation hook - keep it simple
register_activation_hook( __FILE__, function() {
    // Just set default options
    add_option( 'wp2static_gitlab_private_url', 'https://gitlab.com' );
    add_option( 'wp2static_gitlab_private_project_id', '' );
    add_option( 'wp2static_gitlab_private_access_token', '' );
    add_option( 'wp2static_gitlab_private_branch', 'main' );
    add_option( 'wp2static_gitlab_private_deploy_subdir', 'public' );
    add_option( 'wp2static_gitlab_private_commit_message', 'Deploy static site from WP2Static' );
    add_option( 'wp2static_gitlab_private_author_name', 'WP2Static' );
    add_option( 'wp2static_gitlab_private_author_email', 'noreply@wp2static.com' );
    add_option( 'wp2static_gitlab_private_delete_orphaned_files', false );
    add_option( 'wp2static_gitlab_private_verbose_logging', false );
    add_option( 'wp2static_gitlab_private_target_branch', 'master' );
} );

// Deactivation hook
register_deactivation_hook( __FILE__, function() {
    // Keep options for easy reactivation
} ); 