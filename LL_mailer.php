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
  
  const option_msg                = LL_mailer::_ . '_msg';
  const option_general_senderName = LL_mailer::_ . '_general_senderName';
  const option_general_senderMail = LL_mailer::_ . '_general_senderMail';
  const option_template_list      = LL_mailer::_ . '_template_list';
  const option_template_          = LL_mailer::_ . '_template_';
  const option_message_list       = LL_mailer::_ . '_message_list';
  const option_message_           = LL_mailer::_ . '_message_';
  
  const admin_page_settings       = LL_mailer::_ . '_settings';
  const admin_page_templates      = LL_mailer::_ . '_templates';
  const admin_page_template_edit  = LL_mailer::_ . '_templates&edit=';
  const admin_page_messages       = LL_mailer::_ . '_messages';
  const admin_page_message_edit   = LL_mailer::_ . '_messages&edit=';
  
  
	static function _($member_function) { return array(LL_mailer::_, $member_function); }
  static function pluginPath() { return plugin_dir_path(__FILE__); }
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
  
  

  static function activate()
  {
    global $wpdb;

    $sql = 'CREATE TABLE ' . $wpdb->prefix . LL_mailer::_ . ' (
      mail varchar(100) NOT NULL,
      name tinytext,
      subscribed_at datetime DEFAULT \'0000-00-00 00:00:00\' NOT NULL,
      PRIMARY KEY  (mail)
    ) ' . $wpdb->get_charset_collate() . ';';

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $result = dbDelta($sql);
    
    LL_mailer::message('Datenbank eingerichtet.<br />' . implode('<hr />', $result));
    
    
    add_option(LL_mailer::option_general_senderName, 'Linda liest');
    add_option(LL_mailer::option_general_senderMail, 'mail@linda-liest.de');
    
    LL_mailer::message('Optionen initialisiert.');
    
     
    register_uninstall_hook(__FILE__, LL_mailer::_('uninstall'));
  }
  
  static function uninstall()
  {
    global $wpdb;
    $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . LL_mailer::_ . ';');
    
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
    add_menu_page(LL_mailer::_,                      LL_mailer::_,                  $required_capability, LL_mailer::admin_page_settings,  LL_mailer::_('admin_page_settings'), plugins_url('/icon.png', __FILE__));
    add_submenu_page(LL_mailer::admin_page_settings, LL_mailer::_, 'Einstellungen', $required_capability, LL_mailer::admin_page_settings,  LL_mailer::_('admin_page_settings'));
    add_submenu_page(LL_mailer::admin_page_settings, LL_mailer::_, 'Vorlagen',      $required_capability, LL_mailer::admin_page_templates, LL_mailer::_('admin_page_templates'));
    add_submenu_page(LL_mailer::admin_page_settings, LL_mailer::_, 'Nachrichten',   $required_capability, LL_mailer::admin_page_messages,  LL_mailer::_('admin_page_messages'));

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
          <input type="text" name="<?=LL_mailer::option_general_senderName?>" value="<?=esc_attr(get_option(LL_mailer::option_general_senderName))?>" placeholder="Name" style="width: 200px;"/>
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
              <input type="text" name="new_template_slug" placeholder="<?=__('meine-vorlage')?>" class="general-text" />
            </td>
            </tr>
          </table>
          <?php submit_button(__('Neue Vorlage anlegen')); ?>
        </form>
        
        <hr />
        
        <h1><?=__('Gespeicherte Vorlagen')?></h1>
        
        <p>
<?php
          $templates = LL_mailer::get_option_array(LL_mailer::option_template_list);
          $edit_url = get_admin_url() . 'admin.php?page=' . LL_mailer::admin_page_template_edit;
          foreach ($templates as $template) {
?>
            <li><a href="<?=$edit_url . $template?>"><?=$template?></a></li>
<?php
          }
?>
        </p>
