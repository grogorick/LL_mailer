<?php
/*
Plugin Name:  LL_mailer
Plugin URI:   https://linda-liest.de/
Description:  New Post Notification Mail
Version:      1
Author:       Steve
Author URI:   https://linda-liest.de/
License:      Unlicense
License URI:  https://unlicense.org/
*/

if (!defined('ABSPATH')) { header('Location: https://linda-liest.de/'); exit; }



class LL_mailer
{
  const _ = 'LL_mailer';
  
  const option_msg                    = LL_mailer::_ . '_msg';
  const option_sender_name            = LL_mailer::_ . '_sender_name';
  const option_sender_mail            = LL_mailer::_ . '_sender_mail';
  const option_subscriber_attributes  = LL_mailer::_ . '_subscriber_attributes';
  
  const subscriber_attribute_mail     = 'mail';
  const subscriber_attribute_name     = 'name';
  
  const table_templates               = LL_mailer::_ . '_templates';
  const table_messages                = LL_mailer::_ . '_messages';
  const table_subscribers             = LL_mailer::_ . '_subscribers';
  
  const admin_page_settings           = LL_mailer::_ . '_settings';
  const admin_page_templates          = LL_mailer::_ . '_templates';
  const admin_page_template_edit      = LL_mailer::_ . '_templates&edit=';
  const admin_page_messages           = LL_mailer::_ . '_messages';
  const admin_page_message_edit       = LL_mailer::_ . '_messages&edit=';
  const admin_page_subscribers        = LL_mailer::_ . 'subscribers';
  const admin_page_subscriber_edit    = LL_mailer::_ . 'subscribers&edit=';
  
  const token_template_content        = '[CONTENT]';
  
  const list_item = '&ndash; &nbsp;';
  const arrow_up = '&#x2934;';
  const arrow_down = '&#x2935;';
  
  const html_prefix = '<html><head></head><body>';
  const html_suffix = '</body></html>';
  
  
  
	static function _($member_function) { return array(LL_mailer::_, $member_function); }
  
  static function pluginPath() { return plugin_dir_path(__FILE__); }
  static function admin_url() { return get_admin_url() . 'admin.php?page='; }
  static function json_url() { return get_rest_url() . 'LL_mailer/v1/'; }

  static function get_option_array($option) {
    $val = get_option($option);
    if (empty($val))
      return array();
    return $val;
  }
  
  static function is_predefined_subscriber_attribute($attr) { return in_array($attr, array(LL_mailer::subscriber_attribute_mail, LL_mailer::subscriber_attribute_name)); }
  
  
  
  static function message($msg)
  {
    $msgs = LL_mailer::get_option_array(LL_mailer::option_msg);
    $msgs[] = $msg;
    update_option(LL_mailer::option_msg, $msgs);
  }
  
  static function admin_notices()
  {
    // notice-error, notice-warning, notice-success or notice-info
    // is-dismissible
    $msgs = LL_mailer::get_option_array(LL_mailer::option_msg);
    if (!empty($msgs)) {
      ?><div class="notice notice-info is-dismissible"><?php
      foreach ($msgs as $msg) {
        if (!isset($first_line)) $first_line = true; else echo '<hr />';
        ?><p><?=nl2br($msg)?></p><?php
      }
      ?></div><?php
      delete_option(LL_mailer::option_msg);
    }
  }
  
  
  
  static function array_zip($glue_key_value, $array, $glue_rows = null, $prefix_if_not_empty = '', $suffix_if_not_empty = '')
  {
    if (empty($array)) {
      return is_null($glue_rows) ? array() : '';
    }
    if (is_null($glue_rows)) {
      array_walk($array, function(&$val, $key) 
                                use ($glue_key_value, $suffix_if_not_empty, $prefix_if_not_empty) 
                                { $val = $prefix_if_not_empty . $key . $glue_key_value . $val . $suffix_if_not_empty; });
      return $array;
    }
    else {
      array_walk($array, function(&$val, $key) use ($glue_key_value) { $val = $key . $glue_key_value . $val; });
      return $prefix_if_not_empty . implode($glue_rows, $array) . $suffix_if_not_empty;
    }
  }
  
  static function escape_keys($keys)
  {
    if (is_array($keys)) {
      return array_map(function($key) {
        return '`' . $key . '`';
      }, $keys);
    }
    if ($keys != '*')
      return '`' . $keys . '`';
    return $keys;
  }
  
  static function escape_values($array)
  {
    return array_map(function($val) {
      return (!is_null($val)) ? '"' . $val . '"' : 'NULL';
    }, $array);
  }
  
  static function _db_build_select($table, $what, $where)
  {
    if (is_array($what)) {
      $what = implode(', ', LL_mailer::escape_keys($what));
    }
    else {
      $what = LL_mailer::escape_keys($what);
    }
    $sql = 'SELECT ' . $what . ' FROM ' . LL_mailer::escape_keys($table) . LL_mailer::array_zip('` = ', LL_mailer::escape_values($where), ' AND `', ' WHERE `') . ';';
    // LL_mailer::message($sql);
    return $sql;
  }
  
  static function _db_insert_or_update($table, $data, $primary_key, $timestamp_key = null)
  {
    $data = LL_mailer::escape_values($data);
    if (!is_null($timestamp_key))
      $data[$timestamp_key] = 'NOW()';
    $data_keys = LL_mailer::escape_keys(array_keys($data));
    $data_values = array_values($data);
    $data_no_key = array_filter($data, function($val) use ($primary_key) { return $val != $primary_key; }, ARRAY_FILTER_USE_KEY);
    global $wpdb;
    $sql = 'INSERT INTO ' . LL_mailer::escape_keys($wpdb->prefix . $table) . ' ( ' . implode(', ', $data_keys) . ' ) 
            VALUES ( ' . implode(', ', $data_values) . ' )' . 
            LL_mailer::array_zip('` = ', $data_no_key, ', `', ' ON DUPLICATE KEY UPDATE `') . ';';
    // LL_mailer::message($sql);
    return $wpdb->query($sql);
  }
  
  static function _db_delete($table, $where)
  {
    global $wpdb;
    $sql = 'DELETE FROM ' . LL_mailer::escape_keys($wpdb->prefix . $table) . LL_mailer::array_zip('` = ', LL_mailer::escape_values($where), ' AND `', ' WHERE `') . ';';
    // LL_mailer::message($sql);
    return $wpdb->query($sql);
  }
  
  static function _db_select($table, $what = '*', $where = array())
  {
    global $wpdb;
    return $wpdb->get_results(LL_mailer::_db_build_select($wpdb->prefix . $table, $what, $where), ARRAY_A);
  }
  
