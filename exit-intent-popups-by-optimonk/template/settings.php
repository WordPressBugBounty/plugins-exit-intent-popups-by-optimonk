<h2><a href="<?php echo $domain ?>" title="OptiMonk" target="_blank"><img src="<?php echo $pluginDirUrl; ?>/assets/logo.png"></a></h2>
<div class="register-trial"><?php echo $registerText; ?></div>
<?php do_action( 'optimonk_all_admin_notices' ); ?>
<div class="error" id="update-error" style="display: none;"><p><?php echo __('Wrong account id!', 'optimonk') ?></p></div>
<div class="updated" id="update-success" style="display: none;"><p><?php echo __('Your data successfully updated!', 'optimonk'); ?></p></div>
<div id="settings" class="om-wrapper">
    <div class="container">
        <div class="form-wrapper">
            <form method="post" id="settings-form" action="<?php echo admin_url('admin-ajax.php'); ?>">
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="optiMonk-accountId"><?php echo __('Account Id', 'optimonk'); ?></label></th>
                        <td><input type="text" name="optiMonk_accountId" id="optiMonk-accountId" value="<?php echo get_option('optiMonk_accountId'); ?>" /><a id="insert-code-tooltip" class="dashicons dashicons-editor-help" target="_blank" href="https://support.optimonk.com/hc/en-us/articles/202963241-Integrating-OptiMonk-with-Wordpress-website-#sign-in"></a></td>
                        <?php wp_nonce_field( 'optimonk_setting', 'optimonk_setting_nonce' ); ?>
                    </tr>
                    <tr>
                        <td colspan="2"><?php @submit_button(); ?></td>
                    </tr><tr>
                        <td colspan="2"><?php echo $reviewLink; ?></td>
                    </tr>
                </table>
            </form>
            <div class="clearfix"></div>
        </div>
        <div class="descriptions">
            <div class="panel">
                <div class="panel-heading"></div>
                <div class="panel-body">
                    <?php echo $customVariablesDescription; ?>
                </div>
            </div>
        </div>
        <div class="clearfix"></div>
    </div>
</div>