<?php
      } break;
      
      case 'edit':
      {
        $template_slug = $_GET['edit'];
        $templates = LL_mailer::get_option_array(LL_mailer::option_template_list);
        if (!in_array($template_slug, $templates)) {
          LL_mailer::message(sprintf(__('Es existiert keine Vorlage mit dem Slug <b>$s</b>.'), $template_slug));
          wp_redirect(get_admin_url() . 'admin.php?page=' . LL_mailer::admin_page_templates);
          exit;
        }
        $template = get_option(LL_mailer::option_template_ . $template_slug);
?>
        <h1><?=__('Vorlagen')?> &gt; <?=$template_slug?></h1>

        <form method="post" action="admin-post.php">
          <input type="hidden" name="action" value="<?=LL_mailer::_?>_template_action" />
          <?php wp_nonce_field(LL_mailer::_ . '_template_edit'); ?>
          <input type="hidden" name="template" value="<?=$template_slug?>" />
          <table class="form-table">
            <tr valign="top">
              <th scope="row"><?=__('Layout (HTML)')?></th>
              <td>
                <textarea name="bodyHtml" style="width: 100%;" rows=10><?=$template['bodyHtml']?></textarea>
              </td>
            </tr>
            <tr valign="top">
              <th scope="row"><?=__('Layout (Text)')?></th>
              <td>
                <textarea name="bodyText" style="width: 100%;" rows=10><?=$template['bodyText']?></textarea>
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
        $messages = LL_mailer::get_option_array(LL_mailer::option_message_list);
        $using_messages = array();
        foreach ($messages as $message_slug) {
          $message = get_option(LL_mailer::option_message_ . $message_slug);
          if (!empty($message)) {
            if ($message['template'] == $template_slug) {
              $using_messages[] = $message_slug;
            }
          }
        }
        if (!empty($using_messages)) {
?>
          <p>
            <i><?=__('Diese Vorlage kann nicht gelöscht werden, da sie von folgenden Nachrichten verwendet wird:')?></i><br />
            <?=implode(', ', $using_messages)?>
          </p>
<?php
        } else {
?>
          <form method="post" action="admin-post.php">
            <input type="hidden" name="action" value="<?=LL_mailer::_?>_template_action" />
            <?php wp_nonce_field(LL_mailer::_ . '_template_delete'); ?>
            <input type="hidden" name="template" value="<?=$template_slug?>" />
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
        $new_template = sanitize_title($_POST['new_template_slug']);
        if (!empty($new_template)) {
          
          $templates = LL_mailer::get_option_array(LL_mailer::option_template_list);
          
          $templates[] = $new_template;
          update_option(LL_mailer::option_template_list, $templates);
          
          LL_mailer::message(sprintf(__('Neue Vorlage <b>$s</b> angelegt.'), $new_template));
          wp_redirect(get_admin_url() . 'admin.php?page=' . LL_mailer::admin_page_template_edit . $new_template);
          exit;
        }
      }
      
      else if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], LL_mailer::_ . '_template_edit')) {
        $template_slug = $_POST['template'];
        $templates = LL_mailer::get_option_array(LL_mailer::option_template_list);
        if (!in_array($template_slug, $templates)) {
          LL_mailer::message(sprintf(__('Es existiert keine Vorlage mit dem Slug <b>$s</b>.'), $template_slug));
          wp_redirect(get_admin_url() . 'admin.php?page=' . LL_mailer::admin_page_templates);
          exit;
        }
        
        $template = array(
          'bodyHtml' => $_POST['bodyHtml'],
          'bodyText' => strip_tags($_POST['bodyText']));
        update_option(LL_mailer::option_template_ . $template_slug, $template);
        
        LL_mailer::message(sprintf(__('Änderungen in Vorlage <b>$s</b> gespeichert.'), $template_slug));
        wp_redirect(get_admin_url() . 'admin.php?page=' . LL_mailer::admin_page_template_edit . $template_slug);
        exit;
      }
      
      else if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], LL_mailer::_ . '_template_delete')) {
        $template_slug = $_POST['template'];
        $templates = LL_mailer::get_option_array(LL_mailer::option_template_list);
        if (!in_array($template_slug, $templates)) {
          LL_mailer::message(sprintf(__('Es existiert keine Vorlage mit dem Slug <b>$s</b>.'), $template_slug));
          wp_redirect(get_admin_url() . 'admin.php?page=' . LL_mailer::admin_page_templates);
          exit;
        }
        
        delete_option(LL_mailer::option_template_ . $template_slug);
        
        if (($key = array_search($template_slug, $templates)) !== false) {
          unset($templates[$key]);
        }
        if (empty($templates)) {
          delete_option(LL_mailer::option_template_list);
        }
        else {
          update_option(LL_mailer::option_template_list, $templates);
        }
        
        LL_mailer::message(sprintf(__('Vorlage <b>$s</b> gelöscht.'), $template_slug));
        wp_redirect(get_admin_url() . 'admin.php?page=' . LL_mailer::admin_page_templates);
        exit;
      }
    }
    wp_redirect(get_admin_url() . 'admin.php?page=' . LL_mailer::admin_page_templates);
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
              <input type="text" name="new_message_slug" placeholder="<?=__('meine-nachricht')?>" class="general-text" />
            </td>
            </tr>
          </table>
          <?php submit_button(__('Neue Nachricht anlegen')); ?>
        </form>
        
        <hr />
        
        <h1><?=__('Gespeicherte Nachrichten')?></h1>
        
        <p>