  static function _db_select_row($table, $what = '*', $where = array())
  {
    global $wpdb;
    return $wpdb->get_row(LL_mailer::_db_build_select($wpdb->prefix . $table, $what, $where), ARRAY_A);
  }
  
  // templates
  // - slug
  // - body_html
  // - body_text
  // - last_modified
  static function db_save_template($template) { return LL_mailer::_db_insert_or_update(LL_mailer::table_templates, $template, 'slug', 'last_modified'); }
  static function db_delete_template($slug) { return LL_mailer::_db_delete(LL_mailer::table_templates, array('slug' => $slug)); }
  static function db_get_template_by_slug($slug) { return LL_mailer::_db_select_row(LL_mailer::table_templates, '*', array('slug' => $slug)); }
  static function db_get_templates($what) { return LL_mailer::_db_select(LL_mailer::table_templates, $what); }
  
  // messages
  // - slug
  // - subject
  // - template_slug
  // - body_html
  // - body_text
  // - last_modified
  static function db_save_message($message) { return LL_mailer::_db_insert_or_update(LL_mailer::table_messages, $message, 'slug', 'last_modified'); }
  static function db_delete_message($slug) { return LL_mailer::_db_delete(LL_mailer::table_messages, array('slug' => $slug)); }
  static function db_get_message_by_slug($slug) { return LL_mailer::_db_select_row(LL_mailer::table_messages, '*', array('slug' => $slug)); }
  static function db_get_messages($what) { return LL_mailer::_db_select(LL_mailer::table_messages, $what); }
  static function db_get_messages_by_template($template_slug) { return array_map(function($v) { return $v['slug']; }, LL_mailer::_db_select(LL_mailer::table_messages, 'slug', array('template_slug' => $template_slug))); }
  
  // subscribers
  // - mail
  // - subscribed_at
  // [...]
  static function db_save_subscriber($subscriber) { return LL_mailer::_db_insert_or_update(LL_mailer::table_subscribers, $subscriber, LL_mailer::subscriber_attribute_mail); }
  static function db_delete_subscriber($mail) { return LL_mailer::_db_delete(LL_mailer::table_subscribers, array(LL_mailer::subscriber_attribute_mail => $mail)); }
  static function db_get_subscriber_by_mail($mail) { return LL_mailer::_db_select_row(LL_mailer::table_subscribers, '*', array(LL_mailer::subscriber_attribute_mail => $mail)); }
  static function db_get_subscribers($what) { return LL_mailer::_db_select(LL_mailer::table_subscribers, $what); }
  
  static function db_subscribers_add_attribute($attr) {
    global $wpdb;
    $sql = 'ALTER TABLE ' . LL_mailer::escape_keys($wpdb->prefix . LL_mailer::table_subscribers) . ' ADD ' . LL_mailer::escape_keys($attr) . ' TEXT NULL DEFAULT NULL;';
    // LL_mailer::message($sql);
    return $wpdb->query($sql);
  }
  static function db_subscribers_rename_attribute($attr, $new_attr) {
    global $wpdb;
    $sql = 'ALTER TABLE ' . LL_mailer::escape_keys($wpdb->prefix . LL_mailer::table_subscribers) . ' CHANGE ' . LL_mailer::escape_keys($attr) . ' ' . LL_mailer::escape_keys($new_attr) . ' TEXT;';
    // LL_mailer::message($sql);
    return $wpdb->query($sql);
  }
  static function db_subscribers_delete_attribute($attr) {
    global $wpdb;
    $sql = 'ALTER TABLE ' . LL_mailer::escape_keys($wpdb->prefix . LL_mailer::table_subscribers) . ' DROP ' . LL_mailer::escape_keys($attr) . ';';
    // LL_mailer::message($sql);
    return $wpdb->query($sql);
  }
  
  

