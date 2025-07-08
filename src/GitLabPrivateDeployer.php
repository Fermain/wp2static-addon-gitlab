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
            
            // Timeout and performance options
            'gitlabApiTimeout' => get_option( 'wp2static_gitlab_private_api_timeout', 120 ),
            'gitlabConnectionTimeout' => get_option( 'wp2static_gitlab_private_connection_timeout', 30 ),
            'gitlabBatchSize' => get_option( 'wp2static_gitlab_private_batch_size', 20 ),
            'gitlabLargeFileThreshold' => get_option( 'wp2static_gitlab_private_large_file_threshold', 1048576 ),
            'gitlabAdaptiveBatching' => get_option( 'wp2static_gitlab_private_adaptive_batching', true ),
            'gitlabRetryAttempts' => get_option( 'wp2static_gitlab_private_retry_attempts', 3 ),
            'gitlabSuccessCommit' => get_option( 'wp2static_gitlab_private_success_commit', true ),
            'gitlabSquashCommits' => get_option( 'wp2static_gitlab_private_squash_commits', true ),
        ];
        
        // Calculate human-readable values for large file threshold (backwards compatibility)
        $threshold_value = get_option( 'wp2static_gitlab_private_large_file_threshold_value' );
        $threshold_unit = get_option( 'wp2static_gitlab_private_large_file_threshold_unit' );
        
        if ( empty( $threshold_value ) || empty( $threshold_unit ) ) {
            // Convert existing byte value to human readable
            $bytes = $options['gitlabLargeFileThreshold'];
            if ( $bytes >= 1048576 && $bytes % 1048576 === 0 ) {
                $threshold_value = $bytes / 1048576;
                $threshold_unit = 'MB';
            } elseif ( $bytes >= 1024 && $bytes % 1024 === 0 ) {
                $threshold_value = $bytes / 1024;
                $threshold_unit = 'KB';
            } else {
                $threshold_value = max( 1, $bytes / 1048576 );
                $threshold_unit = 'MB';
            }
        }
        
        $options['gitlabLargeFileThresholdValue'] = $threshold_value;
        $options['gitlabLargeFileThresholdUnit'] = $threshold_unit;
        
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
        
        // Save timeout and performance options
        update_option( 'wp2static_gitlab_private_api_timeout', max( 30, intval( $_POST['gitlabApiTimeout'] ?? 120 ) ) );
        update_option( 'wp2static_gitlab_private_connection_timeout', max( 10, intval( $_POST['gitlabConnectionTimeout'] ?? 30 ) ) );
        update_option( 'wp2static_gitlab_private_batch_size', max( 1, min( 100, intval( $_POST['gitlabBatchSize'] ?? 20 ) ) ) );
        
        // Handle large file threshold with human-friendly input
        $threshold_value = max( 1, intval( $_POST['gitlabLargeFileThresholdValue'] ?? 1 ) );
        $threshold_unit = sanitize_text_field( $_POST['gitlabLargeFileThresholdUnit'] ?? 'MB' );
        
        $threshold_bytes = $threshold_value;
        if ( $threshold_unit === 'MB' ) {
            $threshold_bytes = $threshold_value * 1048576;
        } elseif ( $threshold_unit === 'KB' ) {
            $threshold_bytes = $threshold_value * 1024;
        }
        
        // Ensure minimum threshold of 100KB
        $threshold_bytes = max( 102400, $threshold_bytes );
        
        update_option( 'wp2static_gitlab_private_large_file_threshold', $threshold_bytes );
        update_option( 'wp2static_gitlab_private_large_file_threshold_value', $threshold_value );
        update_option( 'wp2static_gitlab_private_large_file_threshold_unit', $threshold_unit );
        
        update_option( 'wp2static_gitlab_private_adaptive_batching', isset( $_POST['gitlabAdaptiveBatching'] ) ? true : false );
        update_option( 'wp2static_gitlab_private_retry_attempts', max( 1, min( 10, intval( $_POST['gitlabRetryAttempts'] ?? 3 ) ) ) );
        update_option( 'wp2static_gitlab_private_success_commit', isset( $_POST['gitlabSuccessCommit'] ) ? true : false );
        update_option( 'wp2static_gitlab_private_squash_commits', isset( $_POST['gitlabSquashCommits'] ) ? true : false );
        
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
            
            $connection_timeout = get_option( 'wp2static_gitlab_private_connection_timeout', 30 );
            
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
            $this->logDeploymentSettings();
            
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
        
        // Create success commit to signal CI systems
        $this->createSuccessCommit();
            
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

    private function logDeploymentSettings() : void {
        $threshold_value = get_option( 'wp2static_gitlab_private_large_file_threshold_value', 1 );
        $threshold_unit = get_option( 'wp2static_gitlab_private_large_file_threshold_unit', 'MB' );
        
        $settings = [
            'API Timeout' => get_option( 'wp2static_gitlab_private_api_timeout', 120 ) . 's',
            'Connection Timeout' => get_option( 'wp2static_gitlab_private_connection_timeout', 30 ) . 's',
            'Batch Size' => get_option( 'wp2static_gitlab_private_batch_size', 20 ),
            'Large File Threshold' => $threshold_value . ' ' . $threshold_unit,
            'Adaptive Batching' => get_option( 'wp2static_gitlab_private_adaptive_batching', true ) ? 'Enabled' : 'Disabled',
            'Retry Attempts' => get_option( 'wp2static_gitlab_private_retry_attempts', 3 ),
            'Success Commit' => get_option( 'wp2static_gitlab_private_success_commit', true ) ? 'Enabled' : 'Disabled',
            'Reduce Commits' => get_option( 'wp2static_gitlab_private_squash_commits', true ) ? 'Enabled' : 'Disabled',
        ];
        
        $settings_log = 'Deployment settings: ';
        foreach ( $settings as $key => $value ) {
            $settings_log .= "$key: $value, ";
        }
        $settings_log = rtrim( $settings_log, ', ' );
        
        $this->verboseLog( $settings_log );
    }



    private function createSuccessCommit() : void {
        $success_commit_enabled = get_option( 'wp2static_gitlab_private_success_commit', true );
        
        if ( ! $success_commit_enabled ) {
            $this->verboseLog( 'Success commit is disabled - skipping' );
            return;
        }

        try {
            $gitlab_url = get_option( 'wp2static_gitlab_private_url' );
            $project_id = get_option( 'wp2static_gitlab_private_project_id' );
            $access_token = get_option( 'wp2static_gitlab_private_access_token' );
            $branch = get_option( 'wp2static_gitlab_private_branch' );
            $author_name = get_option( 'wp2static_gitlab_private_author_name' );
            $author_email = get_option( 'wp2static_gitlab_private_author_email' );

            $api_url = $gitlab_url . '/api/v4/projects/' . urlencode( $project_id ) . '/repository/commits';
            
            $commit_message = 'Deployment completed successfully ✓';
            $timestamp = current_time( 'Y-m-d H:i:s T' );
            
            $payload = [
                'branch' => $branch,
                'commit_message' => $commit_message . "\n\nDeployment finished at: $timestamp",
                'actions' => [], // Empty actions array creates a blank commit
                'author_name' => $author_name,
                'author_email' => $author_email,
            ];

            WsLog::l( 'Creating success commit to signal deployment completion' );
            
            $response = $this->makeApiRequest( $api_url, $payload, $access_token );
            
            if ( $response ) {
                WsLog::l( 'Success commit created successfully' );
                $this->verboseLog( 'Success commit ID: ' . ( $response['id'] ?? 'unknown' ) );
            } else {
                WsLog::l( 'Failed to create success commit (not critical to deployment)' );
            }
            
        } catch ( \Exception $e ) {
            WsLog::l( 'Error creating success commit: ' . $e->getMessage() . ' (not critical to deployment)' );
        }
    }

    private function getFilesToDeploy( string $processed_site_path ) : array {
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
                
                // Use consistent path format for cache check
                $cache_path = '/' . ltrim( $relative_path, '/' );
                
                // TEMPORARY: Skip cache check for debugging blank commits
                // if ( ! $this->shouldDeployFile( $local_path, $cache_path ) ) {
                //     continue;
                // }
                
                // Still log cache status for debugging
                $is_cached = DeployCache::fileisCached( $cache_path, 'wp2static-gitlab-private' );
                if ( count( $files ) < 3 ) {
                    $this->verboseLog( "DEBUG: File $cache_path cache status: " . ( $is_cached ? 'cached' : 'not cached' ) );
                }
                
                $files[] = [
                    'local_path' => $local_path,
                    'remote_path' => $relative_path,
                ];
                
                // Debug first few files
                if ( count( $files ) <= 3 ) {
                    $this->verboseLog( "File " . count( $files ) . " - Local: $local_path, Remote: $relative_path" );
                }
            }
        }

        WsLog::l( "Found " . count( $files ) . " files to deploy from: $processed_site_path" );
        return $files;
    }

    private function shouldDeployFile( string $local_path, string $remote_path ) : bool {
        $is_cached = DeployCache::fileisCached( $remote_path, 'wp2static-gitlab-private' );
        $should_deploy = ! $is_cached;
        
        // Debug cache behavior for first few files
        static $debug_count = 0;
        if ( $debug_count < 5 ) {
            $this->verboseLog( "Cache check - $remote_path: " . ( $is_cached ? 'cached (skip)' : 'not cached (deploy)' ) );
            $debug_count++;
        }
        
        return $should_deploy;
    }

    private function deployFiles( array $files ) : void {
        $base_batch_size = get_option( 'wp2static_gitlab_private_batch_size', 20 );
        $large_file_threshold = get_option( 'wp2static_gitlab_private_large_file_threshold', 1048576 );
        $adaptive_batching = get_option( 'wp2static_gitlab_private_adaptive_batching', true );
        $squash_commits = get_option( 'wp2static_gitlab_private_squash_commits', true );
        
        // If squashing is enabled, use larger batches to create fewer commits
        if ( $squash_commits ) {
            $effective_batch_size = min( 100, $base_batch_size * 3 ); // Triple the batch size for fewer commits
            $this->verboseLog( "Squashing enabled - using larger batch size: $effective_batch_size" );
        } else {
            $effective_batch_size = $base_batch_size;
        }
        
        if ( $adaptive_batching ) {
            $batches = $this->createAdaptiveBatches( $files, $effective_batch_size, $large_file_threshold );
        } else {
            $batches = array_chunk( $files, $effective_batch_size );
        }
        
        $total_batches = count( $batches );
        $successful_batches = 0;
        $start_time = time();
        
        foreach ( $batches as $batch_index => $batch ) {
            $batch_start_time = microtime( true );
            
            WsLog::l( "Deploying batch " . ( $batch_index + 1 ) . " of $total_batches (" . count( $batch ) . " files)" );
            
            $success = $this->deployFileBatchWithRetry( $batch );
            
            if ( $success ) {
                $successful_batches++;
                $batch_duration = microtime( true ) - $batch_start_time;
                $this->verboseLog( sprintf( 'Batch %d completed in %.2f seconds', $batch_index + 1, $batch_duration ) );
            } else {
                $detailed_error = "Failed to deploy batch " . ( $batch_index + 1 ) . " after all retry attempts.\n\n";
                $detailed_error .= "Troubleshooting suggestions:\n";
                $detailed_error .= "1. Check your network connection and GitLab service status\n";
                $detailed_error .= "2. Increase API timeout (currently: " . get_option( 'wp2static_gitlab_private_api_timeout', 120 ) . "s)\n";
                $detailed_error .= "3. Reduce batch size (currently: " . get_option( 'wp2static_gitlab_private_batch_size', 20 ) . " files)\n";
                $detailed_error .= "4. Clear the Deploy Cache in WP2Static → Caches\n";
                $detailed_error .= "5. Try using a different branch name\n";
                
                WsLog::l( $detailed_error );
                throw new \Exception( "Deployment failed on batch " . ( $batch_index + 1 ) . " - check logs for details" );
            }
            
            // Add delay between batches to avoid rate limiting
            if ( $batch_index < $total_batches - 1 ) {
                sleep( 1 );
            }
        }
        
        $total_duration = time() - $start_time;
        WsLog::l( "Deployment completed: $successful_batches/$total_batches batches successful in {$total_duration}s" );
    }

    private function createAdaptiveBatches( array $files, int $base_batch_size, int $large_file_threshold ) : array {
        $batches = [];
        $current_batch = [];
        $current_batch_size = 0;
        $current_total_size = 0;
        
        foreach ( $files as $file ) {
            $file_size = file_exists( $file['local_path'] ) ? filesize( $file['local_path'] ) : 0;
            
            // Determine batch size for this file
            $target_batch_size = $base_batch_size;
            if ( $file_size > $large_file_threshold ) {
                // Reduce batch size for large files
                $target_batch_size = max( 1, intval( $base_batch_size / 4 ) );
                $this->verboseLog( "Large file detected ({$file['remote_path']}: " . size_format( $file_size ) . "), using smaller batch size: $target_batch_size" );
            }
            
            // Check if we should start a new batch
            $should_start_new_batch = false;
            
            if ( $current_batch_size >= $target_batch_size ) {
                $should_start_new_batch = true;
            } elseif ( $file_size > $large_file_threshold && $current_batch_size > 0 ) {
                // Start new batch for large files to avoid mixing with small files
                $should_start_new_batch = true;
            } elseif ( $current_total_size + $file_size > $large_file_threshold * 2 ) {
                // Start new batch if total size would be too large
                $should_start_new_batch = true;
            }
            
            if ( $should_start_new_batch && ! empty( $current_batch ) ) {
                $batches[] = $current_batch;
                $current_batch = [];
                $current_batch_size = 0;
                $current_total_size = 0;
            }
            
            $current_batch[] = $file;
            $current_batch_size++;
            $current_total_size += $file_size;
        }
        
        // Add the final batch if not empty
        if ( ! empty( $current_batch ) ) {
            $batches[] = $current_batch;
        }
        
        $this->verboseLog( "Created " . count( $batches ) . " adaptive batches from " . count( $files ) . " files" );
        return $batches;
    }

    private function deployFileBatchWithRetry( array $files ) : bool {
        $retry_attempts = get_option( 'wp2static_gitlab_private_retry_attempts', 3 );
        
        for ( $attempt = 1; $attempt <= $retry_attempts; $attempt++ ) {
            $success = $this->deployFileBatch( $files );
            
            if ( $success ) {
                if ( $attempt > 1 ) {
                    WsLog::l( "Batch deployment succeeded on attempt $attempt" );
                }
                return true;
            }
            
            if ( $attempt < $retry_attempts ) {
                $delay = min( pow( 2, $attempt - 1 ), 10 ); // Exponential backoff, max 10 seconds
                WsLog::l( "Batch deployment failed on attempt $attempt, retrying in {$delay}s..." );
                sleep( $delay );
            } else {
                WsLog::l( "Batch deployment failed after $retry_attempts attempts" );
            }
        }
        
        return false;
    }

    private function deployFileBatch( array $files ) : bool {
        $gitlab_url = get_option( 'wp2static_gitlab_private_url' );
        $project_id = get_option( 'wp2static_gitlab_private_project_id' );
        $access_token = get_option( 'wp2static_gitlab_private_access_token' );
        $branch = get_option( 'wp2static_gitlab_private_branch' );
        $commit_message = get_option( 'wp2static_gitlab_private_commit_message' );
        $author_name = get_option( 'wp2static_gitlab_private_author_name' );
        $author_email = get_option( 'wp2static_gitlab_private_author_email' );

        // Try with 'update' first (works for both new and existing files in most cases)
        $success = $this->attemptCommit( $files, $gitlab_url, $project_id, $access_token, $branch, $commit_message, $author_name, $author_email, 'update' );
        
        if ( ! $success ) {
            // If that fails, try with mixed strategy (silent retry)
            $success = $this->attemptMixedCommit( $files, $gitlab_url, $project_id, $access_token, $branch, $commit_message, $author_name, $author_email );
        }
        
        if ( ! $success ) {
            // Final fallback: try with 'create' for all files (handles case where files don't exist)
            $this->verboseLog( "Mixed strategy failed, trying create strategy as final fallback" );
            $success = $this->attemptCommit( $files, $gitlab_url, $project_id, $access_token, $branch, $commit_message, $author_name, $author_email, 'create' );
        }
        
        if ( $success ) {
            foreach ( $files as $file ) {
                DeployCache::addFile( $file['remote_path'], 'wp2static-gitlab-private' );
            }
            return true;
        } else {
            $this->verboseLog( "Batch deployment failed - will retry if attempts remaining" );
            return false;
        }
    }

    private function attemptCommit( array $files, string $gitlab_url, string $project_id, string $access_token, string $branch, string $commit_message, string $author_name, string $author_email, string $action ) : bool {
        $actions = [];
        $skipped_files = 0;
        
        foreach ( $files as $file ) {
            if ( ! file_exists( $file['local_path'] ) ) {
                WsLog::l( "File does not exist: " . $file['local_path'] );
                $skipped_files++;
                continue;
            }
            
            $content = file_get_contents( $file['local_path'] );
            if ( $content === false ) {
                WsLog::l( "Failed to read file: " . $file['local_path'] );
                $skipped_files++;
                continue;
            }

            // Additional validation
            if ( empty( $file['remote_path'] ) ) {
                WsLog::l( "Empty remote path for file: " . $file['local_path'] );
                $skipped_files++;
                continue;
            }

            $actions[] = [
                'action' => $action,
                'file_path' => $file['remote_path'],
                'content' => base64_encode( $content ),
                'encoding' => 'base64',
            ];
            
            // Debug first file in batch
            if ( count( $actions ) === 1 ) {
                $this->verboseLog( "First file - Local: {$file['local_path']}, Remote: {$file['remote_path']}, Size: " . strlen( $content ) . " bytes" );
            }
        }
        
        if ( $skipped_files > 0 ) {
            WsLog::l( "Skipped $skipped_files files due to errors" );
        }

        if ( empty( $actions ) ) {
            return true;
        }

        $api_url = $gitlab_url . '/api/v4/projects/' . urlencode( $project_id ) . '/repository/commits';
        
        $payload = [
            'branch' => $branch,
            'commit_message' => $commit_message . ' (' . count( $actions ) . ' files)',
            'actions' => $actions,
            'author_name' => $author_name,
            'author_email' => $author_email,
        ];

        WsLog::l( "Deploying " . count( $actions ) . " files to GitLab ($action)" );
        
        // Debug logging to identify blank commit issue (without massive base64 content)
        if ( ! empty( $actions ) ) {
            $sample_action = $actions[0];
            $debug_action = [
                'action' => $sample_action['action'],
                'file_path' => $sample_action['file_path'],
                'content_length' => strlen( $sample_action['content'] ?? '' ),
                'encoding' => $sample_action['encoding'] ?? 'none'
            ];
            $this->verboseLog( "Sample action for debugging: " . wp_json_encode( $debug_action ) );
        } else {
            WsLog::l( "ERROR: Actions array is empty despite having files!" );
        }
        
        $response = $this->makeApiRequest( $api_url, $payload, $access_token );
        
        if ( $response ) {
            WsLog::l( "Successfully deployed " . count( $actions ) . " files to GitLab using '$action' action" );
            return true;
        } else {
            $this->verboseLog( "Failed to deploy files using '$action' action - response was false/empty" );
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
        
        $connection_timeout = get_option( 'wp2static_gitlab_private_connection_timeout', 30 );
        
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

        // Process deletions in batches
        $batch_size = 20;
        $batches = array_chunk( $files_to_delete, $batch_size );

        foreach ( $batches as $batch_index => $batch ) {
            $actions = [];
            
            foreach ( $batch as $file_path ) {
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
                // Batch deleted successfully (silent unless error)
                if ( $batch_index === count( $batches ) - 1 ) {
                    WsLog::l( "Successfully removed orphaned files from GitLab" );
                }
            } else {
                WsLog::l( "Failed to delete orphaned files from GitLab" );
                // Don't throw exception here, just log the error
                return;
            }

            // Small delay between batches
            if ( $batch_index < count( $batches ) - 1 ) {
                sleep( 1 );
            }
        }
    }

    private function makeApiRequest( string $url, array $payload, string $access_token ) {
        $api_timeout = get_option( 'wp2static_gitlab_private_api_timeout', 120 );
        $start_time = microtime( true );
        
        // Debug payload structure
        $action_count = isset( $payload['actions'] ) ? count( $payload['actions'] ) : 0;
        $total_content_size = 0;
        if ( isset( $payload['actions'] ) ) {
            foreach ( $payload['actions'] as $action ) {
                $total_content_size += strlen( $action['content'] ?? '' );
            }
        }
        $this->verboseLog( "API payload: $action_count actions, total content size: " . size_format( $total_content_size ) );
        
        if ( $action_count > 0 && isset( $payload['actions'][0] ) ) {
            $first_action = $payload['actions'][0];
            $content_length = isset( $first_action['content'] ) ? strlen( $first_action['content'] ) : 0;
            $this->verboseLog( "First action: {$first_action['action']} - {$first_action['file_path']} - content length: $content_length chars" );
        }
        
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
        $request_duration = microtime( true ) - $start_time;

        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            WsLog::l( sprintf( 'GitLab API request failed after %.2fs: %s', $request_duration, $error_message ) );
            
            // Check for timeout-specific errors
            if ( strpos( $error_message, 'timeout' ) !== false || strpos( $error_message, 'timed out' ) !== false ) {
                WsLog::l( 'Request timed out - consider increasing API timeout or reducing batch size' );
            }
            
            return false;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        // Performance monitoring
        $file_count = isset( $payload['actions'] ) ? count( $payload['actions'] ) : 1;
        $this->verboseLog( sprintf( 'API request completed in %.2fs (%d files, %.2fs per file)', 
            $request_duration, $file_count, $request_duration / max( 1, $file_count ) ) );

        if ( $response_code >= 200 && $response_code < 300 ) {
            // Log slow requests for performance analysis
            if ( $request_duration > 30 ) {
                WsLog::l( sprintf( 'Slow API request detected: %.2fs for %d files - consider reducing batch size', 
                    $request_duration, $file_count ) );
            }
            
            return json_decode( $response_body, true );
        } else {
            $error_msg = "GitLab API error (HTTP $response_code): $response_body";
            WsLog::l( $error_msg );
            
            // Handle specific GitLab API errors  
            if ( $response_code === 400 ) {
                if ( strpos( $response_body, 'file with this name already exists' ) !== false ) {
                    WsLog::l( "File exists error - retrying with different strategy" );
                } elseif ( strpos( $response_body, "file with this name doesn't exist" ) !== false || 
                          strpos( $response_body, 'file with this name does not exist' ) !== false ) {
                    WsLog::l( "File doesn't exist error - retrying with create strategy" );
                } else {
                    WsLog::l( "Other 400 error - check request format or file paths" );
                }
            } elseif ( $response_code === 413 ) {
                WsLog::l( "Request too large (413) - try reducing batch size or large file threshold" );
            } elseif ( $response_code === 502 || $response_code === 503 || $response_code === 504 ) {
                WsLog::l( "Server error ($response_code) - GitLab may be experiencing issues, will retry" );
            }
            
            return false;
        }
    }
} 