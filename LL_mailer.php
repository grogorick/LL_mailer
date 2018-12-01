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
  
  const option_msg                  = LL_mailer::_ . '_msg';
  const option_general_senderName   = LL_mailer::_ . '_general_senderName';
  const option_general_senderMail   = LL_mailer::_ . '_general_senderMail';
  
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
  
  const list_item = '&ndash; &nbsp;';
  
  
  
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
      foreach($msgs as $msg) {
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
  
  static function escape_values($array)
  {
    return array_map(function($val) {
      return (!is_null($val)) ? '"' . $val . '"' : 'NULL';
    }, $array);
  }
  
  static function _db_build_select($table, $what, $where)
  {
    if (is_array($what)) {
      $what = implode(', ', $what);
    }
    $sql = 'SELECT ' . $what . ' FROM ' . $table . LL_mailer::array_zip(' = ', LL_mailer::escape_values($where), ' AND ', ' WHERE ') . ';';
    // LL_mailer::message($sql);
    return $sql;
  }
  
  static function _db_insert_or_update($table, $data, $primary_key, $timestamp_key = null)
  {
    $data = LL_mailer::escape_values($data);
    if (!is_null($timestamp_key))
      $data[$timestamp_key] = 'NOW()';
    $data_keys = array_keys($data);
    $data_values = array_values($data);
    $data_no_slug = array_filter($data, function($val) use ($primary_key) { return $val != $primary_key; }, ARRAY_FILTER_USE_KEY);
    global $wpdb;
    $sql = 'INSERT INTO ' . $wpdb->prefix . $table . ' ( ' . implode(', ', $data_keys) . ' ) 
            VALUES ( ' . implode(', ', $data_values) . ' )' . 
            LL_mailer::array_zip(' = ', $data_no_slug, ', ', ' ON DUPLICATE KEY UPDATE ') . ';';
    // LL_mailer::message($sql);
    return $wpdb->query($sql);
  }
  
  static function _db_delete($table, $where)
  {
    global $wpdb;
    $sql = 'DELETE FROM ' . $wpdb->prefix . $table . LL_mailer::array_zip(' = ', LL_mailer::escape_values($where), ' AND ', ' WHERE ') . ';';
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
  // - name
  // - subscribed_at
  static function db_save_subscriber($subscriber) { return LL_mailer::_db_insert_or_update(LL_mailer::table_subscribers, $subscriber, 'mail'); }
  static function db_delete_subscriber($mail) { return LL_mailer::_db_delete(LL_mailer::table_subscribers, array('mail' => $mail)); }
  static function db_get_subscriber_by_mail($mail) { return LL_mailer::_db_select_row(LL_mailer::table_subscribers, '*', array('mail' => $mail)); }
  static function db_get_subscribers($what) { return LL_mailer::_db_select(LL_mailer::table_subscribers, $what); }
  
  

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
        name tinytext,
        subscribed_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (mail)
      ) ' . $wpdb->get_charset_collate() . ';');
    
    LL_mailer::message('Datenbank eingerichtet.<br /><p>- ' . implode('</p><p>- ', $r) . '</p>');
    
    
    add_option(LL_mailer::option_general_senderName, 'Linda liest');
    add_option(LL_mailer::option_general_senderMail, 'mail@linda-liest.de');
    
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
    delete_option(LL_mailer::option_general_senderName);
    delete_option(LL_mailer::option_general_senderMail);
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
          $mail->setFrom(get_option(LL_mailer::option_general_senderMail), get_option(LL_mailer::option_general_senderName));
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

    add_action('admin_init', LL_mailer::_('admin_page_settings_action'));
  }

  
  
  static function admin_page_settings()
  {
?>
    <div class="wrap">
    <h1><?=__('Allgemeine Einstellungen')?></h1>

    <form method="post" action="options.php">
      <?php settings_fields(LL_mailer::_ . '_general'); ?>
      <table class="form-table">
        <tr valign="top">
        <th scope="row"><?=__('Absender')?></th>
        <td>
          <input type="text" name="<?=LL_mailer::option_general_senderName?>" value="<?=esc_attr(get_option(LL_mailer::option_general_senderName))?>" placeholder="Name" class="regular-text"/>
          <input type="text" name="<?=LL_mailer::option_general_senderMail?>" value="<?=esc_attr(get_option(LL_mailer::option_general_senderMail))?>" placeholder="E-Mail" class="regular-text" />
        </td>
        </tr>
      </table>
      <?php submit_button(); ?>
    </form>
    </div>
<?php
  }

  static function admin_page_settings_action()
  {
    // Save changed settings via WordPress
    register_setting(LL_mailer::_ . '_general', LL_mailer::option_general_senderName);
    register_setting(LL_mailer::_ . '_general', LL_mailer::option_general_senderMail);
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
        <h1><?=__('Neue Vorlage')?></h1>

        <form method="post" action="admin-post.php">
          <input type="hidden" name="action" value="<?=LL_mailer::_?>_template_action" />
          <?php wp_nonce_field(LL_mailer::_ . '_template_add'); ?>
          <table class="form-table">
            <tr valign="top">
            <th scope="row"><?=__('Slug für neue Vorlage')?></th>
            <td>
              <input type="text" name="template_slug" placeholder="<?=__('meine-vorlage')?>" class="regular-text" />
            </td>
            </tr>
          </table>
          <?php submit_button(__('Neue Vorlage anlegen')); ?>
        </form>
        
        <hr />
        
        <h1><?=__('Gespeicherte Vorlagen')?></h1>
        
        <p>
<?php
          $templates = LL_mailer::db_get_templates('slug, last_modified');
          $edit_url = LL_mailer::admin_url() . LL_mailer::admin_page_template_edit;
          foreach ($templates as $template) {
?>
            <?=LL_mailer::list_item?> <a href="<?=$edit_url . $template['slug']?>"><b><?=$template['slug']?></b></a> &nbsp; <span style="color: gray;">( <?=__('zuletzt bearbeitet: ') . $template['last_modified']?> )</span><br />
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
          LL_mailer::message(sprintf(__('Es existiert keine Vorlage <b>%s</b>.'), $template_slug));
          wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_templates);
          exit;
        }
