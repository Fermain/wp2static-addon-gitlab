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
                        <label for="gitlabBranch">Working Branch</label>
                    </th>
                    <td>
                        <input 
                            type="text" 
                            name="gitlabBranch" 
                            id="gitlabBranch" 
                            value="<?php echo esc_attr( $options['gitlabBranch'] ); ?>" 
                            class="regular-text"
                        />
                        <p class="description">The branch to push deployments to (e.g., <code>staging</code>)</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="gitlabTargetBranch">Target Branch</label>
                    </th>
                    <td>
                        <input 
                            type="text" 
                            name="gitlabTargetBranch" 
                            id="gitlabTargetBranch" 
                            value="<?php echo esc_attr( $options['gitlabTargetBranch'] ); ?>" 
                            class="regular-text"
                        />
                        <p class="description">Merge requests will target this branch (e.g., <code>master</code>). MR is created automatically when working branch is new.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="gitlabDeploySubdir">Deploy Subdirectory</label>
                    </th>
                    <td>
                        <input 
                            type="text" 
                            name="gitlabDeploySubdir" 
                            id="gitlabDeploySubdir" 
                            value="<?php echo esc_attr( $options['gitlabDeploySubdir'] ); ?>" 
                            class="regular-text"
                        />
                        <p class="description">Deploy into this subdirectory in the repository (e.g., public)</p>
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
