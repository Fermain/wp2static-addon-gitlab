<?php

namespace WP2StaticGitLabPrivate;

use WP2Static\WsLog;
use WP2Static\DeployCache;

class GitLabPrivateDeployer {

    public function __construct() {
        add_action( 'wp2static_deploy', [ $this, 'deploy' ], 10, 2 );
        add_action( 'admin_menu', [ $this, 'addOptionsPage' ] );
        add_action( 'admin_post_wp2static_gitlab_private_save_options', [ $this, 'saveOptions' ] );
        add_action( 'admin_post_wp2static_gitlab_private_test_connection', [ $this, 'testConnection' ] );
    }

    private function verboseLog( string $message ) : void {
        if ( get_option( 'wp2static_gitlab_private_verbose_logging', false ) ) {
            WsLog::l( $message );
        }
    }

    public function addOptionsPage() : void {
        add_submenu_page(
            'wp2static',
            'WP2Static GitLab Private',
            'GitLab Private',
            'manage_options',
            'wp2static-gitlab-private',
            [ $this, 'renderOptionsPage' ]
        );
    }

    public function renderOptionsPage() : void {
        // Get options using WordPress built-in functions
        $options = [
            'gitlabUrl' => get_option( 'wp2static_gitlab_private_url', 'https://gitlab.com' ),
            'gitlabProjectId' => get_option( 'wp2static_gitlab_private_project_id', '' ),
            'gitlabAccessToken' => get_option( 'wp2static_gitlab_private_access_token', '' ),
            'gitlabBranch' => get_option( 'wp2static_gitlab_private_branch', 'main' ),
            'gitlabCommitMessage' => get_option( 'wp2static_gitlab_private_commit_message', 'Deploy static site from WP2Static' ),
            'gitlabAuthorName' => get_option( 'wp2static_gitlab_private_author_name', 'WP2Static' ),
            'gitlabAuthorEmail' => get_option( 'wp2static_gitlab_private_author_email', 'noreply@wp2static.com' ),
            'gitlabDeleteOrphanedFiles' => get_option( 'wp2static_gitlab_private_delete_orphaned_files', false ),
            'gitlabVerboseLogging' => get_option( 'wp2static_gitlab_private_verbose_logging', false ),
        ];
        
        include WP2STATIC_GITLAB_PRIVATE_PATH . 'views/options-page.php';
    }