?>
        <h1><?=__('Vorlagen')?> &gt; <?=$template_slug?></h1>

        <form method="post" action="admin-post.php">
          <input type="hidden" name="action" value="<?=LL_mailer::_?>_template_action" />
          <?php wp_nonce_field(LL_mailer::_ . '_template_edit'); ?>
          <input type="hidden" name="template_slug" value="<?=$template_slug?>" />
          <table class="form-table">
            <tr valign="top">
              <th scope="row"><?=__('Layout (HTML)')?></th>
              <td>
                <textarea name="body_html" style="width: 100%;" rows=10><?=$template['body_html']?></textarea>
              </td>
            </tr>
            <tr valign="top">
              <th scope="row"><?=__('Layout (Text)')?></th>
              <td>
                <textarea name="body_text" style="width: 100%;" rows=10><?=$template['body_text']?></textarea>
              </td>
            </tr>
            <tr>
              <td></td>
              <td>
                <i><?=__('Im Layout muss <b>[CONTENT]</b> an der Stelle verwendet werden, an der später die eigentliche Nachricht eingefügt werden soll.')?></i>
              </td>
            </tr>
          </table>
          <?php submit_button(__('Vorlage speichern')); ?>
        </form>
        
        <hr />
        
        <h1><?=__('Löschen')?></h1>
        