  static function activate()
  {
    global $wpdb;
    $r = array();

    $r[] = LL_mailer::table_templates . ' : ' . $wpdb->query('
      CREATE TABLE ' . LL_mailer::escape_keys($wpdb->prefix . LL_mailer::table_templates) . ' (
        `slug` varchar(100) NOT NULL,
        `body_html` text,
        `body_text` text,
        `last_modified` datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (`slug`)
      ) ' . $wpdb->get_charset_collate() . ';');

    $r[] = LL_mailer::table_messages . ' : ' . $wpdb->query('
      CREATE TABLE ' . LL_mailer::escape_keys($wpdb->prefix . LL_mailer::table_messages) . ' (
        `slug` varchar(100) NOT NULL,
        `subject` tinytext,
        `template_slug` varchar(100),
        `body_html` text,
        `body_text` text,
        `last_modified` datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (`slug`),
        FOREIGN KEY (`template_slug`) REFERENCES ' . LL_mailer::escape_keys($wpdb->prefix . LL_mailer::table_templates) . ' (`slug`) ON DELETE RESTRICT ON UPDATE CASCADE
      ) ' . $wpdb->get_charset_collate() . ';');

    $r[] = LL_mailer::table_subscribers . ' : ' . $wpdb->query('
      CREATE TABLE ' . LL_mailer::escape_keys($wpdb->prefix . LL_mailer::table_subscribers) . ' (
        `' . LL_mailer::subscriber_attribute_mail . '` varchar(100) NOT NULL,
        `' . LL_mailer::subscriber_attribute_name . '` TEXT NULL DEFAULT NULL,
        `subscribed_at` datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (`' . LL_mailer::subscriber_attribute_mail . '`)
      ) ' . $wpdb->get_charset_collate() . ';');
    
    LL_mailer::message('Datenbank eingerichtet.<br /><p>- ' . implode('</p><p>- ', $r) . '</p>');
    
    
    add_option(LL_mailer::option_subscriber_attributes, array(
      LL_mailer::subscriber_attribute_mail => 'Deine E-Mail Adresse',
      LL_mailer::subscriber_attribute_name => 'Dein Name'));
    
    LL_mailer::message('Optionen initialisiert.');
    
     
    // register_uninstall_hook(__FILE__, LL_mailer::_('uninstall'));
  }
  
  static function uninstall()
  {
    global $wpdb;
    $wpdb->query('DROP TABLE IF EXISTS ' . LL_mailer::escape_keys($wpdb->prefix . LL_mailer::table_subscribers) . ';');
    $wpdb->query('DROP TABLE IF EXISTS ' . LL_mailer::escape_keys($wpdb->prefix . LL_mailer::table_messages) . ';');
    $wpdb->query('DROP TABLE IF EXISTS ' . LL_mailer::escape_keys($wpdb->prefix . LL_mailer::table_templates) . ';');
    
    delete_option(LL_mailer::option_msg);
    delete_option(LL_mailer::option_sender_name);
    delete_option(LL_mailer::option_sender_mail);
    delete_option(LL_mailer::option_subscriber_attributes);
  }
  
  
  
  static function json_get($request)
  {
    if (isset($request['template'])) {
      return LL_mailer::db_get_template_by_slug($request['template']);
    }
  }
  
  static function testmail($request)
  {
    if (isset($request['send'])) {
      
      if (isset($request['to'])) {
        $to = LL_mailer::db_get_subscriber_by_mail($request['to']);
        if (is_null($to)) return 'Empfänger nicht gefunden.';
      }
      else return 'Kein Empfänger angegeben.';
      
      if (isset($request['msg'])) {
        $msg = LL_mailer::db_get_message_by_slug($request['msg']);
        if (is_null($msg)) return 'Nachricht nicht gefunden.';
        $body = $msg['body_html'];
        $body_text = $msg['body_text'];
        
        if (!is_null($msg['template_slug'])) {
          $template = LL_mailer::db_get_template_by_slug($msg['template_slug']);
          $body = str_replace(LL_mailer::token_template_content, $body, $template['body_html']);
          $body_text = str_replace(LL_mailer::token_template_content, $body_text, $template['body_text']);
        }
      }
      else return 'Keine Nachricht angegeben.';
      
      
      require LL_mailer::pluginPath() . 'cssin/src/CSSIN.php';
      $cssin = new FM\CSSIN();
      $body = $cssin->inlineCSS('http://linda-liest.de/', $body);
      
      
      require LL_mailer::pluginPath() . 'phpmailer/Exception.php';
      require LL_mailer::pluginPath() . 'phpmailer/PHPMailer.php';
      require LL_mailer::pluginPath() . 'phpmailer/SMTP.php';
      
      $mail = new PHPMailer\PHPMailer\PHPMailer(true /* enable exceptions */);
      try {
        $mail->isSendmail();
        $mail->setFrom(get_option(LL_mailer::option_sender_mail), get_option(LL_mailer::option_sender_name));
        $mail->addAddress($to[LL_mailer::subscriber_attribute_mail], $to[LL_mailer::subscriber_attribute_name]);

        // $mail->addEmbeddedImage('img/2u_cs_mini.jpg', 'logo_2u');

        $mail->isHTML(true);
        $mail->Subject = utf8_decode($msg['subject']);
        $mail->Body = utf8_decode($body);
        $mail->AltBody = utf8_decode($body_text);

        $success = $mail->send();
        return 'Nachricht gesendet.';
        
      } catch (PHPMailer\PHPMailer\Exception $e) {
        return 'Nachricht nicht gesendet. Fehler: ' . $mail->ErrorInfo;
      }
    }
    return null;
  }
  
  static function post_status_transition($new_status, $old_status, $post)
  {
    if ($new_status == 'publish' && $old_status != 'publish') {
      LL_mailer::message('Jetzt würde die E-Mail an die Abonnenten rausgehen :)');
    }
  }








  static function admin_menu()
  {
    $required_capability = 'administrator';
    add_menu_page(LL_mailer::_,                      LL_mailer::_,                  $required_capability, LL_mailer::admin_page_settings,    LL_mailer::_('admin_page_settings'), plugins_url('/icon.png', __FILE__));
    add_submenu_page(LL_mailer::admin_page_settings, LL_mailer::_, 'Einstellungen', $required_capability, LL_mailer::admin_page_settings,    LL_mailer::_('admin_page_settings'));
    add_submenu_page(LL_mailer::admin_page_settings, LL_mailer::_, 'Vorlagen',      $required_capability, LL_mailer::admin_page_templates,   LL_mailer::_('admin_page_templates'));
    add_submenu_page(LL_mailer::admin_page_settings, LL_mailer::_, 'Nachrichten',   $required_capability, LL_mailer::admin_page_messages,    LL_mailer::_('admin_page_messages'));
    add_submenu_page(LL_mailer::admin_page_settings, LL_mailer::_, 'Abonnenten',    $required_capability, LL_mailer::admin_page_subscribers, LL_mailer::_('admin_page_subscribers'));

    add_action('admin_init', LL_mailer::_('admin_page_settings_general_action'));
  }

  
  
  static function admin_page_settings()
  {
?>
    <div class="wrap">
      <h1><?=__('Allgemeine Einstellungen', 'LL_mailer')?></h1>

      <form method="post" action="options.php">
        <?php settings_fields(LL_mailer::_ . '_general'); ?>
        <table class="form-table">
          <tr valign="top">
            <th scope="row"><?=__('Absender', 'LL_mailer')?></th>
            <td>
              <input type="text" name="<?=LL_mailer::option_sender_name?>" value="<?=esc_attr(get_option(LL_mailer::option_sender_name))?>" placeholder="Name" class="regular-text"/>
              <input type="text" name="<?=LL_mailer::option_sender_mail?>" value="<?=esc_attr(get_option(LL_mailer::option_sender_mail))?>" placeholder="E-Mail" class="regular-text" />
            </td>
          </tr>
        </table>
        <?php submit_button(); ?>
      </form>
      <hr />
      <table class="form-table">
        <tr valign="top">
          <th scope="row"><?=__('Abonnenten-Attribute', 'LL_mailer')?></th>
          <td>
<?php
            $attributes = LL_mailer::get_option_array(LL_mailer::option_subscriber_attributes);
            $attribute_groups = array(
              'predefined' => array(
                LL_mailer::subscriber_attribute_mail => $attributes[LL_mailer::subscriber_attribute_mail],
                LL_mailer::subscriber_attribute_name => $attributes[LL_mailer::subscriber_attribute_name]),
              'dynamic' => array_filter($attributes, function($key) { return !LL_mailer::is_predefined_subscriber_attribute($key); }, ARRAY_FILTER_USE_KEY));
            foreach ($attribute_groups as $group => $attrs) {
              foreach ($attrs as $attr => $attr_label) {
?>
                <form method="post" action="admin-post.php" style="display: inline;">
                  <input type="hidden" name="action" value="<?=LL_mailer::_?>_settings_action" />
                  <?php wp_nonce_field(LL_mailer::_ . '_subscriber_attribute_edit'); ?>
                  <input type="hidden" name="attribute" value="<?=$attr?>" />
                  <input type="text" name="new_attribute_label" value="<?=$attr_label?>" class="regular-text" />
                  <?php submit_button(__('Speichern', 'LL_mailer'), '', 'submit', false, array('style' => 'vertical-align: baseline;')); ?>
                </form>
<?php
                if ($group == 'dynamic') {
?>
                  &nbsp;
                  <form method="post" action="admin-post.php" style="display: inline;">
                    <input type="hidden" name="action" value="<?=LL_mailer::_?>_settings_action" />
                    <?php wp_nonce_field(LL_mailer::_ . '_subscriber_attribute_delete'); ?>
                    <input type="hidden" name="attribute" value="<?=$attr?>" />
                    <?php submit_button(__('Löschen', 'LL_mailer'), '', 'submit', false, array('style' => 'vertical-align: baseline;')); ?>
                  </form>
<?php
                }
?>
                <br />
<?php
              }
            }
?>
            <form method="post" action="admin-post.php" style="display: inline;">
              <input type="hidden" name="action" value="<?=LL_mailer::_?>_settings_action" />
              <?php wp_nonce_field(LL_mailer::_ . '_subscriber_attribute_add'); ?>
              <input type="text" name="attribute" placeholder="<?=__('Neues Attribut', 'LL_mailer')?>" class="regular-text" />
              <?php submit_button(__('Hinzufügen', 'LL_mailer'), '', 'submit', false, array('style' => 'vertical-align: baseline;')); ?>
            </form>
          </td>
        </tr>
      </table>
    </div>
<?php
  }

  static function admin_page_settings_general_action()
  {
    // Save changed settings via WordPress
    register_setting(LL_mailer::_ . '_general', LL_mailer::option_sender_name);
    register_setting(LL_mailer::_ . '_general', LL_mailer::option_sender_mail);
  }
  
  static function admin_page_settings_action()
  {
    if (!empty($_POST) && isset($_POST['_wpnonce'])) {
      $attribute = trim($_POST['attribute']);
      if (!empty($attribute)) {
        if (wp_verify_nonce($_POST['_wpnonce'], LL_mailer::_ . '_subscriber_attribute_add')) {
          $attribute_label = $attribute;
          $attribute = sanitize_title($attribute);
          if (!empty($attribute)) {
            $attributes = LL_mailer::get_option_array(LL_mailer::option_subscriber_attributes);
            if (in_array($attribute, array_keys($attributes))) {
              LL_mailer::message(sprintf(__('Ein Abonnenten-Attribut <b>%s</b> existiert bereits.', 'LL_mailer'), $attribute_label));
              wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_settings);
              exit;
            }
            
            $attributes[$attribute] = $attribute_label;
            update_option(LL_mailer::option_subscriber_attributes, $attributes);
            $r = LL_mailer::db_subscribers_add_attribute($attribute);
            
            LL_mailer::message(sprintf(__('Neues Abonnenten-Attribut <b>%s</b> hinzugefügt.', 'LL_mailer'), $attribute_label));
          }
        }
        else if (wp_verify_nonce($_POST['_wpnonce'], LL_mailer::_ . '_subscriber_attribute_edit')) {
          $new_attribute_label = trim($_POST['new_attribute_label']);
          $new_attribute = sanitize_title($new_attribute_label);
          if (!empty($new_attribute_label) && !empty($new_attribute)) {
            $attributes = LL_mailer::get_option_array(LL_mailer::option_subscriber_attributes);
            if ($new_attribute != $attribute && in_array($new_attribute, array_keys($attributes))) {
              LL_mailer::message(sprintf(__('Ein Abonnenten-Attribut <b>%s</b> existiert bereits.', 'LL_mailer'), $new_attribute_label));
              wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_settings);
              exit;
            }
            
            $attribute_label = $attributes[$attribute];
            if (LL_mailer::is_predefined_subscriber_attribute($attribute)) {
              $attributes[$attribute] = $new_attribute_label;
            } else {
              unset($attributes[$attribute]);
              $attributes[$new_attribute] = $new_attribute_label;
              LL_mailer::db_subscribers_rename_attribute($attribute, $new_attribute);
            }
            update_option(LL_mailer::option_subscriber_attributes, $attributes);
            
            LL_mailer::message(sprintf(__('Abonnenten-Attribut <b>%s</b> in <b>%s</b> umbenannt.', 'LL_mailer'), $attribute_label, $new_attribute_label));
          }
        }
        else if (wp_verify_nonce($_POST['_wpnonce'], LL_mailer::_ . '_subscriber_attribute_delete')) {
          $attributes = LL_mailer::get_option_array(LL_mailer::option_subscriber_attributes);
          
          $attribute_label = $attributes[$attribute];
          unset($attributes[$attribute]);
          update_option(LL_mailer::option_subscriber_attributes, $attributes);
          LL_mailer::db_subscribers_delete_attribute($attribute);
          
          LL_mailer::message(sprintf(__('Abonnenten-Attribut <b>%s</b> gelöscht.', 'LL_mailer'), $attribute_label));
        }
      }
    }
    wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_settings);
    exit;
  }

  
  
