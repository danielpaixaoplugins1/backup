<?php
/*
Plugin Name: WPCM Database Backup
Plugin URI: https://centralmidia.net.br/dev
Description: Um plugin para backup do banco de dados MySQL com agendamento e compressão de arquivo.
Version: 1.5
Author: Daniel Oliveira da Paixao
Author URI: https://centralmidia.net.br/dev
*/

// Função para realizar o backup do banco de dados
function wpcm_backup_database() {
    $db_user = escapeshellcmd(DB_USER);
    $db_password = escapeshellcmd(DB_PASSWORD);
    $db_host = escapeshellcmd(DB_HOST);
    $db_name = escapeshellcmd(DB_NAME);

    $upload_dir = wp_upload_dir()['basedir'];
    $backup_dir = $upload_dir . '/wpcm_backups';

    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }

    $timestamp = current_time('Y-m-d_H-i-s');
    $compressed_file_name = "db_backup_{$timestamp}.sql.gz";
    $backup_file_path = escapeshellarg($backup_dir . '/' . $compressed_file_name);

    $command = "mysqldump --user={$db_user} --password={$db_password} --host={$db_host} {$db_name} | gzip > {$backup_file_path}";

    exec($command, $output, $return_var);

    if ($return_var === 0) {
        add_settings_error('wpcm_backup_messages', 'wpcm_backup_success', 'Backup realizado com sucesso!', 'updated');
    } else {
        add_settings_error('wpcm_backup_messages', 'wpcm_backup_error', 'Erro ao realizar backup.', 'error');
    }
}
// Função para agendar o backup com base nas configurações do usuário
function wpcm_schedule_backup() {
    $frequency = get_option('wpcm_backup_frequency');
    $time = get_option('wpcm_backup_time');

    wp_clear_scheduled_hook('wpcm_backup_hook');

    if ($frequency && $time) {
        $time_parts = explode(':', $time);
        $scheduled_time = strtotime("today {$time_parts[0]}:{$time_parts[1]}");
        wp_schedule_event($scheduled_time, $frequency, 'wpcm_backup_hook');
    }
}

// Função para adicionar a página de configurações no menu do WordPress
function wpcm_backup_menu() {
    add_options_page('WPCM Database Backup Settings', 'WPCM Database Backup', 'manage_options', 'wpcm-database-backup', 'wpcm_backup_options_page');
}

// Função para renderizar a página de configurações
function wpcm_backup_options_page() {
    ?>
    <div class="wrap">
        <h2>WPCM Database Backup Settings</h2>
        <form method="post" action="options.php">
            <?php
            settings_fields('wpcm_backup_options_group');
            do_settings_sections('wpcm-database-backup');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}
// Função para registrar as configurações
function wpcm_backup_register_settings() {
    register_setting('wpcm_backup_options_group', 'wpcm_backup_frequency');
    register_setting('wpcm_backup_options_group', 'wpcm_backup_time');

    add_settings_section('wpcm_backup_section', 'Backup Settings', null, 'wpcm-database-backup');

    add_settings_field('wpcm_backup_frequency', 'Backup Frequency', 'wpcm_backup_frequency_callback', 'wpcm-database-backup', 'wpcm_backup_section');
    add_settings_field('wpcm_backup_time', 'Backup Time', 'wpcm_backup_time_callback', 'wpcm-database-backup', 'wpcm_backup_section');
}

// Callbacks para renderizar os campos de configuração
function wpcm_backup_frequency_callback() {
    $frequency = get_option('wpcm_backup_frequency');
    ?>
    <select name="wpcm_backup_frequency">
        <option value="daily" <?php selected($frequency, 'daily'); ?>>Daily</option>
        <option value="twicedaily" <?php selected($frequency, 'twicedaily'); ?>>Twice Daily</option>
        <option value="hourly" <?php selected($frequency, 'hourly'); ?>>Hourly</option>
    </select>
   
    <?php
}

function wpcm_backup_time_callback() {
    $time = get_option('wpcm_backup_time');
    ?>
    <input type="time" name="wpcm_backup_time" value="<?php echo esc_attr($time); ?>">
    <?php
}
// Hooks para adicionar a página de configurações, registrar as configurações e agendar o backup
add_action('admin_menu', 'wpcm_backup_menu');
add_action('admin_init', 'wpcm_backup_register_settings');
add_action('update_option_wpcm_backup_frequency', 'wpcm_schedule_backup');
add_action('update_option_wpcm_backup_time', 'wpcm_schedule_backup');

// Hook para realizar o backup
add_action('wpcm_backup_hook', 'wpcm_backup_database');

// Hooks para limpar o agendamento ao ativar/desativar o plugin
register_activation_hook(__FILE__, 'wpcm_schedule_backup');
register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('wpcm_backup_hook');
});