<?php
        $using_messages = LL_mailer::db_get_messages_by_template($template_slug);
        $message_url = LL_mailer::admin_url() . LL_mailer::admin_page_message_edit;
        if (!empty($using_messages)) {
?>
          <i><?=__('Diese Vorlage kann nicht gelöscht werden, da sie von folgenden Nachrichten verwendet wird:')?></i>
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
            <?php submit_button(__('Vorlage löschen'), 'delete'); ?>
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
    if (!empty($_POST)) {
      if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], LL_mailer::_ . '_template_add')) {
        $new_template = sanitize_title($_POST['template_slug']);
        if (!empty($new_template)) {
          
          $existing_template = LL_mailer::db_get_template_by_slug($new_template);
          if (!empty($existing_template)) {
            LL_mailer::message(sprintf(__('Die Vorlage <b>%s</b> existiert bereits.'), $new_template) . print_r($existing_template, true));
            wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_templates);
            exit;
          }
          
          LL_mailer::db_save_template(array('slug' => $new_template));
          
          LL_mailer::message(sprintf(__('Neue Vorlage <b>%s</b> angelegt.'), $new_template));
          wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_template_edit . $new_template);
          exit;
        }
      }
      
      else if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], LL_mailer::_ . '_template_edit')) {
        $template = array(
          'slug' => $_POST['template_slug'],
          'body_html' => $_POST['body_html'] ?: null,
          'body_text' => strip_tags($_POST['body_text']) ?: null);
        LL_mailer::db_save_template($template);
        
        LL_mailer::message(sprintf(__('Vorlage <b>%s</b> gespeichert.'), $template['slug']));
        wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_template_edit . $template['slug']);
        exit;
      }
      
      else if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], LL_mailer::_ . '_template_delete')) {
        $template_slug = $_POST['template_slug'];
        LL_mailer::db_delete_template($template_slug);
        
        LL_mailer::message(sprintf(__('Vorlage <b>%s</b> gelöscht.'), $template_slug));
        wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_templates);
        exit;
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
        <h1><?=__('Neue Nachricht')?></h1>

        <form method="post" action="admin-post.php">
          <input type="hidden" name="action" value="<?=LL_mailer::_?>_message_action" />
          <?php wp_nonce_field(LL_mailer::_ . '_message_add'); ?>
          <table class="form-table">
            <tr valign="top">
            <th scope="row"><?=__('Slug für neue Nachricht')?></th>
            <td>
              <input type="text" name="message_slug" placeholder="<?=__('meine-nachricht')?>" class="regular-text" />
            </td>
            </tr>
          </table>
          <?php submit_button(__('Neue Nachricht anlegen')); ?>
        </form>
        
        <hr />
        
        <h1><?=__('Gespeicherte Nachrichten')?></h1>
        
        <p>
<?php
          $messages = LL_mailer::db_get_messages('slug, subject, template_slug, last_modified');
          $edit_url = LL_mailer::admin_url() . LL_mailer::admin_page_message_edit;
          foreach ($messages as $message) {
?>
            <?=LL_mailer::list_item?> <a href="<?=$edit_url . $message['slug']?>"><b><?=$message['slug']?></b></a> &nbsp; <?=$message['subject'] ?: '<i>(kein Betreff)</i>'?> &nbsp; <span style="color: gray;">( <?=$message['template_slug']?> &mdash; <?=__('zuletzt bearbeitet: ') . $message['last_modified']?> )</span><br />
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
          LL_mailer::message(sprintf(__('Es existiert keine Nachricht <b>%s</b>.'), $message_slug));
          wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_messages);
          exit;
        }
        $templates = LL_mailer::db_get_templates('slug');
?>
        <h1><?=__('Nachrichten')?> &gt; <?=$message_slug?></h1>

        <form method="post" action="admin-post.php">
          <input type="hidden" name="action" value="<?=LL_mailer::_?>_message_action" />
          <?php wp_nonce_field(LL_mailer::_ . '_message_edit'); ?>
          <input type="hidden" name="message_slug" value="<?=$message_slug?>" />
          <table class="form-table">
            <tr valign="top">
              <th scope="row"><?=__('Betreff')?></th>
              <td>
                <input type="text" name="subject" value="<?=esc_attr($message['subject'])?>" placeholder="Betreff" style="width: 100%;" />
              </td>
            </tr>
            <tr valign="top">
              <th scope="row"><?=__('Vorlage')?></th>
              <td>
                <select name="template_slug" style="min-width: 50%; max-width: 100%;">
                  <option value="">--</option>
<?php
                  foreach ($templates as $template) {
?>
                    <option value="<?=esc_attr($template['slug'])?>" <?=$template['slug'] == $message['template_slug'] ? 'selected' : ''?>><?=$template['slug']?></option>
<?php
                  }