  static function admin_page_templates()
  {
    $sub_page = 'list';
    if (isset($_GET['edit'])) $sub_page = 'edit';
?>
    <div class="wrap">
<?php
    switch ($sub_page) {
      case 'list':
      {
?>
        <h1><?=__('Neue Vorlage', 'LL_mailer')?></h1>

        <form method="post" action="admin-post.php">
          <input type="hidden" name="action" value="<?=LL_mailer::_?>_template_action" />
          <?php wp_nonce_field(LL_mailer::_ . '_template_add'); ?>
          <table class="form-table">
            <tr valign="top">
            <th scope="row"><?=__('Slug für neue Vorlage', 'LL_mailer')?></th>
            <td>
              <input type="text" name="template_slug" placeholder="<?=__('meine-vorlage', 'LL_mailer')?>" class="regular-text" /> &nbsp;
              <?php submit_button(__('Neue Vorlage anlegen', 'LL_mailer'), 'primary', '', false); ?>
            </td>
            </tr>
          </table>
        </form>
        
        <hr />
        
        <h1><?=__('Gespeicherte Vorlagen', 'LL_mailer')?></h1>
        
        <p>
<?php
          $templates = LL_mailer::db_get_templates(array('slug', 'last_modified'));
          $edit_url = LL_mailer::admin_url() . LL_mailer::admin_page_template_edit;
          foreach ($templates as $template) {
?>
            <?=LL_mailer::list_item?> <a href="<?=$edit_url . $template['slug']?>"><b><?=$template['slug']?></b></a> &nbsp; <span style="color: gray;">( <?=__('zuletzt bearbeitet: ', 'LL_mailer') . $template['last_modified']?> )</span><br />
<?php
          }
?>
        </p>
<?php
      } break;
      
      case 'edit':
      {
        $template_slug = $_GET['edit'];
        $template = LL_mailer::db_get_template_by_slug($template_slug);
        if (empty($template)) {
          LL_mailer::message(sprintf(__('Es existiert keine Vorlage <b>%s</b>.', 'LL_mailer'), $template_slug));
          wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_templates);
          exit;
        }
?>
        <h1><?=__('Vorlagen', 'LL_mailer')?> &gt; <?=$template_slug?></h1>

        <form method="post" action="admin-post.php">
          <input type="hidden" name="action" value="<?=LL_mailer::_?>_template_action" />
          <?php wp_nonce_field(LL_mailer::_ . '_template_edit'); ?>
          <input type="hidden" name="template_slug" value="<?=$template_slug?>" />
          <table class="form-table">
            <tr valign="top">
              <th scope="row"><?=__('Layout (HTML)', 'LL_mailer')?></th>
              <td>
                <textarea name="body_html" style="width: 100%;" rows=10><?=$template['body_html']?></textarea>
              </td>
            </tr>
            <tr valign="top">
              <th scope="row"><?=__('Vorschau (HTML)', 'LL_mailer')?></th>
              <td>
                <iframe id="body_html_preview" style="width: 100%; height: 200px; resize: vertical; border: 1px solid #ddd; background: white;" srcdoc="<?=htmlspecialchars(
                    LL_mailer::html_prefix . $template['body_html'] . LL_mailer::html_suffix
                  )?>">
                </iframe>
              </td>
            </tr>
            <tr valign="top">
              <th scope="row"><?=__('Layout (Text)', 'LL_mailer')?></th>
              <td>
                <textarea name="body_text" style="width: 100%;" rows=10><?=$template['body_text']?></textarea>
              </td>
            </tr>
            <tr>
              <td></td>
              <td>
                <i><?=__('Im Layout muss <b>[CONTENT]</b> an der Stelle verwendet werden, an der später die eigentliche Nachricht eingefügt werden soll.', 'LL_mailer')?></i>
              </td>
            </tr>
          </table>
          <?php submit_button(__('Vorlage speichern', 'LL_mailer')); ?>
        </form>
        <script>
          var preview = document.querySelector('#body_html_preview');
          jQuery('[name="body_html"]').on('input', function() {
            preview.contentWindow.document.body.innerHTML = this.value;
          });
        </script>
        
        <hr />
        
        <h1><?=__('Löschen', 'LL_mailer')?></h1>
        
<?php
        $using_messages = LL_mailer::db_get_messages_by_template($template_slug);
        $message_url = LL_mailer::admin_url() . LL_mailer::admin_page_message_edit;
        if (!empty($using_messages)) {
?>
          <i><?=__('Diese Vorlage kann nicht gelöscht werden, da sie von folgenden Nachrichten verwendet wird:', 'LL_mailer')?></i>
          <ul>
            <?=implode('<br />', array_map(function($v) use ($message_url) { return LL_mailer::list_item . ' <a href="' . $message_url . $v . '">' . $v . '</a>'; }, $using_messages))?>
          </ul>
<?php
        } else {
?>
          <form method="post" action="admin-post.php">
            <input type="hidden" name="action" value="<?=LL_mailer::_?>_template_action" />
            <?php wp_nonce_field(LL_mailer::_ . '_template_delete'); ?>
            <input type="hidden" name="template_slug" value="<?=$template_slug?>" />
            <?php submit_button(__('Vorlage löschen', 'LL_mailer'), ''); ?>
          </form>
<?php
        }
      } break;
    }
