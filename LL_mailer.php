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

if (!defined('ABSPATH')) header('Location: https://linda-liest.de/');



use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require LL_mailer::pluginPath() . 'phpmailer/Exception.php';
require LL_mailer::pluginPath() . 'phpmailer/PHPMailer.php';
require LL_mailer::pluginPath() . 'phpmailer/SMTP.php';



class LL_mailer
{
  const _ = 'LL_mailer';
  
  const option_msg                    = LL_mailer::_ . '_msg';
  const option_sender_name            = LL_mailer::_ . '_sender_name';
  const option_sender_mail            = LL_mailer::_ . '_sender_mail';
  const option_subscriber_attributes  = LL_mailer::_ . '_subscriber_attributes';
  
  const table_templates             = LL_mailer::_ . '_templates';
  const table_messages              = LL_mailer::_ . '_messages';
  const table_subscribers           = LL_mailer::_ . '_subscribers';
  
  const admin_page_settings         = LL_mailer::_ . '_settings';
  const admin_page_templates        = LL_mailer::_ . '_templates';
  const admin_page_template_edit    = LL_mailer::_ . '_templates&edit=';
  const admin_page_messages         = LL_mailer::_ . '_messages';
  const admin_page_message_edit     = LL_mailer::_ . '_messages&edit=';
  const admin_page_subscribers      = LL_mailer::_ . 'subscribers';
  const admin_page_subscriber_edit  = LL_mailer::_ . 'subscribers&edit=';
  
  const token_template_content      = '[CONTENT]';
  
  const list_item = '&ndash; &nbsp;';
  const arrow_up = '&#x2934;';
  const arrow_down = '&#x2935;';
  
  
  
	static function _($member_function) { return array(LL_mailer::_, $member_function); }
  
  static function pluginPath() { return plugin_dir_path(__FILE__); }
  static function admin_url() { return get_admin_url() . 'admin.php?page='; }

  static function get_option_array($option) {
    $val = get_option($option);
    if (empty($val))
      return array();
    return $val;
  } 
  
  
  
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
  static function db_save_subscriber($subscriber) { return LL_mailer::_db_insert_or_update(LL_mailer::table_subscribers, $subscriber, 'mail'); }
  static function db_delete_subscriber($mail) { return LL_mailer::_db_delete(LL_mailer::table_subscribers, array('mail' => $mail)); }
  static function db_get_subscriber_by_mail($mail) { return LL_mailer::_db_select_row(LL_mailer::table_subscribers, '*', array('mail' => $mail)); }
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
      CREATE TABLE ' . $wpdb->prefix . LL_mailer::table_templates . ' (
        slug varchar(100) NOT NULL,
        body_html text,
        body_text text,
        last_modified datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (slug)
      ) ' . $wpdb->get_charset_collate() . ';');