?>
                </select>
              </td>
            </tr>
            <tr valign="top">
              <th scope="row"><?=__('Inhalt (HTML)')?></th>
              <td>
                <textarea name="body_html" style="width: 100%;" rows=10><?=$message['body_html']?></textarea>
              </td>
            </tr>
            <tr valign="top">
              <th scope="row"><?=__('Inhalt (Text)')?></th>
              <td>
                <textarea name="body_text" style="width: 100%;" rows=10><?=$message['body_text']?></textarea>
              </td>
            </tr>
            <tr>
              <td></td>
              <td>
                <i><?=__('Im Inhalt können <code>[POST "&lt;post-member&gt;"]</code> und <code>[META "&lt;meta-key&gt;"]</code> verwendet werden, um später an der Stelle standard Post-Eigenschaften und individuelle Post-Meta-Daten des entsprechenden Posts einzufügen.<br />Bspw.: <code>[POST "post_title"]</code> oder <code>[META "custom_post_image_url"]</code>')?></i>
              </td>
            </tr>
          </table>
          <?php submit_button(__('Nachricht speichern')); ?>
        </form>
        
        <hr />
        
        <h1><?=__('Löschen')?></h1>
        
        <form method="post" action="admin-post.php">
          <input type="hidden" name="action" value="<?=LL_mailer::_?>_message_action" />
          <?php wp_nonce_field(LL_mailer::_ . '_message_delete'); ?>
          <input type="hidden" name="message_slug" value="<?=$message_slug?>" />
          <?php submit_button(__('Nachricht löschen'), 'delete'); ?>
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
    if (!empty($_POST)) {
      if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], LL_mailer::_ . '_message_add')) {
        $new_message = sanitize_title($_POST['message_slug']);
        if (!empty($new_message)) {
          
          $existing_message = LL_mailer::db_get_message_by_slug($new_message);
          if (!empty($existing_message)) {
            LL_mailer::message(sprintf(__('Die Nachricht <b>%s</b> existiert bereits.'), $new_message));
            wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_messages);
            exit;
          }
          
          LL_mailer::db_save_message(array('slug' => $new_message));
          
          LL_mailer::message(sprintf(__('Neue Nachricht <b>%s</b> angelegt.'), $new_message));
          wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_message_edit . $new_message);
          exit;
        }
      }
      
      else if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], LL_mailer::_ . '_message_edit')) {
        $message = array(
          'slug' => $_POST['message_slug'],
          'subject' => $_POST['subject'] ?: null,
          'template_slug' => $_POST['template_slug'] ?: null,
          'body_html' => $_POST['body_html'] ?: null,
          'body_text' => strip_tags($_POST['body_text']) ?: null);
        LL_mailer::db_save_message($message);
        
        LL_mailer::message(sprintf(__('Nachricht <b>%s</b> gespeichert.'), $message['slug']));
        wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_message_edit . $message['slug']);
        exit;
      }
      
      else if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], LL_mailer::_ . '_message_delete')) {
        $message_slug = $_POST['message_slug'];
        LL_mailer::db_delete_message($message_slug);
        
        LL_mailer::message(sprintf(__('Nachricht <b>%s</b> gelöscht.'), $message_slug));
        wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_messages);
        exit;
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
        <h1><?=__('Neuer Abonnent')?></h1>

        <form method="post" action="admin-post.php">
          <input type="hidden" name="action" value="<?=LL_mailer::_?>_subscriber_action" />
          <?php wp_nonce_field(LL_mailer::_ . '_subscriber_add'); ?>
          <table class="form-table">
            <tr valign="top">
            <th scope="row"><?=__('E-Mail des neuen Abonnenten')?></th>
            <td>
              <input type="email" name="subscriber_mail" placeholder="<?=__('name@email.de')?>" class="regular-text" />
            </td>
            </tr>
          </table>
          <?php submit_button(__('Neuen Abonnenten anlegen')); ?>
        </form>
        
        <hr />
        
        <h1><?=__('Gespeicherte Abonnenten')?></h1>
        
        <p>