?>
    </div>
<?php
  }
  
  static function admin_page_template_action()
  {
    if (!empty($_POST) && isset($_POST['_wpnonce'])) {
      $template_slug = $_POST['template_slug'];
      if (empty(!$template_slug)) {
        if (wp_verify_nonce($_POST['_wpnonce'], LL_mailer::_ . '_template_add')) {
          $template_slug = sanitize_title($template_slug);
          if (empty($template_slug)) {
            LL_mailer::message(sprintf(__('<b>%s</b> kann nicht als Vorlagen-Slug verwendet werden.', 'LL_mailer'), $_POST['template_slug']));
            wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_templates);
            exit;
          }
          
          $existing_template = LL_mailer::db_get_template_by_slug($template_slug);
          if (!empty($existing_template)) {
            LL_mailer::message(sprintf(__('Die Vorlage <b>%s</b> existiert bereits.', 'LL_mailer'), $template_slug));
            wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_templates);
            exit;
          }
          
          LL_mailer::db_save_template(array('slug' => $template_slug));
          
          LL_mailer::message(sprintf(__('Neue Vorlage <b>%s</b> angelegt.', 'LL_mailer'), $template_slug));
          wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_template_edit . $template_slug);
          exit;
        }
        
        else if (wp_verify_nonce($_POST['_wpnonce'], LL_mailer::_ . '_template_edit')) {
          $template = array(
            'slug' => $template_slug,
            'body_html' => $_POST['body_html'] ?: null,
            'body_text' => strip_tags($_POST['body_text']) ?: null);
          LL_mailer::db_save_template($template);
          
          LL_mailer::message(sprintf(__('Vorlage <b>%s</b> gespeichert.', 'LL_mailer'), $template_slug));
          wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_template_edit . $template_slug);
          exit;
        }
        
        else if (wp_verify_nonce($_POST['_wpnonce'], LL_mailer::_ . '_template_delete')) {
          LL_mailer::db_delete_template($template_slug);
          
          LL_mailer::message(sprintf(__('Vorlage <b>%s</b> gelöscht.', 'LL_mailer'), $template_slug));
          wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_templates);
          exit;
        }
      }
    }
    wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_templates);
    exit;
  }

  
  
  static function admin_page_messages()
  {
    $sub_page = 'list';
    if (isset($_GET['edit'])) $sub_page = 'edit';
?>
    <div class="wrap">
<?php
    switch ($sub_page) {
      case 'list':
      {
?>
        <h1><?=__('Neue Nachricht', 'LL_mailer')?></h1>

        <form method="post" action="admin-post.php">
          <input type="hidden" name="action" value="<?=LL_mailer::_?>_message_action" />
          <?php wp_nonce_field(LL_mailer::_ . '_message_add'); ?>
          <table class="form-table">
            <tr valign="top">
            <th scope="row"><?=__('Slug für neue Nachricht', 'LL_mailer')?></th>
            <td>
              <input type="text" name="message_slug" placeholder="<?=__('meine-nachricht', 'LL_mailer')?>" class="regular-text" /> &nbsp;
              <?php submit_button(__('Neue Nachricht anlegen', 'LL_mailer'), 'primary', '', false); ?>
            </td>
            </tr>
          </table>
        </form>
        
        <hr />
        
        <h1><?=__('Gespeicherte Nachrichten', 'LL_mailer')?></h1>
        
        <p>
<?php
          $messages = LL_mailer::db_get_messages(array('slug', 'subject', 'template_slug', 'last_modified'));
          $edit_url = LL_mailer::admin_url() . LL_mailer::admin_page_message_edit;
          foreach ($messages as $message) {
?>
            <?=LL_mailer::list_item?> <a href="<?=$edit_url . $message['slug']?>"><b><?=$message['slug']?></b></a> &nbsp; <?=$message['subject'] ?: '<i>(kein Betreff)</i>'?> &nbsp; <span style="color: gray;">( <?=$message['template_slug']?> &mdash; <?=__('zuletzt bearbeitet: ', 'LL_mailer') . $message['last_modified']?> )</span><br />
<?php
          }
?>
        </p>
<?php
      } break;
      
      case 'edit':
      {
        $message_slug = $_GET['edit'];
        $message = LL_mailer::db_get_message_by_slug($message_slug);
        if (empty($message)) {
          LL_mailer::message(sprintf(__('Es existiert keine Nachricht <b>%s</b>.', 'LL_mailer'), $message_slug));
          wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_messages);
          exit;
        }
        $preview_body_html = $message['body_html'];
        $preview_body_text = $message['body_text'];
        
        $templates = LL_mailer::db_get_templates('slug');
        
        $template_body_html = '';
        $template_body_text = '';
        if (!is_null($message['template_slug'])) {
          $template = LL_mailer::db_get_template_by_slug($message['template_slug']);
          $template_body_html = $template['body_html'];
          $template_body_text = $template['body_text'];
          
          $preview_body_html = str_replace(LL_mailer::token_template_content, $message['body_html'], $template_body_html);
          $preview_body_text = str_replace(LL_mailer::token_template_content, $message['body_text'], $template_body_text);
        }
?>
        <h1><?=__('Nachrichten', 'LL_mailer')?> &gt; <?=$message_slug?></h1>

        <form method="post" action="admin-post.php">
          <input type="hidden" name="action" value="<?=LL_mailer::_?>_message_action" />
          <?php wp_nonce_field(LL_mailer::_ . '_message_edit'); ?>
          <input type="hidden" name="message_slug" value="<?=$message_slug?>" />
          <table class="form-table">
            <tr valign="top">
              <th scope="row"><?=__('Betreff', 'LL_mailer')?></th>
              <td>
                <input type="text" name="subject" value="<?=esc_attr($message['subject'])?>" placeholder="Betreff" style="width: 100%;" />
              </td>
            </tr>
            <tr valign="top">
              <th scope="row"><?=__('Vorlage', 'LL_mailer')?></th>
              <td>
                <select name="template_slug" style="min-width: 50%; max-width: 100%;">
                  <option value="">--</option>
<?php
                  foreach ($templates as $template_slug) {
?>
                    <option value="<?=esc_attr($template_slug['slug'])?>" <?=$template_slug['slug'] == $message['template_slug'] ? 'selected' : ''?>><?=$template_slug['slug']?></option>
<?php
                  }
?>
                </select> &nbsp;
                <a id="LL_mailer_template_edit_link" href="<?=LL_mailer::admin_url() . LL_mailer::admin_page_template_edit . $message['template_slug']?>">(<?=__('Vorlage bearbeiten', 'LL_mailer')?>)</a>
              </td>
            </tr>
            <tr valign="top">
              <th scope="row"><?=__('Inhalt (HTML)', 'LL_mailer')?></th>
              <td>
                <textarea name="body_html" style="width: 100%;" rows=10><?=$message['body_html']?></textarea>
              </td>
            </tr>
            <tr valign="top">
              <th scope="row"><?=__('Vorschau (HTML)', 'LL_mailer')?></th>
              <td>
                <iframe id="body_html_preview" style="width: 100%; height: 200px; resize: vertical; border: 1px solid #ddd; background: white;" srcdoc="<?=htmlspecialchars(
                    LL_mailer::html_prefix . $preview_body_html . LL_mailer::html_suffix
                  )?>">
                </iframe>
                <div id="LL_mailer_template_body_html" style="display: none;"><?=$template_body_html?></div>
              </td>
            </tr>
            <tr valign="top">
              <th scope="row"><?=__('Inhalt (Text)', 'LL_mailer')?></th>
              <td>
                <textarea name="body_text" style="width: 100%;" rows=10><?=$message['body_text']?></textarea>
              </td>
            </tr>
            <tr valign="top">
              <th scope="row"><?=__('Vorschau (Text)', 'LL_mailer')?></th>
              <td>
                <textarea disabled id="body_text_preview" style="width: 100%; color:black; background: white;" rows=10><?=$preview_body_text?></textarea>
                <div id="LL_mailer_template_body_text" style="display: none;"><?=$template_body_text?></div>
              </td>
            </tr>
            <tr>
              <td></td>
              <td>
                <i><?=__('Im Inhalt (HTML und Text) können folgende Platzhalter verwendet werden:<br>' .
                  LL_mailer::list_item . ' <code>[POST "{WP_POST_ATTRIBUTE}"]</code> für Wordpress WP_Post Attribute<br />' .
                  LL_mailer::list_item . ' <code>[META "{POST_META}"]</code> für Post-Metadaten, zB. von Plugins', 'LL_mailer')?></i>
              </td>
            </tr>
          </table>
          <?php submit_button(__('Nachricht speichern', 'LL_mailer')); ?>
        </form>
        <script>
          var template_select = document.querySelector('[name="template_slug"]');
          var template_edit_link = document.querySelector('#LL_mailer_template_edit_link');
          var textarea_html = document.querySelector('[name="body_html"]');
          var textarea_text = document.querySelector('[name="body_text"]');
          var template_body_html_div = document.querySelector('#LL_mailer_template_body_html');
          var template_body_text_div = document.querySelector('#LL_mailer_template_body_text');
          var preview_html = document.querySelector('#body_html_preview');
          var preview_text = document.querySelector('#body_text_preview');
          var show_hide = [template_select, textarea_html, textarea_text];
          function updatePreviewHtml(preview, template_body_div, textarea) {
            preview.contentWindow.document.body.innerHTML = template_body_div.innerHTML.replace('<?=LL_mailer::token_template_content?>', textarea.value);
          }
          function updatePreviewText(preview, template_body_div, textarea) {
            preview.value = template_body_div.innerHTML.replace('<?=LL_mailer::token_template_content?>', textarea.value);
          }
          jQuery(textarea_html).on('input', function() { updatePreviewHtml(preview_html, template_body_html_div, textarea_html); });
          jQuery(textarea_text).on('input', function() { updatePreviewText(preview_text, template_body_text_div, textarea_text); });
          jQuery(template_select).on('input', function() {
            if (template_select.value === '') {
              template_edit_link.href = '';
              template_edit_link.style.display = 'none';
              template_body_html_div.innerHTML = '<?=LL_mailer::token_template_content?>';
              template_body_text_div.innerHTML = '<?=LL_mailer::token_template_content?>';
              updatePreviewHtml(preview_html, template_body_html_div, textarea_html);
              updatePreviewText(preview_text, template_body_text_div, textarea_text);
            }
            else {
              for (var i = 0; i < show_hide.length; i++)
                show_hide[i].disabled = true;
              
              jQuery.getJSON('<?=LL_mailer::json_url()?>get?template=' + template_select.value, function(new_template) {
                template_edit_link.href = '<?=LL_mailer::admin_url() . LL_mailer::admin_page_template_edit?>' + new_template.slug;
                template_edit_link.style.display = 'inline';
                template_body_html_div.innerHTML = new_template.body_html;
                template_body_text_div.innerHTML = new_template.body_text;
                updatePreviewHtml(preview_html, template_body_html_div, textarea_html);
                updatePreviewText(preview_text, template_body_text_div, textarea_text);
                
                for (var i = 0; i < show_hide.length; i++)
                  show_hide[i].disabled = false;
              });
            }
          });
        </script>
        
        <hr />
        
        <h1><?=__('Löschen', 'LL_mailer')?></h1>
        
        <form method="post" action="admin-post.php">
          <input type="hidden" name="action" value="<?=LL_mailer::_?>_message_action" />
          <?php wp_nonce_field(LL_mailer::_ . '_message_delete'); ?>
          <input type="hidden" name="message_slug" value="<?=$message_slug?>" />
          <?php submit_button(__('Nachricht löschen', 'LL_mailer'), ''); ?>
        </form>
        
        <hr />
        
        <h1><?=__('Testnachricht', 'LL_mailer')?></h1>
        
<?php
        $subscribers = LL_mailer::db_get_subscribers(array(LL_mailer::subscriber_attribute_mail, LL_mailer::subscriber_attribute_name));
        if (empty($subscribers)) {
?>
          <i><?=__('Es wird mindestens ein Abonnent für die Empfänger-Auswahl benötigt.', 'LL_mailer')?></i>
<?php
        } else {
?>
          <form id="LL_mailer_testmail" method="post" action="<?=LL_mailer::json_url() . 'testmail'?>">
            <input type="hidden" name="msg" value="<?=$message_slug?>" />
            <select id="to" name="to">
<?php
              foreach ($subscribers as $subscriber) {
?>
                <option value="<?=$subscriber[LL_mailer::subscriber_attribute_mail]?>"><?=$subscriber[LL_mailer::subscriber_attribute_name] . ' / ' . $subscriber[LL_mailer::subscriber_attribute_mail]?></option>
<?php
              }
?>
            </select>
            <?php submit_button(__('senden', 'LL_mailer'), '', '', false); ?>
            <i id="response"></i>
          </form>
          <script>
            var to_select = document.querySelector('#LL_mailer_testmail #to');
            var response_tag = document.querySelector('#LL_mailer_testmail #response');
            jQuery('#LL_mailer_testmail').submit(function(e) {
              var url = '<?=LL_mailer::json_url() . 'testmail?msg=' . $message_slug . '&to='?>';
              jQuery.getJSON(url, function(response) {
                response_tag.innerHTML = response;
              });
              e.preventDefault();
            });
          </script>
<?php
        }
      } break;
    }