    $r[] = LL_mailer::table_messages . ' : ' . $wpdb->query('
      CREATE TABLE ' . $wpdb->prefix . LL_mailer::table_messages . ' (
        slug varchar(100) NOT NULL,
        subject tinytext,
        template_slug varchar(100),
        body_html text,
        body_text text,
        last_modified datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (slug),
        FOREIGN KEY (template_slug) REFERENCES ' . $wpdb->prefix . LL_mailer::table_templates . ' (slug) ON DELETE RESTRICT ON UPDATE CASCADE
      ) ' . $wpdb->get_charset_collate() . ';');

    $r[] = LL_mailer::table_subscribers . ' : ' . $wpdb->query('
      CREATE TABLE ' . $wpdb->prefix . LL_mailer::table_subscribers . ' (
        mail varchar(100) NOT NULL,
        subscribed_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (mail)
      ) ' . $wpdb->get_charset_collate() . ';');
    
    LL_mailer::message('Datenbank eingerichtet.<br /><p>- ' . implode('</p><p>- ', $r) . '</p>');
    
    
    add_option(LL_mailer::option_sender_name, 'Linda liest');
    add_option(LL_mailer::option_sender_mail, 'mail@linda-liest.de');
    
    LL_mailer::message('Optionen initialisiert.');
    
     
    // register_uninstall_hook(__FILE__, LL_mailer::_('uninstall'));
  }
  
  static function uninstall()
  {
    global $wpdb;
    $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . LL_mailer::table_subscribers . ';');
    $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . LL_mailer::table_messages . ';');
    $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . LL_mailer::table_templates . ';');
    
    delete_option(LL_mailer::option_msg);
    delete_option(LL_mailer::option_sender_name);
    delete_option(LL_mailer::option_sender_mail);
  }
  
  
  
  static function subscribe($request)
  {
    if (isset($request['send'])) {
      
      if (isset($request['receiverName']) && isset($request['receiverMail'])) {
        $to = array(
          'name' => $request['receiverName'],
          'mail' => $request['receiverMail']);
      }
      
      if (isset($to)) {
        $mail = new PHPMailer(true /* enable exceptions */);
        try {
          $mail->isSendmail();
          $mail->setFrom(get_option(LL_mailer::option_sender_mail), get_option(LL_mailer::option_sender_name));
          $mail->addAddress($to['mail'], $to['name']);

          // $mail->addEmbeddedImage('img/2u_cs_mini.jpg', 'logo_2u');

          $mail->isHTML(true);
          $mail->Subject = 'Test-Betreff';
          $mail->Body    = 'This is the HTML message body <b>in bold!</b>';
          $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

          $success = $mail->send();
          return 'Message has been sent';
          
        } catch (Exception $e) {
          return 'Message could not be sent. Mailer Error: ' . $mail->ErrorInfo;
        }
      }
    }
    return null;
  }
  
  

  static function post_status_transition($new_status, $old_status, $post)
  {
    if ($new_status == 'publish' && $old_status != 'publish') {
      
      $mail = new PHPMailer(true);                            // Passing `true` enables exceptions
      try {
        //Server settings
        $mail->SMTPDebug = 2;                                 // Enable verbose debug output
        $mail->isSMTP();                                      // Set mailer to use SMTP
        $mail->Host = 'smtp1.example.com;smtp2.example.com';  // Specify main and backup SMTP servers
        $mail->SMTPAuth = true;                               // Enable SMTP authentication
        $mail->Username = 'user@example.com';                 // SMTP username
        $mail->Password = 'secret';                           // SMTP password
        $mail->SMTPSecure = 'tls';                            // Enable TLS encryption, `ssl` also accepted
        $mail->Port = 587;                                    // TCP port to connect to

        //Recipients
        $mail->setFrom('from@example.com', 'Mailer');
        $mail->addAddress('joe@example.net', 'Joe User');     // Add a recipient
        $mail->addAddress('ellen@example.com');               // Name is optional
        $mail->addReplyTo('info@example.com', 'Information');
        $mail->addCC('cc@example.com');
        $mail->addBCC('bcc@example.com');

        //Attachments
        $mail->addAttachment('/var/tmp/file.tar.gz');         // Add attachments
        $mail->addAttachment('/tmp/image.jpg', 'new.jpg');    // Optional name

        //Content
        $mail->isHTML(true);                                  // Set email format to HTML
        $mail->Subject = 'Here is the subject';
        $mail->Body    = 'This is the HTML message body <b>in bold!</b>';
        $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

        $mail->send();
        echo 'Message has been sent';
        
      } catch (Exception $e) {
        echo 'Message could not be sent. Mailer Error: ' . $mail->ErrorInfo;
      }
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
            foreach ($attributes as $attr => $attr_label) {
?>
              <form method="post" action="admin-post.php" style="display: inline;">
                <input type="hidden" name="action" value="<?=LL_mailer::_?>_settings_action" />
                <?php wp_nonce_field(LL_mailer::_ . '_subscriber_attribute_edit'); ?>
                <input type="hidden" name="attribute" value="<?=$attr?>" />
                <input type="text" name="new_attribute_label" value="<?=$attr_label?>" class="regular-text" />
                <?php submit_button(__('Speichern', 'LL_mailer'), '', 'submit', false, array('style' => 'vertical-align: baseline;')); ?>
              </form>
              &nbsp;
              <form method="post" action="admin-post.php" style="display: inline;">
                <input type="hidden" name="action" value="<?=LL_mailer::_?>_settings_action" />
                <?php wp_nonce_field(LL_mailer::_ . '_subscriber_attribute_delete'); ?>
                <input type="hidden" name="attribute" value="<?=$attr?>" />
                <?php submit_button(__('Löschen', 'LL_mailer'), '', 'submit', false, array('style' => 'vertical-align: baseline;')); ?>
              </form>
              <br />
<?php
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
            if (in_array($new_attribute, array_keys($attributes))) {
              LL_mailer::message(sprintf(__('Ein Abonnenten-Attribut <b>%s</b> existiert bereits.', 'LL_mailer'), $new_attribute_label));
              wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_settings);
              exit;
            }
            
            $attribute_label = $attributes[$attribute];
            unset($attributes[$attribute]);
            $attributes[$new_attribute] = $new_attribute_label;
            update_option(LL_mailer::option_subscriber_attributes, $attributes);
            LL_mailer::db_subscribers_rename_attribute($attribute, $new_attribute);
            
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
              <input type="text" name="template_slug" placeholder="<?=__('meine-vorlage', 'LL_mailer')?>" class="regular-text" />
            </td>
            </tr>
          </table>
          <?php submit_button(__('Neue Vorlage anlegen', 'LL_mailer')); ?>
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
            <?php submit_button(__('Vorlage löschen', 'LL_mailer'), 'delete'); ?>
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
              <input type="text" name="message_slug" placeholder="<?=__('meine-nachricht', 'LL_mailer')?>" class="regular-text" />
            </td>
            </tr>
          </table>
          <?php submit_button(__('Neue Nachricht anlegen', 'LL_mailer')); ?>
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
        $templates = LL_mailer::db_get_templates('slug');
        $template = LL_mailer::db_get_template_by_slug($message['template_slug']);
        
        $pos1 = strpos($template['body_html'], LL_mailer::token_template_content);
        $pos2 = $pos1 + strlen(LL_mailer::token_template_content);
        $template_body1 = substr($template['body_html'], 0, $pos1);
        $template_body2 = substr($template['body_html'], $pos2);
        
        $template_prefix = '<html><head></head><body>';
        $template_suffix = '</body></html>';
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
                </select>
              </td>
            </tr>
            <tr valign="top">
              <th scope="row"><?=__('Inhalt (HTML)', 'LL_mailer')?></th>
              <td>
                <textarea name="body_html" style="width: 100%;" rows=10><?=$message['body_html']?></textarea>
              </td>
            </tr>
            <tr valign="top">
              <th scope="row"><?=__('Vorschau', 'LL_mailer')?></th>
              <td>
                <iframe id="body_html_preview" style="width: 100%; height: 200px; resize: vertical; border: 1px solid #ddd; background: white;" srcdoc="<?=htmlspecialchars(
                    $template_prefix . $template_body1 . $message['body_html'] . $template_body2 . $template_suffix
                  )?>">
                </iframe>
                <div id="LL_mailer_template_body" style="display: none;"><?=$template['body_html']?></div>
                <script>
                  var body_div = jQuery('#LL_mailer_template_body');
                  var timeout = null;
                  var scrollY = 0;
                  jQuery('[name="body_html"]').on('input', function() {
                    var textarea = this;
                    if (timeout !== null) {
                      clearTimeout(timeout);
                    }
                    timeout = setTimeout(function() {
                      timeout = null;
                      var template_body = body_div.html();
                      var new_body = template_body.replace('<?=LL_mailer::token_template_content?>', jQuery(textarea).val());
                      scrollY = document.querySelector('#body_html_preview').contentWindow.scrollY;
                      jQuery('#body_html_preview')[0].srcdoc = '<?=$template_prefix?>' + new_body + '<?=$template_suffix?>';
                      timeout = setTimeout(function() {
                        document.querySelector('#body_html_preview').contentWindow.scrollTo(0, scrollY);
                      }, 100);
                    }, 1000);
                  });
                </script>
              </td>
            </tr>
            <tr valign="top">
              <th scope="row"><?=__('Inhalt (Text)', 'LL_mailer')?></th>
              <td>
                <textarea name="body_text" style="width: 100%;" rows=10><?=$message['body_text']?></textarea>
              </td>
            </tr>
            <tr>
              <td></td>
              <td>
                <i><?=__('Im Inhalt können <code>[POST "&lt;post-member&gt;"]</code> und <code>[META "&lt;meta-key&gt;"]</code> verwendet werden, um später an der Stelle standard Post-Eigenschaften und individuelle Post-Meta-Daten des entsprechenden Posts einzufügen.<br />Bspw.: <code>[POST "post_title"]</code> oder <code>[META "custom_post_image_url"]</code>', 'LL_mailer')?></i>
              </td>
            </tr>
          </table>
          <?php submit_button(__('Nachricht speichern', 'LL_mailer')); ?>
        </form>
        
        <hr />
        
        <h1><?=__('Löschen', 'LL_mailer')?></h1>
        
        <form method="post" action="admin-post.php">
          <input type="hidden" name="action" value="<?=LL_mailer::_?>_message_action" />
          <?php wp_nonce_field(LL_mailer::_ . '_message_delete'); ?>
          <input type="hidden" name="message_slug" value="<?=$message_slug?>" />
          <?php submit_button(__('Nachricht löschen', 'LL_mailer'), 'delete'); ?>
        </form>
<?php
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
              <input type="email" name="subscriber_mail" placeholder="<?=__('name@email.de', 'LL_mailer')?>" class="regular-text" />
            </td>
            </tr>
          </table>
          <?php submit_button(__('Neuen Abonnenten anlegen', 'LL_mailer')); ?>
        </form>
        
        <hr />
        
        <h1><?=__('Gespeicherte Abonnenten', 'LL_mailer')?></h1>
        
        <p>
<?php
          $subscribers = LL_mailer::db_get_subscribers(array('mail', 'subscribed_at'));
          $edit_url = LL_mailer::admin_url() . LL_mailer::admin_page_subscriber_edit;
          foreach ($subscribers as $subscriber) {
?>
            <?=LL_mailer::list_item?> <a href="<?=$edit_url . $subscriber['mail']?>"><b><?=$subscriber['mail']?></b></a> &nbsp; <span style="color: gray;">( <?=__('abonniert am: ', 'LL_mailer') . $subscriber['subscribed_at']?> )</span><br />
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
          <?php submit_button(__('Abonnent löschen', 'LL_mailer'), 'delete'); ?>
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
          
          LL_mailer::db_save_subscriber(array('mail' => $subscriber_mail));
          
          LL_mailer::message(sprintf(__('Neuer Abonnent <b>%s</b> angelegt.', 'LL_mailer'), $subscriber_mail));
          wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_subscriber_edit . $subscriber_mail);
          exit;
        }
        
        else if (wp_verify_nonce($_POST['_wpnonce'], LL_mailer::_ . '_subscriber_edit')) {
          $subscriber = array(
            'mail' => $subscriber_mail);
            
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
      register_rest_route('LL_mailer/v1', '/subscribe', array(
        'methods' => 'GET',
        'callback' => LL_mailer::_('subscribe')
      ));
    });
  }
}

LL_mailer::init_hooks_and_filters();

?>