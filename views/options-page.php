<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * GitLab Private Deployment Options Page
 *
 * @var array $options
 * @var string $nonce
 */

// Show admin notices
if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] === 'true' ) {
    ?>
    <div class="notice notice-success is-dismissible">
        <p><?php _e( 'Settings saved.', 'wp2static-addon-gitlab-private' ); ?></p>
    </div>
    <?php
}

if ( isset( $_GET['test_result'] ) && isset( $_GET['test_message'] ) ) {
    $test_result = sanitize_text_field( $_GET['test_result'] );
    $test_message = urldecode( sanitize_text_field( $_GET['test_message'] ) );
    
    $notice_class = 'notice-error';
    if ( $test_result === 'success' ) {
        $notice_class = 'notice-success';
    } elseif ( $test_result === 'warning' ) {
        $notice_class = 'notice-warning';
    }
    ?>
    <div class="notice <?php echo esc_attr( $notice_class ); ?> is-dismissible">
        <p><?php echo esc_html( $test_message ); ?></p>
    </div>
    <?php
}
?>

<div class="wrap">
    <h1>WP2Static GitLab Private Deployment</h1>
    
    <div style="background: #fff; padding: 20px; margin: 20px 0; border-left: 4px solid #0073aa;">
        <h3>Authentication Setup</h3>
        <p><strong>Use Project Access Tokens for better security:</strong></p>
        <ol>
            <li>Go to your GitLab project → Settings → Access Tokens</li>
            <li>Create a new token with <code><strong>Maintainer</strong></code> role (not Developer)</li>
            <li>Grant <code>api</code> and <code>write_repository</code> scopes</li>
            <li>Use this token below instead of a Personal Access Token</li>
        </ol>
        <p><strong>Important:</strong> The token needs <strong>Maintainer</strong> role to push to protected branches like 'main'. If you get permission errors, either:</p>
        <ul>
            <li>Use a Maintainer-level token, or</li>
            <li>Use a different branch name (like 'wp2static-deploy')</li>
        </ul>
    </div>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="wp2static_gitlab_private_save_options">
        <?php wp_nonce_field( 'wp2static-gitlab-private-options' ); ?>
        
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="gitlabUrl">GitLab URL</label>
                    </th>
                    <td>
                        <input 
                            type="url" 
                            name="gitlabUrl" 
                            id="gitlabUrl" 
                            value="<?php echo esc_attr( $options['gitlabUrl'] ); ?>" 
                            class="regular-text"
                            required
                        />
                        <p class="description">The base URL of your GitLab instance (e.g., https://gitlab.com or https://gitlab.yourcompany.com)</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="gitlabProjectId">Project ID</label>
                    </th>
                    <td>
                        <input 
                            type="text" 
                            name="gitlabProjectId" 
                            id="gitlabProjectId" 
                            value="<?php echo esc_attr( $options['gitlabProjectId'] ); ?>" 
                            class="regular-text"
                            required
                        />
                        <p class="description">Your GitLab project ID (found in project settings or URL)</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="gitlabAccessToken">Access Token</label>
                    </th>
                    <td>
                        <input 
                            type="password" 
                            name="gitlabAccessToken" 
                            id="gitlabAccessToken" 
                            value="<?php echo esc_attr( $options['gitlabAccessToken'] ); ?>" 
                            class="regular-text"
                            required
                        />
                        <p class="description">Project Access Token or Personal Access Token with API and repository write access</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="gitlabBranch">Target Branch</label>
                    </th>
                    <td>
                        <input 
                            type="text" 
                            name="gitlabBranch" 
                            id="gitlabBranch" 
                            value="<?php echo esc_attr( $options['gitlabBranch'] ); ?>" 
                            class="regular-text"
                        />
                        <p class="description">Branch to deploy to (default: main)</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="gitlabCommitMessage">Commit Message</label>
                    </th>
                    <td>
                        <input 
                            type="text" 
                            name="gitlabCommitMessage" 
                            id="gitlabCommitMessage" 
                            value="<?php echo esc_attr( $options['gitlabCommitMessage'] ); ?>" 
                            class="large-text"
                        />
                        <p class="description">Message for commits (file count will be appended)</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="gitlabAuthorName">Author Name</label>
                    </th>
                    <td>
                        <input 
                            type="text" 
                            name="gitlabAuthorName" 
                            id="gitlabAuthorName" 
                            value="<?php echo esc_attr( $options['gitlabAuthorName'] ); ?>" 
                            class="regular-text"
                        />
                        <p class="description">Name to use for commit author</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="gitlabAuthorEmail">Author Email</label>
                    </th>
                    <td>
                        <input 
                            type="email" 
                            name="gitlabAuthorEmail" 
                            id="gitlabAuthorEmail" 
                            value="<?php echo esc_attr( $options['gitlabAuthorEmail'] ); ?>" 
                            class="regular-text"
                        />
                        <p class="description">Email to use for commit author</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="gitlabDeleteOrphanedFiles">Delete Orphaned Files</label>
                    </th>
                    <td>
                        <label>
                            <input 
                                type="checkbox" 
                                name="gitlabDeleteOrphanedFiles" 
                                id="gitlabDeleteOrphanedFiles" 
                                value="1"
                                <?php checked( $options['gitlabDeleteOrphanedFiles'] ); ?>
                            />
                            Automatically delete files from GitLab that no longer exist in WordPress
                        </label>
                        <p class="description">
                            <strong>Use with caution:</strong> When enabled, files that exist in your GitLab repository but not in your WordPress static site will be automatically deleted. 
                            This ensures your repository stays in sync but may remove files you want to keep.
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="gitlabVerboseLogging">Verbose Logging</label>
                    </th>
                    <td>
                        <label>
                            <input 
                                type="checkbox" 
                                name="gitlabVerboseLogging" 
                                id="gitlabVerboseLogging" 
                                value="1"
                                <?php checked( $options['gitlabVerboseLogging'] ); ?>
                            />
                            Enable detailed logging for troubleshooting
                        </label>
                        <p class="description">
                            <strong>Development/Debug only:</strong> Logs detailed information about file processing and API calls. 
                            Turn off for production to reduce log noise.
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <h2>Performance & Timeout Settings</h2>
        <p>Configure timeouts and batch sizes to optimize for your network conditions.</p>
        
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="gitlabApiTimeout">API Request Timeout</label>
                    </th>
                    <td>
                        <input 
                            type="number" 
                            name="gitlabApiTimeout" 
                            id="gitlabApiTimeout" 
                            value="<?php echo esc_attr( $options['gitlabApiTimeout'] ); ?>" 
                            class="small-text"
                            min="30"
                            max="600"
                        /> seconds
                        <p class="description">
                            Timeout for GitLab API requests (30-600 seconds). Increase for slower networks. Default: 120 seconds.
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="gitlabConnectionTimeout">Connection Test Timeout</label>
                    </th>
                    <td>
                        <input 
                            type="number" 
                            name="gitlabConnectionTimeout" 
                            id="gitlabConnectionTimeout" 
                            value="<?php echo esc_attr( $options['gitlabConnectionTimeout'] ); ?>" 
                            class="small-text"
                            min="10"
                            max="120"
                        /> seconds
                        <p class="description">
                            Timeout for connection tests (10-120 seconds). Default: 30 seconds.
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="gitlabBatchSize">Batch Size</label>
                    </th>
                    <td>
                        <input 
                            type="number" 
                            name="gitlabBatchSize" 
                            id="gitlabBatchSize" 
                            value="<?php echo esc_attr( $options['gitlabBatchSize'] ); ?>" 
                            class="small-text"
                            min="1"
                            max="100"
                        /> files per request
                        <p class="description">
                            Number of files to upload per GitLab API request (1-100). Smaller batches are more reliable but slower. Default: 20.
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="gitlabLargeFileThresholdValue">Large File Threshold</label>
                    </th>
                    <td>
                        <input 
                            type="number" 
                            name="gitlabLargeFileThresholdValue" 
                            id="gitlabLargeFileThresholdValue" 
                            value="<?php echo esc_attr( $options['gitlabLargeFileThresholdValue'] ); ?>" 
                            class="small-text"
                            min="1"
                            max="1000"
                        />
                        <select name="gitlabLargeFileThresholdUnit" id="gitlabLargeFileThresholdUnit">
                            <option value="KB" <?php selected( $options['gitlabLargeFileThresholdUnit'], 'KB' ); ?>>KB</option>
                            <option value="MB" <?php selected( $options['gitlabLargeFileThresholdUnit'], 'MB' ); ?>>MB</option>
                        </select>
                        <p class="description">
                            Files larger than this size will be processed in smaller batches (minimum 100KB). Default: 1 MB.
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="gitlabAdaptiveBatching">Adaptive Batching</label>
                    </th>
                    <td>
                        <label>
                            <input 
                                type="checkbox" 
                                name="gitlabAdaptiveBatching" 
                                id="gitlabAdaptiveBatching" 
                                value="1"
                                <?php checked( $options['gitlabAdaptiveBatching'] ); ?>
                            />
                            Automatically reduce batch sizes for large files and slow networks
                        </label>
                        <p class="description">
                            When enabled, the plugin will automatically use smaller batch sizes when uploading large files or detecting slow network performance.
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="gitlabRetryAttempts">Retry Attempts</label>
                    </th>
                    <td>
                        <input 
                            type="number" 
                            name="gitlabRetryAttempts" 
                            id="gitlabRetryAttempts" 
                            value="<?php echo esc_attr( $options['gitlabRetryAttempts'] ); ?>" 
                            class="small-text"
                            min="1"
                            max="10"
                        /> attempts
                        <p class="description">
                            Number of times to retry failed requests (1-10). More retries increase reliability but take longer on persistent failures. Default: 3.
                        </p>
                    </td>
                </tr>
                

                
                <tr>
                    <th scope="row">
                        <label for="gitlabSquashCommits">Reduce Deployment Commits</label>
                    </th>
                    <td>
                        <label>
                            <input 
                                type="checkbox" 
                                name="gitlabSquashCommits" 
                                id="gitlabSquashCommits" 
                                value="1"
                                <?php checked( $options['gitlabSquashCommits'] ); ?>
                            />
                            Use larger batch sizes to create fewer commits during deployment
                        </label>
                        <p class="description">
                            <strong>Recommended:</strong> When enabled, the plugin will use larger batch sizes (up to 3x the normal size) to significantly reduce the number of commits created during deployment. This keeps git history much cleaner, especially on large initial deployments.
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <?php submit_button( 'Save Settings' ); ?>
    </form>
    
    <h2>Test Connection</h2>
    
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
        <input type="hidden" name="action" value="wp2static_gitlab_private_test_connection">
        <?php wp_nonce_field( 'wp2static-gitlab-private-test' ); ?>
        <?php submit_button( 'Test Connection', 'secondary', 'test', false ); ?>
    </form>
</div>

<style>
.submit-section {
    border-top: 1px solid #ddd;
    padding-top: 20px;
}
</style> 