?>
    </div>
<?php
  }
  
  static function admin_page_message_action()
  {
    if (!empty($_POST) && isset($_POST['_wpnonce'])) {
      $message_slug = $_POST['message_slug'];
      if (!empty($message_slug)) {
        if (wp_verify_nonce($_POST['_wpnonce'], LL_mailer::_ . '_message_add')) {
          $message_slug = sanitize_title($message_slug);
          if (empty($message_slug)) {
            LL_mailer::message(sprintf(__('<b>%s</b> kann nicht als Nachrichten-Slug verwendet werden.', 'LL_mailer'), $_POST['message_slug']));
            wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_messages);
            exit;
          }
          
          $existing_message = LL_mailer::db_get_message_by_slug($message_slug);
          if (!empty($existing_message)) {
            LL_mailer::message(sprintf(__('Die Nachricht <b>%s</b> existiert bereits.', 'LL_mailer'), $message_slug));
            wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_messages);
            exit;
          }
          
          LL_mailer::db_save_message(array('slug' => $message_slug));
          
          LL_mailer::message(sprintf(__('Neue Nachricht <b>%s</b> angelegt.', 'LL_mailer'), $message_slug));
          wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_message_edit . $message_slug);
          exit;
        }
        
        else if (wp_verify_nonce($_POST['_wpnonce'], LL_mailer::_ . '_message_edit')) {
          $message = array(
            'slug' => $message_slug,
            'subject' => $_POST['subject'] ?: null,
            'template_slug' => $_POST['template_slug'] ?: null,
            'body_html' => $_POST['body_html'] ?: null,
            'body_text' => strip_tags($_POST['body_text']) ?: null);
          LL_mailer::db_save_message($message);
          
          LL_mailer::message(sprintf(__('Nachricht <b>%s</b> gespeichert.', 'LL_mailer'), $message_slug));
          wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_message_edit . $message_slug);
          exit;
        }
        
        else if (wp_verify_nonce($_POST['_wpnonce'], LL_mailer::_ . '_message_delete')) {
          LL_mailer::db_delete_message($message_slug);
          
          LL_mailer::message(sprintf(__('Nachricht <b>%s</b> gelöscht.', 'LL_mailer'), $message_slug));
          wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_messages);
          exit;
        }
      }
    }
    wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_messages);
    exit;
  }

  
  
  static function admin_page_subscribers()
  {
    $sub_page = 'list';
    if (isset($_GET['edit'])) $sub_page = 'edit';
?>
    <div class="wrap">
<?php
    switch ($sub_page) {
      case 'list':
      {
?>
        <h1><?=__('Neuer Abonnent', 'LL_mailer')?></h1>

        <form method="post" action="admin-post.php">
          <input type="hidden" name="action" value="<?=LL_mailer::_?>_subscriber_action" />
          <?php wp_nonce_field(LL_mailer::_ . '_subscriber_add'); ?>
          <table class="form-table">
            <tr valign="top">
            <th scope="row"><?=__('E-Mail des neuen Abonnenten', 'LL_mailer')?></th>
            <td>
              <input type="email" name="subscriber_mail" placeholder="<?=__('name@email.de', 'LL_mailer')?>" class="regular-text" /> &nbsp;
              <?php submit_button(__('Neuen Abonnenten anlegen', 'LL_mailer'), 'primary', '', false); ?>
            </td>
            </tr>
          </table>
        </form>
        
        <hr />
        
        <h1><?=__('Gespeicherte Abonnenten', 'LL_mailer')?></h1>
        
        <p>
<?php
          $subscribers = LL_mailer::db_get_subscribers('*');
          $edit_url = LL_mailer::admin_url() . LL_mailer::admin_page_subscriber_edit;
          foreach ($subscribers as $subscriber) {
?>
            <?=LL_mailer::list_item?> <a href="<?=$edit_url . $subscriber[LL_mailer::subscriber_attribute_mail]?>"><b><?=($subscriber[LL_mailer::subscriber_attribute_name] ?? '</b><i>(' . __('kein Name') . ')</i><b>') . ' / ' . $subscriber[LL_mailer::subscriber_attribute_mail]?></b></a> &nbsp; <span style="color: gray;">( <?=__('abonniert am: ', 'LL_mailer') . $subscriber['subscribed_at']?> )</span><br />
<?php
          }
?>
        </p>
<?php
      } break;
      
      case 'edit':
      {
        $subscriber_mail = $_GET['edit'];
        $subscriber = LL_mailer::db_get_subscriber_by_mail($subscriber_mail);
        if (empty($subscriber)) {
          LL_mailer::message(sprintf(__('Es existiert kein Abonnent <b>%s</b>.', 'LL_mailer'), $subscriber_mail));
          wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_subscribers);
          exit;
        }
?>
        <h1><?=__('Abonnenten', 'LL_mailer')?> &gt; <?=$subscriber_mail?></h1>

        <form method="post" action="admin-post.php">
          <input type="hidden" name="action" value="<?=LL_mailer::_?>_subscriber_action" />
          <?php wp_nonce_field(LL_mailer::_ . '_subscriber_edit'); ?>
          <input type="hidden" name="subscriber_mail" value="<?=$subscriber_mail?>" />
          <table class="form-table">
<?php
            $attributes = LL_mailer::get_option_array(LL_mailer::option_subscriber_attributes);
            foreach ($attributes as $attr => $attr_label) {
?>
              <tr valign="top">
                <th scope="row"><?=$attr_label?></th>
                <td>
                  <input type="text" name="<?=$attr?>" value="<?=esc_attr($subscriber[$attr])?>" placeholder="<?=$attr_label?>" class="regular-text" />
                </td>
              </tr>
<?php
            }
?>
          </table>
          <?php submit_button(__('Abonnent speichern', 'LL_mailer')); ?>
        </form>
        
        <hr />
        
        <h1><?=__('Löschen', 'LL_mailer')?></h1>
        
        <form method="post" action="admin-post.php">
          <input type="hidden" name="action" value="<?=LL_mailer::_?>_subscriber_action" />
          <?php wp_nonce_field(LL_mailer::_ . '_subscriber_delete'); ?>
          <input type="hidden" name="subscriber_mail" value="<?=$subscriber_mail?>" />
          <?php submit_button(__('Abonnent löschen', 'LL_mailer'), ''); ?>
        </form>
<?php
      } break;
    }