<?php
          $subscribers = LL_mailer::db_get_subscribers('mail, name, subscribed_at');
          $edit_url = LL_mailer::admin_url() . LL_mailer::admin_page_subscriber_edit;
          foreach ($subscribers as $subscriber) {
?>
            <?=LL_mailer::list_item?> <a href="<?=$edit_url . $subscriber['mail']?>"><b><?=$subscriber['mail']?></b></a> &nbsp; <?=$subscriber['name'] ?: '<i>(kein Name)</i>'?> &nbsp; <span style="color: gray;">( <?=__('abonniert am: ') . $subscriber['subscribed_at']?> )</span><br />
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
          LL_mailer::message(sprintf(__('Es existiert kein Abonnent <b>%s</b>.'), $subscriber_mail));
          wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_subscribers);
          exit;
        }
?>
        <h1><?=__('Abonnenten')?> &gt; <?=$subscriber_mail?></h1>

        <form method="post" action="admin-post.php">
          <input type="hidden" name="action" value="<?=LL_mailer::_?>_subscriber_action" />
          <?php wp_nonce_field(LL_mailer::_ . '_subscriber_edit'); ?>
          <input type="hidden" name="subscriber_mail" value="<?=$subscriber_mail?>" />
          <table class="form-table">
            <tr valign="top">
              <th scope="row"><?=__('Name')?></th>
              <td>
                <input type="text" name="name" value="<?=esc_attr($subscriber['name'])?>" placeholder="Name" class="regular-text" />
              </td>
            </tr>
          </table>
          <?php submit_button(__('Abonnent speichern')); ?>
        </form>
        
        <hr />
        
        <h1><?=__('Löschen')?></h1>
        
        <form method="post" action="admin-post.php">
          <input type="hidden" name="action" value="<?=LL_mailer::_?>_subscriber_action" />
          <?php wp_nonce_field(LL_mailer::_ . '_subscriber_delete'); ?>
          <input type="hidden" name="subscriber_mail" value="<?=$subscriber_mail?>" />
          <?php submit_button(__('Abonnent löschen'), 'delete'); ?>
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
    if (!empty($_POST)) {
      if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], LL_mailer::_ . '_subscriber_add')) {
        $new_subscriber = trim($_POST['subscriber_mail']);
        if (!empty($new_subscriber)) {
          if (!filter_var($new_subscriber, FILTER_VALIDATE_EMAIL)) {
            LL_mailer::message(sprintf(__('Die E-Mail Adresse <b>%s</b> ist ungültig.'), $new_subscriber));
            wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_subscribers);
            exit;
          }
          
          $existing_subscriber = LL_mailer::db_get_subscriber_by_mail($new_subscriber);
          if (!empty($existing_subscriber)) {
            LL_mailer::message(sprintf(__('Der Abonnent <b>%s</b> existiert bereits.'), $new_subscriber));
            wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_subscribers);
            exit;
          }
          
          LL_mailer::db_save_subscriber(array('mail' => $new_subscriber));
          
          LL_mailer::message(sprintf(__('Neuer Abonnent <b>%s</b> angelegt.'), $new_subscriber));
          wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_subscriber_edit . $new_subscriber);
          exit;
        }
      }
      
      else if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], LL_mailer::_ . '_subscriber_edit')) {
        $subscriber = array(
          'mail' => $_POST['subscriber_mail'],
          'name' => $_POST['name'] ?: null);
        LL_mailer::db_save_subscriber($subscriber);
        
        LL_mailer::message(sprintf(__('Abonnent <b>%s</b> gespeichert.'), $subscriber['mail']));
        wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_subscriber_edit . $subscriber['mail']);
        exit;
      }
      
      else if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], LL_mailer::_ . '_subscriber_delete')) {
        $subscriber_mail = $_POST['subscriber_mail'];
        LL_mailer::db_delete_subscriber($subscriber_mail);
        
        LL_mailer::message(sprintf(__('Abonnent <b>%s</b> gelöscht.'), $subscriber_mail));
        wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_subscribers);
        exit;
      }
    }
    wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_subscribers);
    exit;
  }
  
  
  
  
  
  
  
 

  static function init_hooks_and_filters() {
    
    add_action('admin_menu', LL_mailer::_('admin_menu'));
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