<?php
          $messages = LL_mailer::get_option_array(LL_mailer::option_message_list);
          $edit_url = get_admin_url() . 'admin.php?page=' . LL_mailer::admin_page_message_edit;
          foreach ($messages as $message) {
?>
            <li><a href="<?=$edit_url . $message?>"><?=$message?></a></li>
<?php
          }
?>
        </p>
<?php
      } break;
      
      case 'edit':
      {
        $message_slug = $_GET['edit'];
        $messages = LL_mailer::get_option_array(LL_mailer::option_message_list);
        if (!in_array($message_slug, $messages)) {
          LL_mailer::message(sprintf(__('Es existiert keine Nachricht mit dem Slug <b>$s</b>.'), $message_slug));
          wp_redirect(get_admin_url() . 'admin.php?page=' . LL_mailer::admin_page_messages);
          exit;
        }
        $message = get_option(LL_mailer::option_message_ . $message_slug);
        $templates = LL_mailer::get_option_array(LL_mailer::option_template_list);
?>
        <h1><?=__('Nachrichten')?> &gt; <?=$message_slug?></h1>

        <form method="post" action="admin-post.php">
          <input type="hidden" name="action" value="<?=LL_mailer::_?>_message_action" />
          <?php wp_nonce_field(LL_mailer::_ . '_message_edit'); ?>
          <input type="hidden" name="message" value="<?=$message_slug?>" />
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
                <select name="template" style="min-width: 50%; max-width: 100%;">
<?php
                foreach ($templates as $template_slug) {
?>
                  <option value="<?=esc_attr($template_slug)?>" <?=$template_slug == $message['template'] ? 'selected' : ''?>><?=$template_slug?></option>
<?php
                }
?>
                </select>
              </td>
            </tr>
            <tr valign="top">
              <th scope="row"><?=__('Inhalt (HTML)')?></th>
              <td>
                <textarea name="bodyHtml" style="width: 100%;" rows=10><?=$message['bodyHtml']?></textarea>
              </td>
            </tr>
            <tr valign="top">
              <th scope="row"><?=__('Inhalt (Text)')?></th>
              <td>
                <textarea name="bodyText" style="width: 100%;" rows=10><?=$message['bodyText']?></textarea>
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
        $new_message = sanitize_title($_POST['new_message_slug']);
        if (!empty($new_message)) {
          
          $messages = LL_mailer::get_option_array(LL_mailer::option_message_list);
          
          $messages[] = $new_message;
          update_option(LL_mailer::option_message_list, $messages);
          
          LL_mailer::message(sprintf(__('Neue Nachricht <b>$s</b> angelegt.'), $new_message));
          wp_redirect(get_admin_url() . 'admin.php?page=' . LL_mailer::admin_page_message_edit . $new_message);
          exit;
        }
      }
      
      else if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], LL_mailer::_ . '_message_edit')) {
        $message_slug = $_POST['message'];
        $messages = LL_mailer::get_option_array(LL_mailer::option_message_list);
        if (!in_array($message_slug, $messages)) {
          LL_mailer::message(sprintf(__('Es existiert keine Nachricht mit dem Slug <b>$s</b>.'), $message_slug));
          wp_redirect(get_admin_url() . 'admin.php?page=' . LL_mailer::admin_page_messages);
          exit;
        }
        
        $message = array(
          'subject' => $_POST['subject'],
          'template' => $_POST['template'],
          'bodyHtml' => $_POST['bodyHtml'],
          'bodyText' => strip_tags($_POST['bodyText']));
        update_option(LL_mailer::option_message_ . $message_slug, $message);
        
        LL_mailer::message(sprintf(__('Änderungen in Nachricht <b>$s</b> gespeichert.'), $message_slug));
        wp_redirect(get_admin_url() . 'admin.php?page=' . LL_mailer::admin_page_message_edit . $message_slug);
        exit;
      }
    }
    wp_redirect(get_admin_url() . 'admin.php?page=' . LL_mailer::admin_page_messages);
    exit;
  }
  
  
  
  
  
  
  
 

  static function init_hooks_and_filters() {
    
    add_action('admin_menu', LL_mailer::_('admin_menu'));
    add_action('admin_post_' . LL_mailer::_ . '_template_action', LL_mailer::_('admin_page_template_action'));
    add_action('admin_post_' . LL_mailer::_ . '_message_action', LL_mailer::_('admin_page_message_action'));
    
    
    
    add_action('admin_notices', LL_mailer::_('admin_notices'));

    register_activation_hook(__FILE__, LL_mailer::_('activate'));

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