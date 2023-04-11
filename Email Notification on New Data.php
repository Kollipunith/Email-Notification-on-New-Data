<?php
/**
 * Plugin Name: Email Notification on New Data
 * Plugin URI: https://yourwebsite.com/email-notification-on-new-data
 * Description: This plugin monitors the selected database table and sends an email notification when new data is added to the table.
 * Version: 1.1
 * Author: Your Name
 * Author URI: yourwebsite.com
 * License: GPL-3.0
 */

if (!defined('ABSPATH')) { 
    exit; 
}

// Create the admin menu items
add_action('init', 'notifier_init');

function notifier_init() {
  add_action('wp_insert_post', 'notifier_send_email');
}


// Render the settings page
function email_notification_on_new_data_settings_page() {
    global $wpdb;
    $tables = $wpdb->get_col("SHOW TABLES");
    if (isset($_POST['email_notification_on_new_data_nonce']) && wp_verify_nonce($_POST['email_notification_on_new_data_nonce'], 'email_notification_on_new_data_update_settings')) {
        update_option('email_notification_on_new_data_table', sanitize_text_field($_POST['email_notification_on_new_data_table']));
        update_option('email_notification_on_new_data_email', sanitize_email($_POST['email_notification_on_new_data_email']));
        update_option('email_notification_on_new_data_email_body', wp_kses_post($_POST['email_notification_on_new_data_email_body']));
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
    }
    $selected_table = get_option('email_notification_on_new_data_table');
    $email = get_option('email_notification_on_new_data_email');
    $email_body = get_option('email_notification_on_new_data_email_body');
    ?> <div class="wrap">
    <h1>Email Notification on New Data</h1>
    <form method="post" action="">
        <?php wp_nonce_field('email_notification_on_new_data_update_settings', 'email_notification_on_new_data_nonce'); ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Database Table:</th>
                <td>
                    <select name="email_notification_on_new_data_table">
                        <option value="">Select a table</option> <?php foreach ($tables as $table) : ?> <option
                            value="<?php echo $table; ?>" <?php selected($selected_table, $table); ?>>
                            <?php echo $table; ?> </option> <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Email:</th>
                <td><input type="email" name="email_notification_on_new_data_email"
                        value="<?php echo esc_attr($email); ?>" /></td>
            </tr>
            <tr valign="top">
                <th scope="row">Email Body:</th>
                <td>
                    <textarea rows="10" cols="60"
                        name="email_notification_on_new_data_email_body"><?php echo esc_textarea($email_body); ?></textarea>
                    <p class="description">Use {column_name} as a placeholder for each column in the email body. Replace
                        "column_name" with the actual column name from the table.</p>
                </td>
            </tr>
        </table>
        <p class=“submit”> <input type=“submit” class=“button-primary” value=“Save Changes” /> </p>
    </form>
</div>
<?php
}

// Render the data selection page function email_notification_on_new_data_data_page() { global $wpdb; $table = get_option(‘email_notification_on_new_data_table’); if (empty($table)) { echo ‘<div class=“notice notice-warning is-dismissible”><p>Please select a database table in the plugin settings.</p></div>’; return; } $results = $wpdb->get_results(“SELECT * FROM $table”, ARRAY_A); $columns = array_keys($results[0]); ?>
<div class=“wrap”>
    <h1>Select Data</h1>
    <form method=“post” action=“<?php echo esc_url(admin_url(‘admin-post.php’)); ?>”> <input type=“hidden” name=“action”
            value=“email_notification_on_new_data_update_data”>
        <?php wp_nonce_field(‘email_notification_on_new_data_update_data’, ‘email_notification_on_new_data_nonce’); ?>
        <table class=“widefat striped”>
            <thead>
                <tr>
                    <th>Select</th> <?php foreach ($columns as $column) : ?> <th><?php echo $column; ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody> <?php foreach ($results as $result) : ?> <tr>
                    <td><input type=“checkbox” name=“email_notification_on_new_data_data[]”
                            value=“<?php echo $result[‘id’]; ?>” /></td> <?php foreach ($columns as $column) : ?> <td>
                        <?php echo $result[$column]; ?></td> <?php endforeach; ?>
                </tr> <?php endforeach; ?> </tbody>
        </table>
        <p class=“submit”> <input type=“submit” class=“button-primary” value=“Select Data” /> </p>
    </form>
</div> <?php
}

// Handle form submission on data selection page add_action(‘admin_post_email_notification_on_new_data_update_data’, ‘email_notification_on_new_data_update_data’); function email_notification_on_new_data_update_data() { global $wpdb; $table = get_option(‘email_notification_on_new_data_table’); $email = get_option(‘email_notification_on_new_data_email’); $email_body_template = get_option(‘email_notification_on_new_data_email_body’); if (empty($table) || empty($email) || empty($email_body_template)) { wp_die(‘Error: please ensure all required settings are configured.’); } $data = $_POST[‘email_notification_on_new_data_data’]; if (empty($data)) { wp_die(‘Error: please select at least one row of data.’); } $email_body = ‘’; $results = $wpdb->get_results(“SELECT * FROM $table WHERE id IN (” . implode(‘,’, array_map(‘intval’, $data)) . “)”, ARRAY_A); $columns = array_keys($results[0]); foreach ($results as $result) { $email_body .= $email_body_template . “\n\n”; foreach ($columns as $column) { $email_body = str_replace(“{{$column}}”, $result[$column], $email_body); } } wp_mail($email, “Selected data from {$table}”, $email_body); wp_redirect(admin_url(‘options-general.php?page=email-notification-on-new-data’)); exit; }
// Hook into the “shutdown” action to monitor the table for new data add_action(‘shutdown’, ‘email_notification_on_new_data_on_insert’, 0);

function email_notification_on_new_data_on_insert() { global $wpdb;

$table = get_option('email_notification_on_new_data_table');
$email = get_option('email_notification_on_new_data_email');
$email_body_template = get_option('email_notification_on_new_data_email_body');
$last_check = get_option('email_notification_on_new_data_last_check', 0);

if (empty($table) || empty($email) || empty($email_body_template)) {
    return;
}

$new_data = $wpdb->get_results("SELECT * FROM {$table} WHERE UNIX_TIMESTAMP({$table}.created_at) > {$last_check}");

if (!empty($new_data)) {
    $columns = array_keys((array)$new_data[0]);

    foreach ($new_data as $row) {
        $row = (array)$row;
        $email_body = $email_body_template;

        foreach ($columns as $column) {
            $email_body = str_replace("{{$column}}", $row[$column], $email_body);
        }

        wp_mail($email, "New data added to {$table}", $email_body);
    }
}

update_option('email_notification_on_new_data_last_check', time());
}