    public function saveOptions() : void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have sufficient permissions to access this page.' );
        }

        check_admin_referer( 'wp2static-gitlab-private-options' );

        // Save options using WordPress built-in functions
        update_option( 'wp2static_gitlab_private_url', esc_url_raw( rtrim( sanitize_text_field( $_POST['gitlabUrl'] ?? '' ), '/' ) ) );
        update_option( 'wp2static_gitlab_private_project_id', sanitize_text_field( $_POST['gitlabProjectId'] ?? '' ) );
        update_option( 'wp2static_gitlab_private_access_token', sanitize_text_field( $_POST['gitlabAccessToken'] ?? '' ) );
        update_option( 'wp2static_gitlab_private_branch', sanitize_text_field( $_POST['gitlabBranch'] ?? '' ) );
        update_option( 'wp2static_gitlab_private_commit_message', sanitize_text_field( $_POST['gitlabCommitMessage'] ?? '' ) );
        update_option( 'wp2static_gitlab_private_author_name', sanitize_text_field( $_POST['gitlabAuthorName'] ?? '' ) );
        update_option( 'wp2static_gitlab_private_author_email', sanitize_email( $_POST['gitlabAuthorEmail'] ?? '' ) );
        update_option( 'wp2static_gitlab_private_delete_orphaned_files', isset( $_POST['gitlabDeleteOrphanedFiles'] ) ? true : false );
        update_option( 'wp2static_gitlab_private_verbose_logging', isset( $_POST['gitlabVerboseLogging'] ) ? true : false );
        
        wp_redirect( 
            add_query_arg( 
                [ 
                    'page' => 'wp2static-gitlab-private',
                    'settings-updated' => 'true',
                ],
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    public function testConnection() : void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        check_admin_referer( 'wp2static-gitlab-private-test' );

        try {
            $gitlab_url = get_option( 'wp2static_gitlab_private_url' );
            $project_id = get_option( 'wp2static_gitlab_private_project_id' );
            $access_token = get_option( 'wp2static_gitlab_private_access_token' );

            if ( empty( $gitlab_url ) || empty( $project_id ) || empty( $access_token ) ) {
                throw new \Exception( 'Missing required configuration' );
            }

            $api_url = $gitlab_url . '/api/v4/projects/' . urlencode( $project_id );
            
            $connection_timeout = 30;
            
            $args = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                ],
                'timeout' => $connection_timeout,
            ];

            $response = wp_remote_get( $api_url, $args );

            if ( is_wp_error( $response ) ) {
                throw new \Exception( 'Connection failed: ' . $response->get_error_message() );
            }

            $response_code = wp_remote_retrieve_response_code( $response );
            
            if ( $response_code === 200 ) {
                $project_data = json_decode( wp_remote_retrieve_body( $response ), true );
                $permissions = $project_data['permissions'] ?? [];
                $access_level = $permissions['project_access']['access_level'] ?? 0;
                
                $role_names = [
                    10 => 'Guest',
                    20 => 'Reporter', 
                    30 => 'Developer',
                    40 => 'Maintainer',
                    50 => 'Owner'
                ];
                
                $role_name = $role_names[$access_level] ?? "Unknown ($access_level)";
                
                if ( $access_level >= 40 ) {
                    $message = "Connection successful! Project: {$project_data['name']}, Role: $role_name ✓";
                    $type = 'success';
                } else {
                    $message = "Connection OK but insufficient permissions. Project: {$project_data['name']}, Role: $role_name. Need Maintainer (40) or higher for protected branches.";
                    $type = 'warning';
                }
            } else {
                $message = 'Connection failed (HTTP ' . $response_code . ')';
                $type = 'error';
            }

        } catch ( \Exception $e ) {
            $message = 'Test failed: ' . $e->getMessage();
            $type = 'error';
        }

        wp_redirect( 
            add_query_arg( 
                [ 
                    'page' => 'wp2static-gitlab-private',
                    'test_result' => $type,
                    'test_message' => urlencode( $message ),
                ],
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    public function deploy( string $processed_site_path, string $deployer_slug ) : void {
        if ( $deployer_slug !== 'wp2static-gitlab-private' ) {
            return;
        }

        try {
            WsLog::l( 'GitLab Private deployment started' );
            
            $this->validateConfiguration();
            
                    $files = $this->getFilesToDeploy( $processed_site_path );
        WsLog::l( 'Found ' . count( $files ) . ' files to deploy' );

        if ( empty( $files ) ) {
            WsLog::l( 'No files to deploy' );
            return;
        }
        
        // Additional debugging for blank commit issue
        $this->verboseLog( 'Sample files to deploy: ' . wp_json_encode( array_slice( $files, 0, 2 ) ) );

        $this->deployFiles( $files );
        
        // Check for deleted files and remove them from repository
        $this->handleDeletedFiles( $files );
            
            WsLog::l( 'GitLab Private deployment completed' );
        } catch ( \Exception $e ) {
            WsLog::l( 'GitLab Private deployment failed: ' . $e->getMessage() );
            throw $e;
        }
    }

    private function validateConfiguration() : void {
        $required_options = [
            'wp2static_gitlab_private_url' => get_option( 'wp2static_gitlab_private_url' ),
            'wp2static_gitlab_private_project_id' => get_option( 'wp2static_gitlab_private_project_id' ), 
            'wp2static_gitlab_private_access_token' => get_option( 'wp2static_gitlab_private_access_token' ),
            'wp2static_gitlab_private_branch' => get_option( 'wp2static_gitlab_private_branch' )
        ];
        
        foreach ( $required_options as $option => $value ) {
            if ( empty( $value ) ) {
                $error = "GitLab Private: Missing required option: $option (value: '$value')";
                WsLog::l( $error );
                throw new \Exception( $error );
            }
        }
        
        $this->verboseLog( 'GitLab Private configuration validated successfully' );
    }

    private function getAllLocalFiles( string $processed_site_path ) : array {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $processed_site_path,
                \RecursiveDirectoryIterator::SKIP_DOTS
            )
        );

        // Normalize the processed site path
        $normalized_path = rtrim( $processed_site_path, '/' ) . '/';
        
        foreach ( $iterator as $file ) {
            if ( $file->isFile() ) {
                $local_path = $file->getPathname();
                
                // Get relative path more reliably
                if ( strpos( $local_path, $normalized_path ) === 0 ) {
                    $relative_path = substr( $local_path, strlen( $normalized_path ) );
                } else {
                    // Fallback method
                    $relative_path = str_replace( $normalized_path, '', $local_path );
                }
                
                // Skip empty paths
                if ( empty( $relative_path ) ) {
                    WsLog::l( "Skipping file with empty relative path: $local_path" );
                    continue;
                }
                
                $files[] = [
                    'local_path' => $local_path,
                    'remote_path' => $relative_path,
                ];
            }
        }

        return $files;
    }

    private function filterFilesToDeploy( array $all_files ) : array {
        $files_to_deploy = [];

        foreach ( $all_files as $file ) {
            $cache_path = '/' . ltrim( $file['remote_path'], '/' );

            if ( $this->shouldDeployFile( $file['local_path'], $cache_path ) ) {
                $files_to_deploy[] = $file;
            }
        }

        return $files_to_deploy;
    }

    private function shouldDeployFile( string $local_path, string $remote_path ) : bool {
        return ! DeployCache::fileisCached( $remote_path, 'wp2static-gitlab-private' );
    }

    private function deployFiles( array $files ) : void {
        WsLog::l( "Deploying " . count( $files ) . " files in a single commit" );
        
        $success = $this->deployFileBatch( $files );
        
        if ( ! $success ) {
            throw new \Exception( "Failed to deploy files to GitLab. Check logs for details." );
        }
        
        WsLog::l( 'Deployment completed successfully' );
    }

    private function deployFileBatch( array $files ) : bool {
        $gitlab_url = get_option( 'wp2static_gitlab_private_url' );
        $project_id = get_option( 'wp2static_gitlab_private_project_id' );
        $access_token = get_option( 'wp2static_gitlab_private_access_token' );
        $branch = get_option( 'wp2static_gitlab_private_branch' );
        $commit_message = get_option( 'wp2static_gitlab_private_commit_message' );
        $author_name = get_option( 'wp2static_gitlab_private_author_name' );
        $author_email = get_option( 'wp2static_gitlab_private_author_email' );

        $success = $this->attemptMixedCommit( $files, $gitlab_url, $project_id, $access_token, $branch, $commit_message, $author_name, $author_email );
        
        if ( $success ) {
            foreach ( $files as $file ) {
                DeployCache::addFile( $file['remote_path'], 'wp2static-gitlab-private' );
            }
            return true;
        } else {
            $this->verboseLog( "Deployment failed" );
            return false;
        }
    }

    private function attemptMixedCommit( array $files, string $gitlab_url, string $project_id, string $access_token, string $branch, string $commit_message, string $author_name, string $author_email ) : bool {
        // Get current file list from repository to determine which files exist
        $existing_files = $this->getExistingFiles( $gitlab_url, $project_id, $access_token, $branch );
        
        $actions = [];
        
        foreach ( $files as $file ) {
            if ( ! file_exists( $file['local_path'] ) ) {
                continue;
            }
            
            $content = file_get_contents( $file['local_path'] );
            if ( $content === false ) {
                continue;
            }

            $action = in_array( $file['remote_path'], $existing_files ) ? 'update' : 'create';
            
            $actions[] = [
                'action' => $action,
                'file_path' => $file['remote_path'],
                'content' => base64_encode( $content ),
                'encoding' => 'base64',
            ];
        }

        if ( empty( $actions ) ) {
            return true;
        }

        $api_url = $gitlab_url . '/api/v4/projects/' . urlencode( $project_id ) . '/repository/commits';
        
        $payload = [
            'branch' => $branch,
            'commit_message' => $commit_message . ' (mixed actions, ' . count( $actions ) . ' files)',
            'actions' => $actions,
            'author_name' => $author_name,
            'author_email' => $author_email,
        ];

        WsLog::l( "Deploying " . count( $actions ) . " files to GitLab (mixed strategy)" );
        
        $response = $this->makeApiRequest( $api_url, $payload, $access_token );
        
        if ( $response ) {
            WsLog::l( "Successfully deployed " . count( $actions ) . " files to GitLab using mixed strategy" );
            return true;
        }
        
        return false;
    }

    private function getExistingFiles( string $gitlab_url, string $project_id, string $access_token, string $branch ) : array {
        $api_url = $gitlab_url . '/api/v4/projects/' . urlencode( $project_id ) . '/repository/tree';
        
        $connection_timeout = 30;
        
        $args = [
            'method' => 'GET',
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
            ],
            'timeout' => $connection_timeout,
        ];

        $response = wp_remote_request( $api_url . '?ref=' . urlencode( $branch ) . '&recursive=true&per_page=100', $args );

        if ( is_wp_error( $response ) ) {
            $this->verboseLog( 'Failed to get existing files: ' . $response->get_error_message() );
            return [];
        }
        
        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code === 404 ) {
            $this->verboseLog( "Branch '$branch' doesn't exist yet - assuming empty repository" );
            return [];
        } elseif ( $response_code !== 200 ) {
            $this->verboseLog( "Failed to get existing files (HTTP $response_code) - assuming empty repository" );
            return [];
        }

        $files = json_decode( wp_remote_retrieve_body( $response ), true );
        $file_paths = [];
        
        foreach ( $files as $file ) {
            if ( $file['type'] === 'blob' ) {
                $file_paths[] = $file['path'];
            }
        }
        
        $this->verboseLog( 'Found ' . count( $file_paths ) . ' existing files in repository' );
        return $file_paths;
    }

    private function handleDeletedFiles( array $local_files ) : void {
        $delete_orphaned_files = get_option( 'wp2static_gitlab_private_delete_orphaned_files', false );
        
        if ( ! $delete_orphaned_files ) {
            $this->verboseLog( 'Orphaned file deletion is disabled - skipping cleanup' );
            return;
        }

        $gitlab_url = get_option( 'wp2static_gitlab_private_url' );
        $project_id = get_option( 'wp2static_gitlab_private_project_id' );
        $access_token = get_option( 'wp2static_gitlab_private_access_token' );
        $branch = get_option( 'wp2static_gitlab_private_branch' );

        // Get current files in repository
        $existing_files = $this->getExistingFiles( $gitlab_url, $project_id, $access_token, $branch );
        
        // Get list of local file paths
        $local_file_paths = array_map( function( $file ) {
            return $file['remote_path'];
        }, $local_files );

        // Find files that exist in repository but not locally
        $files_to_delete = array_diff( $existing_files, $local_file_paths );

        if ( empty( $files_to_delete ) ) {
            // No orphaned files to clean up (silent)
            return;
        }

        WsLog::l( 'Removing ' . count( $files_to_delete ) . ' orphaned files from GitLab' );

        $this->deleteFilesFromRepository( $files_to_delete, $gitlab_url, $project_id, $access_token, $branch );
    }

    private function deleteFilesFromRepository( array $files_to_delete, string $gitlab_url, string $project_id, string $access_token, string $branch ) : void {
        $commit_message = get_option( 'wp2static_gitlab_private_commit_message' );
        $author_name = get_option( 'wp2static_gitlab_private_author_name' );
        $author_email = get_option( 'wp2static_gitlab_private_author_email' );

        $actions = [];
        
        foreach ( $files_to_delete as $file_path ) {
            $actions[] = [
                'action' => 'delete',
                'file_path' => $file_path,
            ];
        }

        $api_url = $gitlab_url . '/api/v4/projects/' . urlencode( $project_id ) . '/repository/commits';
        
        $payload = [
            'branch' => $branch,
            'commit_message' => 'Remove orphaned files (' . count( $actions ) . ' files)',
            'actions' => $actions,
            'author_name' => $author_name,
            'author_email' => $author_email,
        ];

        $response = $this->makeApiRequest( $api_url, $payload, $access_token );
        
        if ( $response ) {
            WsLog::l( "Successfully removed orphaned files from GitLab" );
        } else {
            WsLog::l( "Failed to delete orphaned files from GitLab" );
        }
    }

    private function makeApiRequest( string $url, array $payload, string $access_token ) {
        $api_timeout = 120; // A reasonable default
        
        $json_body = wp_json_encode( $payload );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            WsLog::l( 'JSON encoding error: ' . json_last_error_msg() );
            return false;
        }
        
        $args = [
            'method' => 'POST',
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $access_token,
            ],
            'body' => $json_body,
            'timeout' => $api_timeout,
        ];

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            WsLog::l( 'GitLab API request failed: ' . $error_message );
            return false;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        if ( $response_code >= 200 && $response_code < 300 ) {
            return json_decode( $response_body, true );
        } else {
            $error_msg = "GitLab API error (HTTP $response_code): $response_body";
            WsLog::l( $error_msg );
            return false;
        }
    }
} 