?>
    </div>
<?php
  }
  
  static function admin_page_subscriber_action()
  {
    if (!empty($_POST) && isset($_POST['_wpnonce'])) {
      $subscriber_mail = $_POST['subscriber_mail'];
      if (!empty($subscriber_mail)) {
        if (wp_verify_nonce($_POST['_wpnonce'], LL_mailer::_ . '_subscriber_add')) {
          $subscriber_mail = trim($subscriber_mail);
          if (!filter_var($subscriber_mail, FILTER_VALIDATE_EMAIL)) {
            LL_mailer::message(sprintf(__('Die E-Mail Adresse <b>%s</b> ist ungültig.', 'LL_mailer'), $subscriber_mail));
            wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_subscribers);
            exit;
          }
          
          $existing_subscriber = LL_mailer::db_get_subscriber_by_mail($subscriber_mail);
          if (!empty($existing_subscriber)) {
            LL_mailer::message(sprintf(__('Der Abonnent <b>%s</b> existiert bereits.', 'LL_mailer'), $subscriber_mail));
            wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_subscribers);
            exit;
          }
          
          LL_mailer::db_save_subscriber(array(LL_mailer::subscriber_attribute_mail => $subscriber_mail));
          
          LL_mailer::message(sprintf(__('Neuer Abonnent <b>%s</b> angelegt.', 'LL_mailer'), $subscriber_mail));
          wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_subscriber_edit . $subscriber_mail);
          exit;
        }
        
        else if (wp_verify_nonce($_POST['_wpnonce'], LL_mailer::_ . '_subscriber_edit')) {
          $subscriber = array(
            LL_mailer::subscriber_attribute_mail => $subscriber_mail);
            
          $attributes = LL_mailer::get_option_array(LL_mailer::option_subscriber_attributes);
          foreach ($attributes as $attr => $attr_label) {
            $subscriber[$attr] = $_POST[$attr];
          }
          
          LL_mailer::db_save_subscriber($subscriber);
          
          LL_mailer::message(sprintf(__('Abonnent <b>%s</b> gespeichert.', 'LL_mailer'), $subscriber_mail));
          wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_subscriber_edit . $subscriber_mail);
          exit;
        }
        
        else if (wp_verify_nonce($_POST['_wpnonce'], LL_mailer::_ . '_subscriber_delete')) {
          LL_mailer::db_delete_subscriber($subscriber_mail);
          
          LL_mailer::message(sprintf(__('Abonnent <b>%s</b> gelöscht.', 'LL_mailer'), $subscriber_mail));
          wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_subscribers);
          exit;
        }
      }
    }
    wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_subscribers);
    exit;
  }
  
  
  
  
  
  
  
 

  static function init_hooks_and_filters() {
    
    add_action('admin_menu', LL_mailer::_('admin_menu'));
    add_action('admin_post_' . LL_mailer::_ . '_settings_action', LL_mailer::_('admin_page_settings_action'));
    add_action('admin_post_' . LL_mailer::_ . '_template_action', LL_mailer::_('admin_page_template_action'));
    add_action('admin_post_' . LL_mailer::_ . '_message_action', LL_mailer::_('admin_page_message_action'));
    add_action('admin_post_' . LL_mailer::_ . '_subscriber_action', LL_mailer::_('admin_page_subscriber_action'));
    
    
    
    add_action('admin_notices', LL_mailer::_('admin_notices'));

    register_activation_hook(__FILE__, LL_mailer::_('activate'));
    register_deactivation_hook(__FILE__, LL_mailer::_('uninstall'));

    // add_action('transition_post_status', LL_mailer::_('post_status_transition'), 10, 3);

    add_action('rest_api_init', function ()
    {
      register_rest_route('LL_mailer/v1', '/get', array(
        'methods' => 'GET',
        'callback' => LL_mailer::_('json_get')
      ));
      register_rest_route('LL_mailer/v1', '/testmail', array(
        'methods' => 'GET',
        'callback' => LL_mailer::_('testmail')
      ));
    });
  }
}

LL_mailer::init_hooks_and_filters();

?>