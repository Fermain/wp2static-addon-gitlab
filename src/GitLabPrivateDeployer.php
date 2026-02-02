<?php

namespace WP2StaticGitLabPrivate;

use WP2Static\WsLog;

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
            'gitlabDeploySubdir' => get_option( 'wp2static_gitlab_private_deploy_subdir', 'public' ),
            'gitlabCommitMessage' => get_option( 'wp2static_gitlab_private_commit_message', 'Deploy static site from WP2Static' ),
            'gitlabAuthorName' => get_option( 'wp2static_gitlab_private_author_name', 'WP2Static' ),
            'gitlabAuthorEmail' => get_option( 'wp2static_gitlab_private_author_email', 'noreply@wp2static.com' ),
            'gitlabDeleteOrphanedFiles' => get_option( 'wp2static_gitlab_private_delete_orphaned_files', false ),
            'gitlabVerboseLogging' => get_option( 'wp2static_gitlab_private_verbose_logging', false ),
            'gitlabTargetBranch' => get_option( 'wp2static_gitlab_private_target_branch', 'master' ),
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
        $deploy_subdir = sanitize_text_field( $_POST['gitlabDeploySubdir'] ?? '' );
        $deploy_subdir = trim( $deploy_subdir );
        $deploy_subdir = trim( $deploy_subdir, "/\t\n\r\0\x0B" );
        if ( $deploy_subdir === '' ) { $deploy_subdir = 'public'; }
        update_option( 'wp2static_gitlab_private_deploy_subdir', $deploy_subdir );
        update_option( 'wp2static_gitlab_private_commit_message', sanitize_text_field( $_POST['gitlabCommitMessage'] ?? '' ) );
        update_option( 'wp2static_gitlab_private_author_name', sanitize_text_field( $_POST['gitlabAuthorName'] ?? '' ) );
        update_option( 'wp2static_gitlab_private_author_email', sanitize_email( $_POST['gitlabAuthorEmail'] ?? '' ) );
        update_option( 'wp2static_gitlab_private_delete_orphaned_files', isset( $_POST['gitlabDeleteOrphanedFiles'] ) ? true : false );
        update_option( 'wp2static_gitlab_private_verbose_logging', isset( $_POST['gitlabVerboseLogging'] ) ? true : false );
        update_option( 'wp2static_gitlab_private_target_branch', sanitize_text_field( $_POST['gitlabTargetBranch'] ?? 'master' ) );
        
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
            
            $args_bearer = [
                'headers' => [ 'Authorization' => 'Bearer ' . $access_token ],
                'timeout' => $connection_timeout,
            ];
            $response = wp_remote_get( $api_url, $args_bearer );
            $response_code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
            if ( $response_code === 401 || $response_code === 403 || $response_code === 0 ) {
                $args_private = [
                    'headers' => [ 'PRIVATE-TOKEN' => $access_token ],
                    'timeout' => $connection_timeout,
                ];
                $response = wp_remote_get( $api_url, $args_private );
            }

            if ( is_wp_error( $response ) ) {
                throw new \Exception( 'Connection failed: ' . $response->get_error_message() );
            }

            $response_code = wp_remote_retrieve_response_code( $response );
            
            if ( $response_code === 200 ) {
                $project_data = json_decode( wp_remote_retrieve_body( $response ), true );
                $permissions = $project_data['permissions'] ?? [];
                $access_level = $permissions['project_access']['access_level'] ?? 0;
                $default_branch = $project_data['default_branch'] ?? 'main';
                $path_with_namespace = $project_data['path_with_namespace'] ?? '';
                
                $role_names = [
                    10 => 'Guest',
                    20 => 'Reporter', 
                    30 => 'Developer',
                    40 => 'Maintainer',
                    50 => 'Owner'
                ];
                
                $role_name = $role_names[$access_level] ?? "Unknown ($access_level)";
                
                if ( $access_level >= 40 ) {
                    $message = "Connection successful! Project: {$project_data['name']} ({$path_with_namespace}), Default branch: {$default_branch}, Role: $role_name ✓";
                    $type = 'success';
                } else {
                    $message = "Connection OK but insufficient permissions. Project: {$project_data['name']} ({$path_with_namespace}), Default branch: {$default_branch}, Role: $role_name. Need Maintainer (40) or higher for protected branches.";
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

            // Check if a filter profile (exclude_set) is being used
            $exclude_set = strval( filter_input( INPUT_POST, 'exclude_set' ) );
            if ( ! empty( $exclude_set ) ) {
                $this->verboseLog( "Filter profile '$exclude_set' detected for this run." );
            }

            $this->deployViaGit( $processed_site_path, $exclude_set );

            WsLog::l( 'GitLab Private deployment completed' );
        } catch ( \Exception $e ) {
            WsLog::l( 'GitLab Private deployment failed: ' . $e->getMessage() );
            throw $e;
        }
    }

    private function deployViaGit( string $processed_site_path, string $exclude_set = '' ) : void {
        $settings = $this->getSettings();
        $project = $this->fetchProjectInfo( $settings['url'], $settings['project_id'], $settings['token'] );
        if ( empty( $project['http_url_to_repo'] ) ) {
            throw new \Exception( 'Project repo URL missing from GitLab response' );
        }

        $remote = $this->buildRemoteUrlWithToken( $project['http_url_to_repo'], $settings['token'] );

        $upload_dir = wp_upload_dir();
        $work_root = trailingslashit( $upload_dir['basedir'] ) . 'wp2static-gitlab-private/tmp';
        if ( ! wp_mkdir_p( $work_root ) ) {
            throw new \Exception( 'Failed to create tmp directory' );
        }
        $repo_dir = $work_root . '/repo_' . uniqid();
        if ( ! wp_mkdir_p( $repo_dir ) ) {
            throw new \Exception( 'Failed to prepare repo directory' );
        }

        $mask = $settings['token'];

        // Preflight: ensure git is available
        $this->runCmd( [ 'git', '--version' ], $mask );
        $this->runCmd( [ 'git', 'clone', '--depth', '1', $remote, $repo_dir ], $mask );

        // Ensure author
        $author_name = get_option( 'wp2static_gitlab_private_author_name' );
        $author_email = get_option( 'wp2static_gitlab_private_author_email' );
        if ( ! is_string( $author_name ) || $author_name === '' ) { $author_name = 'WP2Static'; }
        if ( ! is_email( $author_email ) ) { $author_email = 'noreply@wp2static.com'; }
        $this->runCmd( [ 'git', '-C', $repo_dir, 'config', 'user.name', $author_name ], $mask );
        $this->runCmd( [ 'git', '-C', $repo_dir, 'config', 'user.email', $author_email ], $mask );

        // Prepare branches
        $push_branch = get_option( 'wp2static_gitlab_private_branch', 'main' );
        if ( ! is_string( $push_branch ) || $push_branch === '' ) {
            $push_branch = is_string( $project['default_branch'] ?? '' ) && $project['default_branch'] !== '' ? $project['default_branch'] : 'main';
        }
        
        $target_branch = get_option( 'wp2static_gitlab_private_target_branch', 'master' );
        if ( ! is_string( $target_branch ) || $target_branch === '' ) { $target_branch = 'master'; }
        
        $has_push_branch = $this->runCmd( [ 'git', '-C', $repo_dir, 'ls-remote', '--heads', 'origin', $push_branch ], $mask );
        $branch_is_new = ! ( $has_push_branch['code'] === 0 && trim( $has_push_branch['out'] ) !== '' );
        
        WsLog::l( '[GITLAB_PRIVATE] Branch detection: ' . $push_branch . ' is ' . ( $branch_is_new ? 'NEW' : 'EXISTING' ) );
        
        if ( $branch_is_new ) {
            WsLog::l( '[GITLAB_PRIVATE] Working branch does not exist, creating from target' );
            $has_target = $this->runCmd( [ 'git', '-C', $repo_dir, 'ls-remote', '--heads', 'origin', $target_branch ], $mask );
            if ( $has_target['code'] === 0 && trim( $has_target['out'] ) !== '' ) {
                $this->runCmd( [ 'git', '-C', $repo_dir, 'fetch', '--depth', '1', 'origin', $target_branch . ':refs/remotes/origin/' . $target_branch ], $mask );
                $this->runCmd( [ 'git', '-C', $repo_dir, 'checkout', '-B', $push_branch, 'origin/' . $target_branch ], $mask );
            } else {
                $this->runCmd( [ 'git', '-C', $repo_dir, 'checkout', '-B', $push_branch ], $mask );
            }
        } else {
            WsLog::l( '[GITLAB_PRIVATE] Working branch exists, building on it' );
            $this->runCmd( [ 'git', '-C', $repo_dir, 'fetch', 'origin', $push_branch . ':refs/remotes/origin/' . $push_branch ], $mask );
            $this->runCmd( [ 'git', '-C', $repo_dir, 'checkout', '-B', $push_branch, 'origin/' . $push_branch ], $mask );
        }

        // Scope-limited delete within deploy subdirectory
        $deploy_subdir = get_option( 'wp2static_gitlab_private_deploy_subdir', 'public' );
        if ( ! is_string( $deploy_subdir ) || $deploy_subdir === '' ) { $deploy_subdir = 'public'; }
        $target_root = rtrim( $repo_dir, '/\\' ) . '/' . trim( $deploy_subdir, '/\\' );
        if ( ! wp_mkdir_p( $target_root ) ) {
            throw new \Exception( 'Failed to create deploy subdirectory in repo: ' . $deploy_subdir );
        }

        $do_cleanup = (bool) get_option( 'wp2static_gitlab_private_delete_orphaned_files', false );
        if ( $do_cleanup ) {
            if ( ! empty( $exclude_set ) ) {
                WsLog::l( "[GITLAB_PRIVATE] Filter profile '$exclude_set' is active. Skipping orphaned file deletion to prevent accidental data loss." );
            } else {
                $this->deleteDirectoryContents( $target_root );
            }
        }

        // Copy processed site into deploy subdir
        $copied = $this->copyTree( $processed_site_path, $target_root );
        if ( $copied === 0 ) {
            WsLog::l( '[GITLAB_PRIVATE] NO_CHANGES: No files to copy; skipping commit' );
        }

        // Commit and push
        $this->runCmd( [ 'git', '-C', $repo_dir, 'add', '-A' ], $mask );
        $commit_message = get_option( 'wp2static_gitlab_private_commit_message', 'Deploy static site from WP2Static' );
        $message = $commit_message;
        if ( is_int( $copied ) && $copied > 0 ) { $message .= ' (' . $copied . ' files)'; }
        $commit = $this->runCmd( [ 'git', '-C', $repo_dir, 'commit', '-m', $message, '--author=' . $author_name . ' <' . $author_email . '>' ], $mask, false );
        $nothingToCommit = ( strpos( $commit['out'], 'nothing to commit' ) !== false );

        if ( ! $nothingToCommit ) {
            if ( $branch_is_new ) {
                $push_cmd = [
                    'git', '-C', $repo_dir, 'push', '-u', 'origin', 'HEAD:refs/heads/' . $push_branch,
                    '-o', 'merge_request.create',
                    '-o', 'merge_request.target=' . $target_branch,
                    '-o', 'merge_request.merge_when_pipeline_succeeds',
                    '-o', 'merge_request.remove_source_branch',
                    '-o', 'merge_request.title=' . $message,
                ];
                WsLog::l( '[GITLAB_PRIVATE] Pushing new branch "' . $push_branch . '" and creating MR to "' . $target_branch . '"' );
            } else {
                $this->runCmd( [ 'git', '-C', $repo_dir, 'fetch', 'origin', $push_branch . ':refs/remotes/origin/' . $push_branch ], $mask );
                $this->verboseLog( '[GITLAB_PRIVATE] Fetched latest state before push' );
                $push_cmd = [ 'git', '-C', $repo_dir, 'push', '-f', '-u', 'origin', 'HEAD:refs/heads/' . $push_branch ];
                WsLog::l( '[GITLAB_PRIVATE] Force pushing to existing branch "' . $push_branch . '" (MR will update if open)' );
            }
            
            WsLog::l( '[GITLAB_PRIVATE] Push command: git push with ' . ( $branch_is_new ? 'MR options' : 'force flag' ) );
            $push = $this->runCmd( $push_cmd, $mask, false );
            if ( $push['code'] !== 0 ) {
                $out = $push['out'];
                if ( is_string( $mask ) && $mask !== '' ) { $out = str_replace( $mask, '***', $out ); }
                WsLog::l( '[GITLAB_PRIVATE] PUSH_FAILED: ' . $out );
                throw new \Exception( 'git push failed: ' . $out );
            }
            
            WsLog::l( '[GITLAB_PRIVATE] COMMIT_OK: Changes committed and pushed' );
        } else {
            WsLog::l( '[GITLAB_PRIVATE] NO_CHANGES: Nothing to commit, skipping push' );
        }

        // Cleanup
        $this->rrmdir( $repo_dir );
    }

    private function getSettings() : array {
        $url = get_option( 'wp2static_gitlab_private_url' );
        $project_id = get_option( 'wp2static_gitlab_private_project_id' );
        $token = get_option( 'wp2static_gitlab_private_access_token' );
        return [
            'url' => is_string( $url ) ? rtrim( $url, '/' ) : '',
            'project_id' => is_string( $project_id ) ? $project_id : '',
            'token' => is_string( $token ) ? $token : '',
        ];
    }

    private function fetchProjectInfo( string $base_url, string $project_id, string $token ) : array {
        $api = $base_url . '/api/v4/projects/' . rawurlencode( $project_id );
        $args = [ 'headers' => [ 'Authorization' => 'Bearer ' . $token ], 'timeout' => 30 ];
        $res = wp_remote_get( $api, $args );
        $code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
        if ( $code < 200 || $code >= 300 ) {
            // Fallback to PRIVATE-TOKEN header
            $args2 = [ 'headers' => [ 'PRIVATE-TOKEN' => $token ], 'timeout' => 30 ];
            $res = wp_remote_get( $api, $args2 );
        }
        if ( is_wp_error( $res ) ) {
            throw new \Exception( $res->get_error_message() );
        }
        $body = json_decode( wp_remote_retrieve_body( $res ), true );
        return is_array( $body ) ? $body : [];
    }

    private function buildRemoteUrlWithToken( string $http_url, string $token ) : string {
        $parts = wp_parse_url( $http_url );
        if ( ! $parts || ! isset( $parts['scheme'] ) || ! isset( $parts['host'] ) ) {
            throw new \Exception( 'Invalid repo URL' );
        }
        $cred = 'oauth2:' . rawurlencode( $token ) . '@';
        $port = isset( $parts['port'] ) ? ( ':' . $parts['port'] ) : '';
        $path = isset( $parts['path'] ) ? $parts['path'] : '';
        return $parts['scheme'] . '://' . $cred . $parts['host'] . $port . $path;
    }

    private function runCmd( array $argv, string $mask = '', bool $throw_on_error = true ) : array {
        $descriptors = [
            0 => [ 'pipe', 'r' ],
            1 => [ 'pipe', 'w' ],
            2 => [ 'pipe', 'w' ],
        ];
        $env = [ 'LC_ALL' => 'C', 'PATH' => '/usr/local/bin:/usr/bin:/bin' ];
        $proc = proc_open( $argv, $descriptors, $pipes, null, $env );
        if ( ! is_resource( $proc ) ) {
            if ( $throw_on_error ) { throw new \Exception( 'Failed to start process' ); }
            return [ 'code' => 1, 'out' => 'Failed to start process' ];
        }
        fclose( $pipes[0] );
        $stdout = stream_get_contents( $pipes[1] ); fclose( $pipes[1] );
        $stderr = stream_get_contents( $pipes[2] ); fclose( $pipes[2] );
        $code = proc_close( $proc );
        $out = trim( $stdout . ( strlen( $stderr ) ? "\n" . $stderr : '' ) );
        if ( is_string( $mask ) && $mask !== '' ) { $out = str_replace( $mask, '***', $out ); }
        if ( $throw_on_error && $code !== 0 ) {
            throw new \Exception( $out );
        }
        return [ 'code' => $code, 'out' => $out ];
    }

    private function deleteDirectoryContents( string $dir ) : void {
        if ( ! is_dir( $dir ) ) { return; }
        $items = scandir( $dir );
        if ( ! is_array( $items ) ) { return; }
        foreach ( $items as $item ) {
            if ( $item === '.' || $item === '..' ) { continue; }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if ( is_dir( $path ) ) {
                $this->rrmdir( $path );
            } else {
                @unlink( $path );
            }
        }
    }

    private function rrmdir( string $dir ) : void {
        if ( ! is_dir( $dir ) ) { return; }
        $items = scandir( $dir );
        if ( ! is_array( $items ) ) { return; }
        foreach ( $items as $item ) {
            if ( $item === '.' || $item === '..' ) { continue; }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if ( is_dir( $path ) ) {
                $this->rrmdir( $path );
            } else {
                @unlink( $path );
            }
        }
        @rmdir( $dir );
    }

    private function copyTree( string $src_root, string $dst_root ) : int {
        $src_root = rtrim( $src_root, '/\\' );
        $dst_root = rtrim( $dst_root, '/\\' );
        $count = 0;
        $it = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $src_root, \FilesystemIterator::SKIP_DOTS ) );
        foreach ( $it as $file ) {
            if ( ! $file->isFile() ) { continue; }
            $abs = $file->getPathname();
            $rel = substr( $abs, strlen( $src_root ) );
            $rel = ltrim( str_replace( '\\', '/', $rel ), '/' );
            $dst = $dst_root . '/' . $rel;
            $dst_dir = dirname( $dst );
            if ( ! is_dir( $dst_dir ) ) { wp_mkdir_p( $dst_dir ); }
            if ( @copy( $abs, $dst ) ) { $count++; }
        }
        return $count;
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

} 