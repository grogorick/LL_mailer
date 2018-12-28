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
  
  const option_msg                          = LL_mailer::_ . '_msg';
  const option_sender_name                  = LL_mailer::_ . '_sender_name';
  const option_sender_mail                  = LL_mailer::_ . '_sender_mail';
  const option_subscriber_attributes        = LL_mailer::_ . '_subscriber_attributes';
  const option_subscribe_page               = LL_mailer::_ . '_subscribe_page';
  const option_confirmation_sent_page       = LL_mailer::_ . '_confirmation_sent_page';
  const option_confirmed_page               = LL_mailer::_ . '_confirmed_page';
  const option_confirmation_msg             = LL_mailer::_ . '_confirmation_msg';
  
  const subscriber_attribute_mail           = 'mail';
  const subscriber_attribute_name           = 'name';
  const subscriber_attribute_subscribed_at  = 'subscribed_at';
  
  const table_templates                     = LL_mailer::_ . '_templates';
  const table_messages                      = LL_mailer::_ . '_messages';
  const table_subscribers                   = LL_mailer::_ . '_subscribers';
  
  const admin_page_settings                 = LL_mailer::_ . '_settings';
  const admin_page_templates                = LL_mailer::_ . '_templates';
  const admin_page_template_edit            = LL_mailer::_ . '_templates&edit=';
  const admin_page_messages                 = LL_mailer::_ . '_messages';
  const admin_page_message_edit             = LL_mailer::_ . '_messages&edit=';
  const admin_page_subscribers              = LL_mailer::_ . 'subscribers';
  const admin_page_subscriber_edit          = LL_mailer::_ . 'subscribers&edit=';
  
  const token_CONTENT                       = array('pattern' => '[CONTENT]',
                                                    'html'    => '[CONTENT]');
  const token_CONFIRMATION_URL              = array('pattern' => '[CONFIRMATION_URL]',
                                                    'html'    => '[CONFIRMATION_URL]');
  const token_SUBSCRIBER_ATTRIBUTE          = array('pattern' => '/\[SUBSCRIBER "([^"]+)" "([^"]+)" "([^"]+)" "([^"]+)"\]/',
                                                    'html'    => '[SUBSCRIBER "<i>Prefix</i>"&nbsp;"<i>Attribut&nbsp;Slug</i>"&nbsp;"<i>Suffix</i>"&nbsp;"<i>Alternative</i>"]');
                                              
  const shortcode_SUBSCRIPTION_FORM         = array('code'    => 'LL_mailer_SUBSCRIPTION_FORM',
                                                    'html'    => '[LL_mailer_SUBSCRIPTION_FORM form_attr=""&nbsp;row_attr=""&nbsp;label_attr=""&nbsp;input_attr=""]');
  const shortcode_SUBSCRIBER_ATTRIBUTE      = array('code'    => 'LL_mailer_SUBSCRIBER',
                                                    'html'    => '[LL_mailer_SUBSCRIBER "<i>Attribut&nbsp;Slug</i>"]');
  
  const list_item = '<span style="padding: 5px;">&ndash;</span>';
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
      foreach ($msgs as &$msg) {
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
  
  static function escape_key($key)
  {
    return '`' . $key . '`';
  }
  
  static function escape_keys($keys)
  {
    if (is_array($keys)) {
      return array_map(function($key) {
        return LL_mailer::escape_key($key);
      }, $keys);
    }
    if ($keys != '*')
      return LL_mailer::escape_key($keys);
    return $keys;
  }
  
  static function escape_value($value)
  {
    return '"' . $value . '"';
  }
  
  static function escape_values($values)
  {
    if (is_array($values)) {
      return array_map(function($val) {
        return (!is_null($val)) ? LL_mailer::escape_value($val) : 'NULL';
      }, $values);
    }
    return LL_mailer::escape_value($values);
  }
  
  static function escape($assoc_array)
  {
    $ret = array();
    foreach ($assoc_array as $key => $val) {
      $ret[LL_mailer::escape_key($key)] = (!is_null($val)) ? LL_mailer::escape_value($val) : 'NULL';
    }
    return $ret;
  }
  
  static function build_where($where)
  {
    return LL_mailer::array_zip(' = ', LL_mailer::escape($where), ' AND ', ' WHERE ');
  }
  
  static function _db_build_select($table, $what, $where)
  {
    if (is_array($what)) {
      $what = implode(', ', LL_mailer::escape_keys($what));
    }
    else {
      $what = LL_mailer::escape_keys($what);
    }
    $sql = 'SELECT ' . $what . ' FROM ' . LL_mailer::escape_keys($table) . LL_mailer::build_where($where) . ';';
    // LL_mailer::message($sql);
    return $sql;
  }
  
  static function _db_insert($table, $data, $timestamp_key = null)
  {
    $data = LL_mailer::escape($data);
    if (!is_null($timestamp_key))
      $data[LL_mailer::escape_key($timestamp_key)] = 'NOW()';
    global $wpdb;
    $sql = 'INSERT INTO ' . LL_mailer::escape_key($wpdb->prefix . $table) . ' ( ' . implode(', ', array_keys($data)) . ' ) VALUES ( ' . implode(', ', array_values($data)) . ' );';
    // LL_mailer::message($sql);
    return $wpdb->query($sql);
  }
  
  static function _db_update($table, $data, $where, $timestamp_key = null)
  {
    $data = LL_mailer::escape($data);
    if (!is_null($timestamp_key))
      $data[LL_mailer::escape_key($timestamp_key)] = 'NOW()';
    global $wpdb;
    $sql = 'UPDATE ' . LL_mailer::escape_key($wpdb->prefix . $table) . ' SET ' . LL_mailer::array_zip(' = ', $data, ', ') . LL_mailer::build_where($where) . ';';
    // LL_mailer::message($sql);
    return $wpdb->query($sql);
  }
  
  static function _db_delete($table, $where)
  {
    global $wpdb;
    $sql = 'DELETE FROM ' . LL_mailer::escape_key($wpdb->prefix . $table) . LL_mailer::build_where($where) . ';';
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
  
  
  
  static function db_check_post_exists($slug) {
    global $wpdb;
    return (int) $wpdb->get_var('SELECT ID FROM ' . $wpdb->posts . LL_mailer::build_where(array('post_name' => $slug)) . ';');
  }
  
  // templates
  // - slug
  // - body_html
  // - body_text
  // - last_modified
  static function db_add_template($template) { return LL_mailer::_db_insert(LL_mailer::table_templates, $template); }
  static function db_update_template($template, $slug) { return LL_mailer::_db_update(LL_mailer::table_templates, $template, array('slug' => $slug), 'last_modified'); }
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
  static function db_add_message($message) { return LL_mailer::_db_insert(LL_mailer::table_messages, $message); }
  static function db_update_message($message, $slug) { return LL_mailer::_db_update(LL_mailer::table_messages, $message, array('slug' => $slug), 'last_modified'); }
  static function db_delete_message($slug) { return LL_mailer::_db_delete(LL_mailer::table_messages, array('slug' => $slug)); }
  static function db_get_message_by_slug($slug) { return LL_mailer::_db_select_row(LL_mailer::table_messages, '*', array('slug' => $slug)); }
  static function db_get_messages($what) { return LL_mailer::_db_select(LL_mailer::table_messages, $what); }
  static function db_get_messages_by_template($template_slug) { return array_map(function($v) { return $v['slug']; }, LL_mailer::_db_select(LL_mailer::table_messages, 'slug', array('template_slug' => $template_slug))); }
  
  // subscribers
  // - mail
  // - subscribed_at
  // [...]
  static function db_add_subscriber($subscriber) { return LL_mailer::_db_insert(LL_mailer::table_subscribers, $subscriber); }
  static function db_update_subscriber($subscriber, $old_mail) { return LL_mailer::_db_update(LL_mailer::table_subscribers, $subscriber, array(LL_mailer::subscriber_attribute_mail => $old_mail)); }
  static function db_confirm_subscriber($mail) { return LL_mailer::_db_update(LL_mailer::table_subscribers, array(), array(LL_mailer::subscriber_attribute_mail => $mail), LL_mailer::subscriber_attribute_subscribed_at); }
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
        `' . LL_mailer::subscriber_attribute_subscribed_at . '` datetime NULL DEFAULT NULL,
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
    delete_option(LL_mailer::option_subscribe_page);
    delete_option(LL_mailer::option_confirmation_sent_page);
    delete_option(LL_mailer::option_confirmed_page);
  }
  
  
  
  static function json_get($request)
  {
    if (isset($request['template'])) {
      return LL_mailer::db_get_template_by_slug($request['template']);
    }
    else if (isset($request['post_exists'])) {
      return LL_mailer::db_check_post_exists($request['post_exists']);
    }
  }
  
  
  
  static function replace_token_SUBSCRIBER_ATTRIBUTE($text, &$to, &$attributes)
  {
    preg_match_all(LL_mailer::token_SUBSCRIBER_ATTRIBUTE['pattern'], $text, $matches, PREG_SET_ORDER);
    if (!empty($matches)) {
      $FULL = 0;
      $PREFIX = 1;
      $ATTR = 2;
      $SUFFIX = 3;
      $ALTERNATIVE = 4;
      foreach ($matches as &$match) {
        $attr = $match[$ATTR];
        if (in_array($attr, $attributes)) {
          if (!empty($to[$attr]))
            $replacement = $match[$PREFIX] . $to[$attr] . $match[$SUFFIX];
          else
            $replacement = $match[$ALTERNATIVE];
          $text = str_replace($match[$FULL], $replacement, $text);
        }
      }
    }
    return $text;
  }
  
  static function send_mail($to, $msg)
  {
    if (isset($to)) {
        $to = LL_mailer::db_get_subscriber_by_mail($to);
        if (is_null($to)) return __('Empfänger nicht gefunden.', 'LL_mailer');
      }
      else return __('Kein Empfänger angegeben.', 'LL_mailer');
      
      if (isset($msg)) {
        $msg = LL_mailer::db_get_message_by_slug($msg);
        if (is_null($msg)) return __('Nachricht nicht gefunden.', 'LL_mailer');
        $body_html = $msg['body_html'];
        $body_text = $msg['body_text'];
        
        if (!is_null($msg['template_slug'])) {
          $template = LL_mailer::db_get_template_by_slug($msg['template_slug']);
          $body_html = str_replace(LL_mailer::token_CONTENT['pattern'], $body_html, $template['body_html']);
          $body_text = str_replace(LL_mailer::token_CONTENT['pattern'], $body_text, $template['body_text']);
        }
      }
      else return __('Keine Nachricht angegeben.', 'LL_mailer');
      
      
      $confirm_url = LL_mailer::json_url() . 'confirm_subscription?subscriber=' . urlencode(base64_encode($to[LL_mailer::subscriber_attribute_mail]));
      $body_html = str_replace(LL_mailer::token_CONFIRMATION_URL['pattern'], $confirm_url, $body_html);
      $body_text = str_replace(LL_mailer::token_CONFIRMATION_URL['pattern'], $confirm_url, $body_text);
      
      $subscriber_attributes = array_keys(LL_mailer::get_option_array(LL_mailer::option_subscriber_attributes));
      $body_html = LL_mailer::replace_token_SUBSCRIBER_ATTRIBUTE($body_html, $to, $subscriber_attributes);
      $body_text = LL_mailer::replace_token_SUBSCRIBER_ATTRIBUTE($body_text, $to, $subscriber_attributes);
      
      
      require LL_mailer::pluginPath() . 'cssin/src/CSSIN.php';
      $cssin = new FM\CSSIN();
      $body_html = $cssin->inlineCSS(site_url(), $body_html);
      
      
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
        $mail->Body = utf8_decode($body_html);
        $mail->AltBody = utf8_decode($body_text);

        $success = $mail->send();
        return false;
        
      } catch (PHPMailer\PHPMailer\Exception $e) {
        return __('Nachricht nicht gesendet. Fehler: ', 'LL_mailer') . $mail->ErrorInfo;
      }
  }
  
  static function testmail($request)
  {
    if (isset($request['send'])) {
      $error = LL_mailer::send_mail($request['to'], $request['msg']);
      if ($error === false) {
        return __('Testnachricht gesendet.', 'LL_mailer');
      }
      else {
        return $error;
      }
    }
    return null;
  }
  
  static function subscribe($request)
  {
    if (!empty($_POST) && isset($_POST[LL_mailer::subscriber_attribute_mail]) && !empty($_POST[LL_mailer::subscriber_attribute_mail])) {
      $attributes = LL_mailer::get_option_array(LL_mailer::option_subscriber_attributes);
      $new_subscriber = array();
      foreach ($attributes as $attr => $attr_label) {
        if (!empty($_POST[$attr])) {
          $new_subscriber[$attr] = $_POST[$attr];
        }
      }
      LL_mailer::message(print_r($new_subscriber, true));
      
      LL_mailer::db_add_subscriber($new_subscriber);
      $error = LL_mailer::send_mail($new_subscriber[LL_mailer::subscriber_attribute_mail], get_option(LL_mailer::option_confirmation_msg));
      if ($error === false) {
        wp_redirect(get_permalink(get_page_by_path(get_option(LL_mailer::option_confirmation_sent_page))) . '?subscriber=' . urlencode(base64_encode($new_subscriber[LL_mailer::subscriber_attribute_mail])));
        exit;
      }
      else {
        return $error;
      }
    }
    wp_redirect(get_permalink(get_page_by_path(get_option(LL_mailer::option_subscribe_page))));
    exit;
  }
  
  static function confirm_subscription($request)
  {
    if (isset($_GET['subscriber']) && !empty($_GET['subscriber'])) {
      $subscriber_mail = base64_decode(urldecode($_GET['subscriber']));
      $existing_subscriber = LL_mailer::db_get_subscriber_by_mail($subscriber_mail);
      if (!is_null($existing_subscriber)) {
        LL_mailer::db_confirm_subscriber($subscriber_mail);
        wp_redirect(get_permalink(get_page_by_path(get_option(LL_mailer::option_confirmed_page))) . '?subscriber=' . urlencode(base64_encode($subscriber_mail)));
        exit;
      }
    }
    wp_redirect(get_permalink(get_page_by_path(get_option(LL_mailer::option_subscribe_page))));
    exit;
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
    $valign = 'style="vertical-align: baseline;"';
?>
    <div class="wrap">
      <h1><?=__('Allgemeine Einstellungen', 'LL_mailer')?></h1>

      <form method="post" action="options.php">
        <?php settings_fields(LL_mailer::_ . '_general'); ?>
        <table class="form-table">
          <tr>
            <th scope="row"><?=__('Absender', 'LL_mailer')?></th>
            <td>
              <input type="text" name="<?=LL_mailer::option_sender_name?>" value="<?=esc_attr(get_option(LL_mailer::option_sender_name))?>" placeholder="Name" class="regular-text" />
              <input type="text" name="<?=LL_mailer::option_sender_mail?>" value="<?=esc_attr(get_option(LL_mailer::option_sender_mail))?>" placeholder="E-Mail" class="regular-text" />
            </td>
          </tr>
          
          <tr>
            <th scope="row" style="padding-bottom: 0;"><?=__('Blog-Seiten', 'LL_mailer')?></th>
          </tr>
          <tr>
            <td <?=$valign?>><?=__('Dem Blog folgen', 'LL_mailer')?></td>
            <td>
              <input type="text" id="<?=LL_mailer::_?>_subscribe_page" name="<?=LL_mailer::option_subscribe_page?>" value="<?=esc_attr(get_option(LL_mailer::option_subscribe_page))?>" placeholder="Seite" class="regular-text" />
              &nbsp; <i id="<?=LL_mailer::_?>_subscribe_page_response"></i>
              <p>(<?=sprintf(__('Nutze <code>%s</code> um ein Formular auf dieser Seite anzuzeigen', 'LL_mailer'), LL_mailer::shortcode_SUBSCRIPTION_FORM['html'])?>)</p>
            </td>
          </tr>
          <tr>
            <td <?=$valign?>><?=__('Bestätigungs-E-Mail gesendet', 'LL_mailer')?></td>
            <td>
              <input type="text" id="<?=LL_mailer::_?>_confirmation_sent_page" name="<?=LL_mailer::option_confirmation_sent_page?>" value="<?=esc_attr(get_option(LL_mailer::option_confirmation_sent_page))?>" placeholder="Seite" class="regular-text" />
              &nbsp; <i id="<?=LL_mailer::_?>_confirmation_sent_page_response"></i>
            </td>
          </tr>
          <tr>
            <td <?=$valign?>><?=__('E-Mail bestätigt', 'LL_mailer')?></td>
            <td>
              <input type="text" id="<?=LL_mailer::_?>_confirmed_page" name="<?=LL_mailer::option_confirmed_page?>" value="<?=esc_attr(get_option(LL_mailer::option_confirmed_page))?>" placeholder="Seite" class="regular-text" />
              &nbsp; <i id="<?=LL_mailer::_?>_confirmed_page_response"></i><br />
              <p>(<?=sprintf(__('Nutze <code>%s</code> um Attribute des neuen Abonnenten auf der Seite anzuzeigen', 'LL_mailer'), LL_mailer::shortcode_SUBSCRIBER_ATTRIBUTE['html'])?>)</p>
            </td>
          </tr>
          
          <tr>
            <th scope="row" style="padding-bottom: 0;"><?=__('E-Mail-Nachrichten', 'LL_mailer')?></th>
          </tr>
          <tr>
            <td <?=$valign?>><?=__('Bestätigungs-E-Mail', 'LL_mailer')?></td>
            <td>
              <select id="<?=LL_mailer::option_confirmation_msg?>" name="<?=LL_mailer::option_confirmation_msg?>">
                <option value="">--</option>
<?php
              $messages = LL_mailer::db_get_messages(array('slug', 'subject'));
              $selected_msg = get_option(LL_mailer::option_confirmation_msg);
              foreach ($messages as $msg) {
?>
                <option value="<?=$msg['slug']?>" <?=$msg['slug'] == $selected_msg ? 'selected' : ''?>><?=$msg['subject'] . ' (' . $msg['slug'] . ')'?></option>
<?php
              }
?>
              </select>
              &nbsp;
              <a id="<?=LL_mailer::option_confirmation_msg?>_link" href="<?=LL_mailer::admin_url() . LL_mailer::admin_page_message_edit . urlencode($selected_msg)?>">(<?=__('Zur Nachricht', 'LL_mailer')?>)</a>
            </td>
          </tr>
        </table>
        <?php submit_button(); ?>
      </form>
      <script>
        new function() {
          var timeout = null;
          function check_page_exists(tag_id) {
            var page_input = document.querySelector('#<?=LL_mailer::_?>' + tag_id);
            var response_tag = document.querySelector('#<?=LL_mailer::_?>' + tag_id + '_response');
            function check_now() {
              if (timeout !== null) {
                clearTimeout(timeout);
              }
              timeout = setTimeout(function() {
                timeout = null;
                jQuery.getJSON('<?=LL_mailer::json_url()?>get?post_exists=' + page_input.value, function(exists) {
                  response_tag.innerHTML = !exists ? '<span style="color: red;"><?=__('Seite nicht gefunden', 'LL_mailer')?></span>' : '';
                });
              }, 1000);
            }
            jQuery(page_input).on('input', check_now);
            check_now();
          }
          function link_message(tag_id) {
            var message_select = document.querySelector('#' + tag_id);
            var link_tag = document.querySelector('#' + tag_id + '_link');
            function link_now() {
              link_tag.href = '<?=LL_mailer::admin_url() . LL_mailer::admin_page_message_edit?>' + encodeURI(message_select.value);
              link_tag.style.display = 'inline';
            }
            jQuery(message_select).on('input', link_now);
            link_now();
          }
          check_page_exists('_subscribe_page');
          check_page_exists('_confirmation_sent_page');
          check_page_exists('_confirmed_page');
          link_message('<?=LL_mailer::option_confirmation_msg?>');
        }
      </script>
      <hr />
      <table class="form-table">
        <tr>
          <th scope="row"><?=__('Abonnenten-Attribute', 'LL_mailer')?></th>
          <td>
<?php
            $attributes = LL_mailer::get_option_array(LL_mailer::option_subscriber_attributes);
            $attribute_groups = array(
              'predefined' => array(
                LL_mailer::subscriber_attribute_mail => $attributes[LL_mailer::subscriber_attribute_mail],
                LL_mailer::subscriber_attribute_name => $attributes[LL_mailer::subscriber_attribute_name]),
              'dynamic' => array_filter($attributes, function($key) { return !LL_mailer::is_predefined_subscriber_attribute($key); }, ARRAY_FILTER_USE_KEY));
?>
            <style>
              .LL_mailer_attributes_table td {
                padding-top: 5px;
                padding-bottom: 0px;
              }
            </style>
            <table class="<?=LL_mailer::_?>_attributes_table">
            <tr><td><?=__('Attribut Label', 'LL_mailer')?></td><td><?=__('Attribut Slug', 'LL_mailer')?></td></tr>
<?php
            foreach ($attribute_groups as $group => &$attrs) {
              foreach ($attrs as $attr => $attr_label) {
?>
              <tr><td>
                <form method="post" action="admin-post.php" style="display: inline;">
                  <input type="hidden" name="action" value="<?=LL_mailer::_?>_settings_action" />
                  <?php wp_nonce_field(LL_mailer::_ . '_subscriber_attribute_edit'); ?>
                  <input type="hidden" name="attribute" value="<?=$attr?>" />
                  <input type="text" name="new_attribute_label" value="<?=$attr_label?>" class="regular-text" />
                  <?php submit_button(__('Speichern', 'LL_mailer'), '', 'submit', false, array('style' => 'vertical-align: baseline;')); ?>
                </form>
              </td><td><code>"<?=$attr?>"</code></td>
              <td>
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
              </td></tr>
<?php
              }
            }
?>
              <tr><td colspan="3">
                <form method="post" action="admin-post.php" style="display: inline;">
                  <input type="hidden" name="action" value="<?=LL_mailer::_?>_settings_action" />
                  <?php wp_nonce_field(LL_mailer::_ . '_subscriber_attribute_add'); ?>
                  <input type="text" name="attribute" placeholder="<?=__('Neues Attribut', 'LL_mailer')?>" class="regular-text" />
                  <?php submit_button(__('Hinzufügen', 'LL_mailer'), '', 'submit', false, array('style' => 'vertical-align: baseline;')); ?>
                </form>
              </td></tr>
            </table>
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
    register_setting(LL_mailer::_ . '_general', LL_mailer::option_subscribe_page);
    register_setting(LL_mailer::_ . '_general', LL_mailer::option_confirmation_sent_page);
    register_setting(LL_mailer::_ . '_general', LL_mailer::option_confirmed_page);
    register_setting(LL_mailer::_ . '_general', LL_mailer::option_confirmation_msg);
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
            <tr>
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
          foreach ($templates as &$template) {
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
            <tr>
              <th scope="row"><?=__('Layout (HTML)', 'LL_mailer')?></th>
              <td>
                <textarea name="body_html" style="width: 100%;" rows=10 autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"><?=$template['body_html']?></textarea>
              </td>
            </tr>
            <tr>
              <th scope="row"><?=__('Vorschau (HTML)', 'LL_mailer')?></th>
              <td>
                <iframe id="body_html_preview" style="width: 100%; height: 200px; resize: vertical; border: 1px solid #ddd; background: white;" srcdoc="<?=htmlspecialchars(
                    LL_mailer::html_prefix . $template['body_html'] . LL_mailer::html_suffix
                  )?>">
                </iframe>
              </td>
            </tr>
            <tr>
              <th scope="row"><?=__('Layout (Text)', 'LL_mailer')?></th>
              <td>
                <textarea name="body_text" style="width: 100%;" rows=10 autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"><?=$template['body_text']?></textarea>
              </td>
            </tr>
            <tr>
              <td style="vertical-align: top;"><?php submit_button(__('Vorlage speichern', 'LL_mailer'), 'primary', '', false); ?></td>
              <td>
                <?=sprintf(__('Im Layout (HTML und Text) muss <code>%s</code> an der Stelle verwendet werden, an der später die eigentliche Nachricht eingefügt werden soll.', 'LL_mailer'), LL_mailer::token_CONTENT['html'])?>
              </td>
            </tr>
          </table>
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
            LL_mailer::message(sprintf(__('<b>%s</b> kann nicht als Vorlagen-Slug verwendet werden.', 'LL_mailer'), $template_slug));
            wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_templates);
            exit;
          }
          
          $existing_template = LL_mailer::db_get_template_by_slug($template_slug);
          if (!empty($existing_template)) {
            LL_mailer::message(sprintf(__('Die Vorlage <b>%s</b> existiert bereits.', 'LL_mailer'), $template_slug));
            wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_templates);
            exit;
          }
          
          LL_mailer::db_add_template(array(
            'slug' => $template_slug,
            'body_html' => "...<br />\n" . LL_mailer::token_CONTENT['pattern'] . "\n<hr />...",
            'body_text' => "...\n" . LL_mailer::token_CONTENT['pattern'] . "\n----------\n..."));
          
          LL_mailer::message(sprintf(__('Neue Vorlage <b>%s</b> angelegt.', 'LL_mailer'), $template_slug));
          wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_template_edit . $template_slug);
          exit;
        }
        
        else if (wp_verify_nonce($_POST['_wpnonce'], LL_mailer::_ . '_template_edit')) {
          $template = array(
            'body_html' => $_POST['body_html'] ?: null,
            'body_text' => strip_tags($_POST['body_text']) ?: null);
          LL_mailer::db_update_template($template, $template_slug);

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
            <tr>
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
          foreach ($messages as &$message) {
?>
            <?=LL_mailer::list_item?> <a href="<?=$edit_url . urlencode($message['slug'])?>"><b><?=$message['slug']?></b></a> &nbsp; 
            <?=$message['subject'] ?: '<i>(kein Betreff)</i>'?> &nbsp; 
            <span style="color: gray;">( <?=$message['template_slug']?> &mdash; <?=__('zuletzt bearbeitet: ', 'LL_mailer') . $message['last_modified']?> )</span><br />
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
          
          $preview_body_html = str_replace(LL_mailer::token_CONTENT['pattern'], $message['body_html'], $template_body_html);
          $preview_body_text = str_replace(LL_mailer::token_CONTENT['pattern'], $message['body_text'], $template_body_text);
        }
?>
        <h1><?=__('Nachrichten', 'LL_mailer')?> &gt; <?=$message_slug?></h1>

        <form method="post" action="admin-post.php">
          <input type="hidden" name="action" value="<?=LL_mailer::_?>_message_action" />
          <?php wp_nonce_field(LL_mailer::_ . '_message_edit'); ?>
          <input type="hidden" name="message_slug" value="<?=$message_slug?>" />
          <table class="form-table">
            <tr>
              <th scope="row"><?=__('Betreff', 'LL_mailer')?></th>
              <td>
                <input type="text" name="subject" value="<?=esc_attr($message['subject'])?>" placeholder="Betreff" style="width: 100%;" />
              </td>
            </tr>
            <tr>
              <th scope="row"><?=__('Vorlage', 'LL_mailer')?></th>
              <td>
                <select name="template_slug" style="min-width: 50%; max-width: 100%;">
                  <option value="">--</option>
<?php
                  foreach ($templates as &$template_slug) {
?>
                    <option value="<?=esc_attr($template_slug['slug'])?>" <?=$template_slug['slug'] == $message['template_slug'] ? 'selected' : ''?>><?=$template_slug['slug']?></option>
<?php
                  }
?>
                </select> &nbsp;
                <a id="<?=LL_mailer::_?>_template_edit_link" href="<?=LL_mailer::admin_url() . LL_mailer::admin_page_template_edit . urlencode($message['template_slug'])?>">(<?=__('Zur Vorlage', 'LL_mailer')?>)</a>
              </td>
            </tr>
            <tr>
              <th scope="row"><?=__('Inhalt (HTML)', 'LL_mailer')?></th>
              <td>
                <textarea name="body_html" style="width: 100%;" rows=10 autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"><?=$message['body_html']?></textarea>
              </td>
            </tr>
            <tr>
              <th scope="row"><?=__('Vorschau (HTML)', 'LL_mailer')?></th>
              <td>
                <iframe id="body_html_preview" style="width: 100%; height: 200px; resize: vertical; border: 1px solid #ddd; background: white;" srcdoc="<?=htmlspecialchars(
                    LL_mailer::html_prefix . $preview_body_html . LL_mailer::html_suffix
                  )?>">
                </iframe>
                <div id="<?=LL_mailer::_?>_template_body_html" style="display: none;"><?=$template_body_html?></div>
              </td>
            </tr>
            <tr>
              <th scope="row"><?=__('Inhalt (Text)', 'LL_mailer')?></th>
              <td>
                <textarea name="body_text" style="width: 100%;" rows=10 autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"><?=$message['body_text']?></textarea>
              </td>
            </tr>
            <tr>
              <th scope="row"><?=__('Vorschau (Text)', 'LL_mailer')?></th>
              <td>
                <textarea disabled id="body_text_preview" style="width: 100%; color:black; background: white;" rows=10><?=$preview_body_text?></textarea>
                <div id="<?=LL_mailer::_?>_template_body_text" style="display: none;"><?=$template_body_text?></div>
              </td>
            </tr>
            <tr>
              <td style="vertical-align: top;"><?php submit_button(__('Nachricht speichern', 'LL_mailer'), 'primary', '', false); ?></td>
              <td>
                <p><?=__('Im Inhalt (HTML und Text) können folgende Platzhalter verwendet werden.', 'LL_mailer')?></p>
                
                <style>
                  .LL_mailer_token_table td {
                    padding: 10px 0px 0px 0px;
                    vertical-align: top;
                  }
                  .LL_mailer_token_table td:nth-child(n+2) {
                    border-bottom: 1px dashed #ddd;
                  }
                  .LL_mailer_token_table tr:last-child td {
                    border-bottom: 0;
                  }
                  .LL_mailer_token_table td:nth-child(3) {
                    padding-left: 20px;
                  }
                </style>
                <table class="<?=LL_mailer::_?>_token_table">
                  <tr><td colspan=2><?=__('Post-Benachrichtigungen:', 'LL_mailer')?></td></tr>
                  <tr>
                    <td><?=LL_mailer::list_item?></td>
                    <td><code>[POST "<i>WP_POST_ATTRIBUTE</i>"]</code></td>
                    <td><?=__('WP_Post Attribute, zB. <i>post_title</i> und <i>post_excerpt</i>', 'LL_mailer')?> <a href="https://codex.wordpress.org/Class_Reference/WP_Post" target="_blank">(?)</a></td>
                  </tr><tr>
                    <td><?=LL_mailer::list_item?></td>
                    <td><code>[META "<i>POST_META</i>"]</code></td>
                    <td><?=__('Individuelle Post-Metadaten, zB. von Plugins', 'LL_mailer')?> <a href="https://codex.wordpress.org/Custom_Fields" target="_blank">(?)</a></td>
                  </tr>
                  <tr><td colspan=2><?=__('Willkommen-E-Mail:', 'LL_mailer')?></td></tr>
                  <tr>
                    <td><?=LL_mailer::list_item?></td>
                    <td><code><?=LL_mailer::token_SUBSCRIBER_ATTRIBUTE['html']?></code></td>
                    <td><?=__('Abonnenten-Attribut. Prefix & Suffix werden vor & nach dem Attribut angezeigt, wenn es für den Abonnenten nicht leer ist.', 'LL_mailer')?></td>
                  </tr><tr>
                    <td><?=LL_mailer::list_item?></td>
                    <td><code><?=LL_mailer::token_CONFIRMATION_URL['html']?></code></td>
                    <td><?=__('URL für Bestätigungs-Link.', 'LL_mailer')?></td>
                  </tr>
                </table>
              </td>
            </tr>
          </table>
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
            preview.contentWindow.document.body.innerHTML = template_body_div.innerHTML.replace('<?=LL_mailer::token_CONTENT['pattern']?>', textarea.value);
          }
          function updatePreviewText(preview, template_body_div, textarea) {
            preview.value = template_body_div.innerHTML.replace('<?=LL_mailer::token_CONTENT['pattern']?>', textarea.value);
          }
          jQuery(textarea_html).on('input', function() { updatePreviewHtml(preview_html, template_body_html_div, textarea_html); });
          jQuery(textarea_text).on('input', function() { updatePreviewText(preview_text, template_body_text_div, textarea_text); });
          jQuery(template_select).on('input', function() {
            if (template_select.value === '') {
              template_edit_link.href = '';
              template_edit_link.style.display = 'none';
              template_body_html_div.innerHTML = '<?=LL_mailer::token_CONTENT['pattern']?>';
              template_body_text_div.innerHTML = '<?=LL_mailer::token_CONTENT['pattern']?>';
              updatePreviewHtml(preview_html, template_body_html_div, textarea_html);
              updatePreviewText(preview_text, template_body_text_div, textarea_text);
            }
            else {
              for (var i = 0; i < show_hide.length; i++)
                show_hide[i].disabled = true;
              
              jQuery.getJSON('<?=LL_mailer::json_url()?>get?template=' + template_select.value, function(new_template) {
                template_edit_link.href = '<?=LL_mailer::admin_url() . LL_mailer::admin_page_template_edit?>' + encodeURI(new_template.slug);
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
        
<?php
        if ($message_slug == get_option(LL_mailer::option_confirmation_msg)) {
?>
          <i>
            <?=__('Diese Nachricht kann nicht gelöscht werden, da sie für die Bestätigungs-E-Mail verwendet wird.', 'LL_mailer')?><br />
            (<a href="<?=LL_mailer::admin_url() . LL_mailer::admin_page_settings?>"><?=__('Zu den Einstellungen', 'LL_mailer')?></a>)
          </i>
<?php
        }
        else {
?>
        <form method="post" action="admin-post.php">
          <input type="hidden" name="action" value="<?=LL_mailer::_?>_message_action" />
          <?php wp_nonce_field(LL_mailer::_ . '_message_delete'); ?>
          <input type="hidden" name="message_slug" value="<?=$message_slug?>" />
          <?php submit_button(__('Nachricht löschen', 'LL_mailer'), ''); ?>
        </form>
<?php
        }
?>
        
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
          <form id="<?=LL_mailer::_?>_testmail" method="post" action="<?=LL_mailer::json_url()?>testmail">
            <input type="hidden" name="msg" value="<?=$message_slug?>" />
            <select id="to" name="to">
<?php
              foreach ($subscribers as &$subscriber) {
?>
                <option value="<?=$subscriber[LL_mailer::subscriber_attribute_mail]?>"><?=$subscriber[LL_mailer::subscriber_attribute_name] . ' / ' . $subscriber[LL_mailer::subscriber_attribute_mail]?></option>
<?php
              }
?>
            </select>
            <?php submit_button(__('senden', 'LL_mailer'), '', '', false); ?> &nbsp;
            <i id="response"></i>
          </form>
          <script>
            var to_select = document.querySelector('#LL_mailer_testmail #to');
            var response_tag = document.querySelector('#LL_mailer_testmail #response');
            jQuery('#LL_mailer_testmail').submit(function(e) {
              var btn = this.querySelector('input[type="submit"]');
              btn.disabled = true;
              response_tag.innerHTML = '...';
              jQuery.getJSON('<?=LL_mailer::json_url() . 'testmail?send&msg=' . $message_slug . '&to='?>' + to_select.value, function(response) {
                btn.disabled = false;
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
          
          LL_mailer::db_add_message(array('slug' => $message_slug));
          
          LL_mailer::message(sprintf(__('Neue Nachricht <b>%s</b> angelegt.', 'LL_mailer'), $message_slug));
          wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_message_edit . $message_slug);
          exit;
        }
        
        else if (wp_verify_nonce($_POST['_wpnonce'], LL_mailer::_ . '_message_edit')) {
          $message = array(
            'subject' => $_POST['subject'] ?: null,
            'template_slug' => $_POST['template_slug'] ?: null,
            'body_html' => $_POST['body_html'] ?: null,
            'body_text' => strip_tags($_POST['body_text']) ?: null);
          LL_mailer::db_update_message($message, $message_slug);

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
            <tr>
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
          foreach ($subscribers as &$subscriber) {
?>
            <?=LL_mailer::list_item?> <a href="<?=$edit_url . urlencode($subscriber[LL_mailer::subscriber_attribute_mail])?>">
              <b><?=($subscriber[LL_mailer::subscriber_attribute_name] ?? '</b><i>(' . __('kein Name', 'LL_mailer') . ')</i><b>') . ' / ' . $subscriber[LL_mailer::subscriber_attribute_mail]?></b>
            </a>
            &nbsp;
            <span style="color: gray;">( <?=!empty($subscriber['subscribed_at']) ? __('abonniert am: ', 'LL_mailer') . $subscriber['subscribed_at'] : __('unbestätigt', 'LL_mailer')?> )</span>
            <br />
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
            <tr>
              <th scope="row"><?=$attr_label?></th>
              <td>
                <input type="text" name="<?=$attr?>" value="<?=esc_attr($subscriber[$attr])?>" placeholder="<?=$attr_label?>" class="regular-text" />
              </td>
            </tr>
<?php
            }
?>
            <tr>
              <th scope="row"><?=__('Abonniert am', 'LL_mailer')?></th>
              <td>
                <?=$subscriber[LL_mailer::subscriber_attribute_subscribed_at] ?: ('<i>(' . __('unbestätigt', 'LL_mailer') . ')</i>')?>
              </td>
            </tr>
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
          
          LL_mailer::db_add_subscriber(array(LL_mailer::subscriber_attribute_mail => $subscriber_mail));
          
          LL_mailer::message(sprintf(__('Neuer Abonnent <b>%s</b> angelegt.', 'LL_mailer'), $subscriber_mail));
          wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_subscriber_edit . urlencode($subscriber_mail));
          exit;
        }
        
        else if (wp_verify_nonce($_POST['_wpnonce'], LL_mailer::_ . '_subscriber_edit')) {
          $new_subscriber_mail = trim($_POST[LL_mailer::subscriber_attribute_mail]);
          if (!filter_var($new_subscriber_mail, FILTER_VALIDATE_EMAIL)) {
            LL_mailer::message(sprintf(__('Die neue E-Mail Adresse <b>%s</b> ist ungültig.', 'LL_mailer'), $new_subscriber_mail));
            wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_subscriber_edit . urlencode($subscriber_mail));
            exit;
          }
          
          $attributes = LL_mailer::get_option_array(LL_mailer::option_subscriber_attributes);
          $subscriber = array();
          foreach (array_keys($attributes) as $attr) {
            $subscriber[$attr] = $_POST[$attr] ?: null;
          }
          $subscriber[LL_mailer::subscriber_attribute_mail] = $new_subscriber_mail;
          
          LL_mailer::db_update_subscriber($subscriber, $subscriber_mail);
          
          LL_mailer::message(sprintf(__('Abonnent <b>%s</b> gespeichert.', 'LL_mailer'), $new_subscriber_mail));
          wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_subscriber_edit . urlencode($new_subscriber_mail));
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
  
  
  
  static function shortcode_SUBSCRIPTION_FORM($atts)
  {
    $attributes = LL_mailer::get_option_array(LL_mailer::option_subscriber_attributes);
    ob_start();
?>
    <form action="<?=LL_mailer::json_url()?>subscribe" method="post" <?=$atts['form_attr'] ?: ''?>>
<?php
    foreach ($attributes as $attr => $attr_label) {
?>
      <p <?=$atts['row_attr'] ?: ''?>>
        <span <?=$atts['label_attr'] ?: ''?>><?=$attr_label?>:</span>
        <input type="text" name="<?=$attr?>" <?=$atts['input_attr'] ?: ''?> />
      </p>
<?php
    }
?>
      <input type="submit" value="<?=__('Jetzt anmelden', 'LL_mailer')?>" class="Button" />
    </form>
<?php
    return ob_get_clean();
  }
  
  static function shortcode_SUBSCRIBER_ATTRIBUTE($atts)
  {
    if (!empty($atts) && isset($_GET['subscriber']) && !empty($_GET['subscriber'])) {
      $subscriber_mail = base64_decode(urldecode($_GET['subscriber']));
      
      $attributes = array_keys(LL_mailer::get_option_array(LL_mailer::option_subscriber_attributes));
      if (in_array($atts[0], $attributes)) {
        
        $subscriber = LL_mailer::db_get_subscriber_by_mail($subscriber_mail);
        if (!is_null($subscriber)) {
          
          return $subscriber[$atts[0]];
        }
      }
    }
    return '';
  }

  
  
  
  
  
 

  static function init_hooks_and_filters()
  {
    add_action('admin_menu', LL_mailer::_('admin_menu'));
    add_action('admin_post_' . LL_mailer::_ . '_settings_action', LL_mailer::_('admin_page_settings_action'));
    add_action('admin_post_' . LL_mailer::_ . '_template_action', LL_mailer::_('admin_page_template_action'));
    add_action('admin_post_' . LL_mailer::_ . '_message_action', LL_mailer::_('admin_page_message_action'));
    add_action('admin_post_' . LL_mailer::_ . '_subscriber_action', LL_mailer::_('admin_page_subscriber_action'));
    
    
    add_shortcode(LL_mailer::shortcode_SUBSCRIPTION_FORM['code'], LL_mailer::_('shortcode_SUBSCRIPTION_FORM'));
    add_shortcode(LL_mailer::shortcode_SUBSCRIBER_ATTRIBUTE['code'], LL_mailer::_('shortcode_SUBSCRIBER_ATTRIBUTE'));
    
    
    add_action('admin_notices', LL_mailer::_('admin_notices'));

    register_activation_hook(__FILE__, LL_mailer::_('activate'));
    register_deactivation_hook(__FILE__, LL_mailer::_('uninstall'));

    // add_action('transition_post_status', LL_mailer::_('post_status_transition'), 10, 3);

    add_action('rest_api_init', function ()
    {
      register_rest_route('LL_mailer/v1', 'get', array(
        'callback' => LL_mailer::_('json_get')
      ));
      register_rest_route('LL_mailer/v1', 'testmail', array(
        'callback' => LL_mailer::_('testmail')
      ));
      register_rest_route('LL_mailer/v1', 'subscribe', array(
        'callback' => LL_mailer::_('subscribe'),
        'methods' => 'POST'
      ));
      register_rest_route('LL_mailer/v1', 'confirm_subscription', array(
        'callback' => LL_mailer::_('confirm_subscription')
      ));
    });
  }
}

LL_mailer::init_hooks_and_filters();

?>