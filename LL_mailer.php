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
  const option_confirmation_msg             = LL_mailer::_ . '_confirmation_msg';
  const option_confirmed_admin_msg          = LL_mailer::_ . '_confirmed_admin_msg';
  const option_confirmed_page               = LL_mailer::_ . '_confirmed_page';
  const option_unsubscribed_admin_msg       = LL_mailer::_ . '_unsubscribed_admin_msg';
  const option_unsubscribed_page            = LL_mailer::_ . '_unsubscribed_page';
  const option_new_post_msg                 = LL_mailer::_ . '_new_post_msg';

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
  const admin_page_subscribers              = LL_mailer::_ . '_subscribers';
  const admin_page_subscriber_edit          = LL_mailer::_ . '_subscribers&edit=';

  const attr_fmt_alt                        = '\s+"([^"]+)"(\s+(fmt|alt)="([^"]*)")?(\s+(fmt|alt)="([^"]*)")?';
  const attr_fmt_alt_html                   = ' "<i>Attribut</i>" fmt="&percnt;s" alt=""';

  const token_CONTENT                       = array('pattern' => '[CONTENT]',
                                                    'html'    => '[CONTENT]'
                                                    );
  const token_CONFIRMATION_URL              = array('pattern' => '[CONFIRMATION_URL]',
                                                    'html'    => '[CONFIRMATION_URL]'
                                                    );
  const token_UNSUBSCRIBE_URL               = array('pattern' => '[UNSUBSCRIBE_URL]',
                                                    'html'    => '[UNSUBSCRIBE_URL]'
                                                    );
  const token_IN_NEW_POST_MAIL              = array('pattern' => '/\[IN_NEW_POST_MAIL\](.*)\[\/IN_NEW_POST_MAIL\]/s',
                                                    'html'    => '[IN_NEW_POST_MAIL]...[/IN_NEW_POST_MAIL]'
                                                    );
  const token_SUBSCRIBER_ATTRIBUTE          = array('pattern' => '/\[SUBSCRIBER' . LL_mailer::attr_fmt_alt . '\]/',
                                                    'html'    => '[SUBSCRIBER' . LL_mailer::attr_fmt_alt_html . ']',
                                                    'filter'  => LL_mailer::_ . '_SUBSCRIBER_attribute',
                                                    'example' => array('[SUBSCRIBER "mail"]',
                                                                       '[SUBSCRIBER "name" fmt="Hallo %s, willkommen" alt="Willkommen"]')
                                                    );
  const token_POST_ATTRIBUTE                = array('pattern' => '/\[POST' . LL_mailer::attr_fmt_alt . '\]/',
                                                    'html'    => '[POST' . LL_mailer::attr_fmt_alt_html . ']',
                                                    'filter'  => LL_mailer::_ . '_POST_attribute',
                                                    'example' => array('[POST "post_title"]',
                                                                       '[POST "post_excerpt" alt="&lt;i&gt;Kein Auszug verfügbar&lt;/i&gt;"]')
                                                    );
  const token_POST_META                     = array('pattern' => '/\[POST_META' . LL_mailer::attr_fmt_alt . ']/',
                                                    'html'    => '[POST_META' . LL_mailer::attr_fmt_alt_html . ']',
                                                    'filter'  => LL_mailer::_ . '_POST_META_attribute',
                                                    'example' => array('[POST_META "plugin-post-meta-key"]',
                                                                       '[POST_META "genre" fmt="Genre: %s&lt;br /&gt;" alt=""]')
                                                    );
                                              
  const shortcode_SUBSCRIPTION_FORM         = array('code'    => 'LL_mailer_SUBSCRIPTION_FORM',
                                                    'html'    => '[LL_mailer_SUBSCRIPTION_FORM html_attr=""]'
                                                    );
  const shortcode_SUBSCRIBER_ATTRIBUTE      = array('code'    => 'LL_mailer_SUBSCRIBER',
                                                    'html'    => '[LL_mailer_SUBSCRIBER "<i>Attribut&nbsp;Slug</i>"]'
                                                    );
  
  const list_item = '<span style="padding: 5px;">&ndash;</span>';
  const arrow_up = '&#x2934;';
  const arrow_down = '&#x2935;';
  const secondary_settings_label = 'style="vertical-align: baseline;"';
  
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

  static function msg_id_new_post_published($post_id) { return 'new-post-published-' . $post_id; }
  static function msg_id_new_subscriber($subscriber_mail) { return 'new-subscriber-' . base64_encode($subscriber_mail); }
  static function msg_id_lost_subscriber($subscriber_mail) { return 'lost-subscriber-' . base64_encode($subscriber_mail); }


  
  static function message($msg, $sticky_id = false)
  {
    $msgs = LL_mailer::get_option_array(LL_mailer::option_msg);
    $msgs[] = array($msg, $sticky_id);
    update_option(LL_mailer::option_msg, $msgs);
  }

  static function hide_message($sticky_id)
  {
    $msgs = LL_mailer::get_option_array(LL_mailer::option_msg);
    foreach ($msgs as $key => &$msg) {
      if ($msg[1] === $sticky_id) {
        unset($msgs[$key]);
      }
    }
    if (empty($msgs)) {
      delete_option(LL_mailer::option_msg);
    }
    else {
      update_option(LL_mailer::option_msg, $msgs);
    }
  }
  
  static function admin_notices()
  {
    // notice
    // notice-error, notice-warning, notice-success or notice-info
    // is-dismissible
    $msgs = LL_mailer::get_option_array(LL_mailer::option_msg);
    if (!empty($msgs)) {
      foreach ($msgs as $key => &$msg) {
        $hide_class = ($msg[1]) ? ' ' . LL_mailer::_ . '_sticky_message' : '';
        echo '<div class="notice notice-info' . $hide_class . '">';
        if ($msg[1]) {
          echo '<p style="float: right; padding-left: 20px;">' .
                '(<a class="' . LL_mailer::_ . '_sticky_message_hide_link" href="' . LL_mailer::json_url() . 'get?hide_message=' . urlencode($msg[1]) . '">' . __('Ausblenden', 'LL_mailer') . '</a>)' .
               '</p>';
        }
        echo '<p>' . nl2br($msg[0]) . '</p></div>';
        if (!$msg[1]) {
          unset($msgs[$key]);
        }
      }
?>
      <script>
        new function() {
          var msg_tags = document.querySelectorAll('.<?=LL_mailer::_?>_sticky_message');
          for (var i = 0; i < msg_tags.length; i++) {
            var msg_tag = msg_tags[i];
            var a_tag = msg_tag.querySelector('.<?=LL_mailer::_?>_sticky_message_hide_link');
            jQuery(a_tag).click(function(e) {
              e.preventDefault();
              this.parentNode.parentNode.style.display = 'none';
              jQuery.get(this.href + '&no_redirect');
            });
          }
        };
      </script>
<?php
      if (empty($msgs)) {
        delete_option(LL_mailer::option_msg);
      }
      else {
        update_option(LL_mailer::option_msg, $msgs);
      }
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
    if (empty($where)) {
      return '';
    }
    $ret = array();
    foreach ($where as $key => &$value) {
      if (is_array($value)) {
        if (isset($value[1])) {
          $ret[] = LL_mailer::escape_key($key) . ' ' . $value[0] . ' ' . LL_mailer::escape_value($value[1]);
        }
        else {
          $ret[] = LL_mailer::escape_key($key) . ' ' . $value[0];
        }
      }
      else {
        $ret[] = LL_mailer::escape_key($key) . ' = ' . LL_mailer::escape_value($value);
      }
    }
    return ' WHERE ' . implode(' AND ', $ret);
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
  
  
  
  static function db_find_post($slug) {
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
  static function db_get_subscribers($what, $confirmed_only = false) { return LL_mailer::_db_select(LL_mailer::table_subscribers, $what, $confirmed_only ? array(LL_mailer::subscriber_attribute_subscribed_at => array('IS NOT NULL')) : array()); }
  
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
    delete_option(LL_mailer::option_confirmation_msg);
    delete_option(LL_mailer::option_confirmed_admin_msg);
    delete_option(LL_mailer::option_confirmed_page);
    delete_option(LL_mailer::option_unsubscribed_admin_msg);
    delete_option(LL_mailer::option_unsubscribed_page);
    delete_option(LL_mailer::option_new_post_msg);
  }



  static function get_post_edit_url($post_id)
  {
    return admin_url('post.php?action=edit&post=' . $post_id);
  }

  static function json_get($request)
  {
    if (isset($request['template'])) {
      return LL_mailer::db_get_template_by_slug($request['template']);
    }
    else if (isset($request['find_post'])) {
      $id = LL_mailer::db_find_post($request['find_post']);
      $url = $id ? LL_mailer::get_post_edit_url($id) : null;
      return array(
        'id'  => $id,
        'url' => $url);
    }
    else if (isset($request['hide_message'])) {
      LL_mailer::hide_message($request['hide_message']);
      if (!isset($request['no_redirect'])) {
        wp_redirect($request->get_header('referer'));
      }
    }
  }



  static function replace_token_using_fmt_and_alt($text, $is_html, $token, &$post, callable $get_value_by_attr, &$replace_dict)
  {
    preg_match_all($token['pattern'], $text, $matches, PREG_SET_ORDER);
    if (!empty($matches)) {
      $FULL = 0;
      $ATTR = 1;
      $html_or_text = $is_html ? 'html' : 'text';
      foreach ($matches as &$match) {
        if (!is_null($replace_dict) && isset($replace_dict['inline'][$html_or_text][$match[$FULL]])) {
          $replacement = $replace_dict['inline'][$html_or_text][$match[$FULL]];
        }
        else {
          $attr = $match[$ATTR];
          foreach (array(3, 6) as $i) switch ($match[$i]) {
            case 'fmt' :
              $fmt = $match[$i + 1];
              break;
            case 'alt' :
              $alt = $match[$i + 1];
              break;
          }

          list($replace_value, $error) = $get_value_by_attr($attr, $match[$FULL]);

          if (!empty($replace_value)) {
            $replace_value_fmt = isset($fmt) ? sprintf($fmt, $replace_value) : $replace_value;
            $replacement = apply_filters($token['filter'], $replace_value_fmt, $replace_value, $fmt, $alt, $attr, $post, $is_html);
          } else if (isset($alt)) {
            $replacement = $alt;
          } else {
            $replacement = $error;
          }

          if (!is_null($replace_dict)) {
            $replace_dict['inline'][$html_or_text][$match[$FULL]] = $replacement;
          }
        }
        $text = str_replace($match[$FULL], $replacement, $text);
      }
    }
    return $text;
  }

  static function replace_token_SUBSCRIBER_ATTRIBUTE($text, $is_html, &$to, &$attributes, &$replace_dict)
  {
    return LL_mailer::replace_token_using_fmt_and_alt($text, $is_html, LL_mailer::token_SUBSCRIBER_ATTRIBUTE, $post,
      function(&$attr, &$found_token) use($to, $attributes) {
        if (in_array($attr, $attributes)) {
          if (isset($to[$attr]) && !empty($to[$attr]))
            return array($to[$attr], '');
          else
            return array('', '');
        }
        else
          return array(null, '(' . sprintf(__('Fehler in %s: "%s" ist kein Abonnenten Attribut', 'LL_mailer'), '<code>' . htmlentities($found_token) . '</code>', $attr) . ')');
      }, $replace_dict);
  }

  static function replace_token_POST_ATTRIBUTE($text, $is_html, &$post, &$post_a, &$replace_dict)
  {
    return LL_mailer::replace_token_using_fmt_and_alt($text, $is_html, LL_mailer::token_POST_ATTRIBUTE, $post,
      function(&$attr, &$found_token) use($post, $post_a) {
        if (array_key_exists($attr, $post_a) && !empty($post_a[$attr]))
          return array($post_a[$attr], '');
        else {
          switch ($attr) {
            case 'url': return array(home_url(user_trailingslashit($post->post_name)), '');
            default: return array(null, '(' . sprintf(__('Fehler in %s: "%s" ist kein WP_Post Attribut', 'LL_mailer'), '<code>' . htmlentities($found_token) . '</code>', $attr) . ')');
          }
        }
      }, $replace_dict);
  }

  static function replace_token_POST_META($text, $is_html, &$post, &$replace_dict)
  {
    return LL_mailer::replace_token_using_fmt_and_alt($text, $is_html, LL_mailer::token_POST_META, $post,
      function(&$attr, &$found_token) use($post) {
        if (metadata_exists('post', $post->ID, $attr))
          return array(get_post_meta($post->ID, $attr, true), '');
        else
          return array(null, '(' . sprintf(__('Fehler in %s: "%s" ist kein Post-Meta Attribut von Post "%s"', 'LL_mailer'), '<code>' . htmlentities($found_token) . '</code>', $attr, $post->post_title) . ')');
      }, $replace_dict);
  }

  static function prepare_mail_for_template($template_slug, &$body_html, &$body_text)
  {
    $template = LL_mailer::db_get_template_by_slug($template_slug);
    $body_html = str_replace(LL_mailer::token_CONTENT['pattern'], $body_html, $template['body_html']);
    $body_text = str_replace(LL_mailer::token_CONTENT['pattern'], $body_text, $template['body_text']);
  }

  static function replace_token_IN_NEW_POST_MAIL($text, $is_new_post_mail, $is_html, $pattern, &$replace_dict)
  {
    preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);
    if (!empty($matches)) {
      $FULL = 0;
      $CONTENT = 1;
      $html_or_text = $is_html ? 'html' : 'text';
      foreach ($matches as &$match) {
        if (!is_null($replace_dict) && isset($replace_dict['block'][$html_or_text][$match[$FULL]])) {
          $replacement = $replace_dict['block'][$html_or_text][$match[$FULL]];
        }
        else {
          if ($is_new_post_mail) {
            $replacement = $match[$CONTENT];
          }
          else {
            $replacement = '';
          }

          if (!is_null($replace_dict)) {
            $replace_dict['block'][$html_or_text][$match[$FULL]] = $replacement;
          }
        }
        $text = str_replace($match[$FULL], $replacement, $text);
      }
    }
    return $text;
  }

  static function prepare_mail_for_new_post_mail($is_new_post_mail, &$body_html, &$body_text, &$replace_dict) {
    $body_html = LL_mailer::replace_token_IN_NEW_POST_MAIL($body_html, $is_new_post_mail, true, LL_mailer::token_IN_NEW_POST_MAIL['pattern'], $replace_dict);
    $body_text = LL_mailer::replace_token_IN_NEW_POST_MAIL($body_text, $is_new_post_mail, false, LL_mailer::token_IN_NEW_POST_MAIL['pattern'], $replace_dict);
  }

  static function prepare_mail_for_receiver($to, &$subject, &$body_html, &$body_text, &$replace_dict)
  {
    $confirm_url = LL_mailer::json_url() . 'confirm-subscription?subscriber=' . urlencode(base64_encode($to[LL_mailer::subscriber_attribute_mail]));
    $body_html = str_replace(LL_mailer::token_CONFIRMATION_URL['pattern'], $confirm_url, $body_html);
    $body_text = str_replace(LL_mailer::token_CONFIRMATION_URL['pattern'], $confirm_url, $body_text);
    $replace_dict['inline']['html'][LL_mailer::token_CONFIRMATION_URL['pattern']] = $confirm_url;
    $replace_dict['inline']['text'][LL_mailer::token_CONFIRMATION_URL['pattern']] = $confirm_url;

    $unsubscribe_url = LL_mailer::json_url() . 'unsubscribe?subscriber=' . urlencode(base64_encode($to[LL_mailer::subscriber_attribute_mail]));
    $body_html = str_replace(LL_mailer::token_UNSUBSCRIBE_URL['pattern'], $unsubscribe_url, $body_html);
    $body_text = str_replace(LL_mailer::token_UNSUBSCRIBE_URL['pattern'], $unsubscribe_url, $body_text);
    $replace_dict['inline']['html'][LL_mailer::token_UNSUBSCRIBE_URL['pattern']] = $unsubscribe_url;
    $replace_dict['inline']['text'][LL_mailer::token_UNSUBSCRIBE_URL['pattern']] = $unsubscribe_url;

    $attributes = array_keys(LL_mailer::get_option_array(LL_mailer::option_subscriber_attributes));
    $subject = LL_mailer::replace_token_SUBSCRIBER_ATTRIBUTE($subject, false, $to, $attributes, $replace_dict);
    $body_html = LL_mailer::replace_token_SUBSCRIBER_ATTRIBUTE($body_html, true, $to, $attributes, $replace_dict);
    $body_text = LL_mailer::replace_token_SUBSCRIBER_ATTRIBUTE($body_text, false, $to, $attributes, $replace_dict);
  }

  static function prepare_mail_for_post($post_id, &$subject, &$body_html, &$body_text, &$replace_dict)
  {
    $post = get_post($post_id);
    $post_a = get_post($post, ARRAY_A);

    $subject = LL_mailer::replace_token_POST_ATTRIBUTE($subject, false, $post, $post_a, $replace_dict);
    $body_html = LL_mailer::replace_token_POST_ATTRIBUTE($body_html, true, $post, $post_a, $replace_dict);
    $body_text = LL_mailer::replace_token_POST_ATTRIBUTE($body_text, false, $post, $post_a, $replace_dict);

    $subject = LL_mailer::replace_token_POST_META($subject, false, $post, $replace_dict);
    $body_html = LL_mailer::replace_token_POST_META($body_html, true, $post, $replace_dict);
    $body_text = LL_mailer::replace_token_POST_META($body_text, false, $post, $replace_dict);

    return $post;
  }

  static function prepare_mail_inline_css(&$body_html)
  {
    require_once LL_mailer::pluginPath() . 'cssin/src/CSSIN.php';
    $cssin = new FM\CSSIN();
    $body_html = $cssin->inlineCSS(site_url(), $body_html);

    $body_html = preg_replace('/class="[^"]*"|class=\'[^\']*\'/i', '', $body_html);
    $body_html = preg_replace('/\\n|\\r|\\r\\n/', '', $body_html);
    $body_html = preg_replace('/>\\s+</i', '><', $body_html);
    $body_html = preg_replace('/\\s\\s+/i', ' ', $body_html);
  }

  static function prepare_mail($msg, $to = null, $post_id = null, $apply_template = true, $inline_css = true)
  {
    if (isset($msg)) {
      if (is_string($msg)) {
        $msg = LL_mailer::db_get_message_by_slug($msg);
        if (is_null($msg)) return __('Nachricht nicht gefunden.', 'LL_mailer');
      }
      $subject = $msg['subject'];
      $body_html = $msg['body_html'];
      $body_text = $msg['body_text'];

      if ($apply_template && !is_null($msg['template_slug'])) {
        LL_mailer::prepare_mail_for_template($msg['template_slug'], $body_html, $body_text);
      }
    }
    else return __('Keine Nachricht angegeben.', 'LL_mailer');

    $replace_dict = array(
      'inline' => array(
        'html' => array(),
        'text' => array()),
      'block' => array(
        'html' => array(),
        'text' => array()));

    LL_mailer::prepare_mail_for_new_post_mail(!is_null($post_id), $body_html, $body_text, $replace_dict);

    if (!is_null($to)) {
      $to = LL_mailer::db_get_subscriber_by_mail($to);
      if (is_null($to)) return __('Empfänger nicht gefunden.', 'LL_mailer');

      LL_mailer::prepare_mail_for_receiver($to, $subject, $body_html, $body_text, $replace_dict);
    }

    $post = null;
    if (!is_null($post_id)) {
      $post = LL_mailer::prepare_mail_for_post($post_id, $subject, $body_html, $body_text, $replace_dict);
    }

    if ($inline_css) {
      LL_mailer::prepare_mail_inline_css($body_html);
    }

    return array($to, $subject, $body_html, $body_text, $replace_dict, $post);
  }

  static function get_sender()
  {
    return array(LL_mailer::subscriber_attribute_mail => get_option(LL_mailer::option_sender_mail),
                 LL_mailer::subscriber_attribute_name => get_option(LL_mailer::option_sender_name));
  }

  static function send_mail($from, $to, $subject, $body_html, $body_text)
  {
    if (empty($from[LL_mailer::subscriber_attribute_mail]) || empty($from[LL_mailer::subscriber_attribute_name]))
    {
      return _('Nachricht nicht gesendet. Fehler: Absender-Name oder E-Mail wurden in den Einstellungen von ' . LL_mailer::_ . ' nicht angegeben.', 'LL_mailer');
    }

    try {
      require_once LL_mailer::pluginPath() . 'phpmailer/Exception.php';
      require_once LL_mailer::pluginPath() . 'phpmailer/PHPMailer.php';
      require_once LL_mailer::pluginPath() . 'phpmailer/SMTP.php';

      $mail = new PHPMailer\PHPMailer\PHPMailer(true /* enable exceptions */);

      $mail->isSendmail();
      $mail->setFrom($from[LL_mailer::subscriber_attribute_mail], $from[LL_mailer::subscriber_attribute_name]);
      $mail->addAddress($to[LL_mailer::subscriber_attribute_mail], $to[LL_mailer::subscriber_attribute_name]);

      // $mail->addEmbeddedImage('img/2u_cs_mini.jpg', 'logo_2u');

      $mail->isHTML(true);
      $mail->Subject = utf8_decode($subject);
      $mail->Body = utf8_decode($body_html);
      $mail->AltBody = utf8_decode($body_text);

      $success = $mail->send();
      return $success ? false : __('Nachricht nicht gesendet. Fehler: PHPMailer hat ein Problem.', 'LL_mailer');

    } catch (PHPMailer\PHPMailer\Exception $e) {
      return __('Nachricht nicht gesendet. Fehler: ', 'LL_mailer') . $mail->ErrorInfo;
    }
  }
  
  static function prepare_and_send_mail($msg_slug, $to, $post_id = null, $receiver_mail_if_different_from_subscriber_mail = null)
  {
    $mail_or_error = LL_mailer::prepare_mail($msg_slug, $to, $post_id, true, true);
    if (is_string($mail_or_error)) return $mail_or_error;
    list($to, $subject, $body_html, $body_text) = $mail_or_error;

    if (!is_null($receiver_mail_if_different_from_subscriber_mail)) {
      $to = $receiver_mail_if_different_from_subscriber_mail;
    }

    return LL_mailer::send_mail(LL_mailer::get_sender(), $to, $subject, $body_html, $body_text);
  }
  
  static function testmail($request)
  {
    if (isset($request['send'])) {
      $error = LL_mailer::prepare_and_send_mail(
        $request['msg'],
        $request['to'],
        $request['post'] ?: null);
      if ($error === false) {
        return __('Testnachricht gesendet.', 'LL_mailer');
      }
      else {
        return $error;
      }
    }
    else if (isset($request['preview'])) {
      $mail_or_error = LL_mailer::prepare_mail(
        $request['msg'],
        $request['to'],
        $request['post'] ?: null,
        true,
        false);
      if (is_string($mail_or_error)) return array('error' => $mail_or_error);
      else {
        list($to, $subject, $body_html, $body_text, $replace_dict) = $mail_or_error;
        return array('subject' => $subject, 'html' => $body_html, 'text' => $body_text, 'replace_dict' => $replace_dict, 'error' => null);
      }
    }
    return null;
  }

  static function new_post_mail($request)
  {
    if (isset($request['post']) && isset($request['to'])) {
      switch ($request['to']) {
        case 'all':
          $msg = get_option(LL_mailer::option_new_post_msg);
          $mail_or_error = LL_mailer::prepare_mail($msg, null, $request['post'], true, false);
          if (is_string($mail_or_error)) return $mail_or_error;
          list($to, $subject, $body_html, $body_text, $replace_dict, $post) = $mail_or_error;

          $from = LL_mailer::get_sender();

          $subscribers = LL_mailer::db_get_subscribers('*', true);
          $error = array();
          foreach ($subscribers as $subscriber) {
            $tmp_subject = $subject;
            $tmp_body_html = $body_html;
            $tmp_body_text = $body_text;
            $tmp_replace_dict = $replace_dict;
            LL_mailer::prepare_mail_for_receiver($subscriber, $tmp_subject, $tmp_body_html, $tmp_body_text, $tmp_replace_dict);
            LL_mailer::prepare_mail_inline_css($tmp_body_html);

            $err = LL_mailer::send_mail($from, $subscriber, $tmp_subject, $tmp_body_html, $tmp_body_text);
            if ($err) $error[] = $err;
          }
          if (!empty($error)) return "Fehler: " . implode('<br />', $error);
          LL_mailer::message(sprintf(__('E-Mails zum Post %s wurden an %d Abonnent(en) versandt.', 'LL_mailer'), '<b>' . get_the_title($post) . '</b>', count($subscribers)));
          LL_mailer::hide_message(LL_mailer::msg_id_new_post_published($post->ID));
          wp_redirect(LL_mailer::get_post_edit_url($post->ID));
          exit;

        default:
          break;
      }
    }
    return null;
  }

  static function confirm_subscriber($subscriber_mail)
  {
    $existing_subscriber = LL_mailer::db_get_subscriber_by_mail($subscriber_mail);
    if (!is_null($existing_subscriber)) {
      LL_mailer::db_confirm_subscriber($subscriber_mail);

      $admin_notification_msg = get_option(LL_mailer::option_confirmed_admin_msg);
      if (!is_null($admin_notification_msg)) {
        $admin_mail = LL_mailer::get_sender();
        $error = LL_mailer::prepare_and_send_mail($admin_notification_msg, $subscriber_mail, null, $admin_mail);
        if ($error !== false) {
          LL_mailer::message($error);
        }
      }

      $display = !empty($existing_subscriber[LL_mailer::subscriber_attribute_name])
        ? $existing_subscriber[LL_mailer::subscriber_attribute_name] . ' (' . $existing_subscriber[LL_mailer::subscriber_attribute_mail] . ')'
        : $existing_subscriber[LL_mailer::subscriber_attribute_mail];
      LL_mailer::message(sprintf(__('%s hat sich für das E-Mail Abo angemeldet.', 'LL_mailer'), '<b>' . $display . '</b>'), LL_mailer::msg_id_new_subscriber($subscriber_mail));

      $confirmed_page = get_option(LL_mailer::option_confirmed_page);
      if (!is_null($confirmed_page)) {
        wp_redirect(get_permalink(get_page_by_path($confirmed_page)) . '?subscriber=' . urlencode(base64_encode($subscriber_mail)));
      }
      else {
        wp_redirect(home_url());
      }
      exit;
    }
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
      
      LL_mailer::db_add_subscriber($new_subscriber);

      $confirmation_msg = get_option(LL_mailer::option_confirmation_msg);
      if (!is_null($confirmation_msg)) {
        $error = LL_mailer::prepare_and_send_mail($confirmation_msg, $new_subscriber[LL_mailer::subscriber_attribute_mail]);
        if ($error === false) {
          $confirmation_sent_page = get_option(LL_mailer::option_confirmation_sent_page);
          if (!is_null($confirmation_sent_page)) {
            wp_redirect(get_permalink(get_page_by_path($confirmation_sent_page)) . '?subscriber=' . urlencode(base64_encode($new_subscriber[LL_mailer::subscriber_attribute_mail])));
          }
          else {
            wp_redirect(home_url());
          }
          exit;
        }
        else {
          return $error;
        }
      }
      else {
        LL_mailer::confirm_subscriber($new_subscriber[LL_mailer::subscriber_attribute_mail]);
      }
    }
    wp_redirect(get_permalink(get_page_by_path(get_option(LL_mailer::option_subscribe_page))));
    exit;
  }
  
  static function confirm_subscription($request)
  {
    if (isset($_GET['subscriber']) && !empty($_GET['subscriber'])) {
      $subscriber_mail = base64_decode(urldecode($_GET['subscriber']));
      LL_mailer::confirm_subscriber($subscriber_mail);
    }
    wp_redirect(get_permalink(get_page_by_path(get_option(LL_mailer::option_subscribe_page))));
    exit;
  }

  static function unsubscribe($request)
  {
    if (isset($_GET['subscriber']) && !empty($_GET['subscriber'])) {
      $subscriber_mail = base64_decode(urldecode($_GET['subscriber']));
      $existing_subscriber = LL_mailer::db_get_subscriber_by_mail($subscriber_mail);
      if (!is_null($existing_subscriber)) {

        $admin_notification_msg = get_option(LL_mailer::option_unsubscribed_admin_msg);
        if (!is_null($admin_notification_msg)) {
          $admin_mail = LL_mailer::get_sender();
          $error = LL_mailer::prepare_and_send_mail($admin_notification_msg, $subscriber_mail, null, $admin_mail);
          if ($error !== false) {
            LL_mailer::message($error);
          }
        }

        LL_mailer::db_delete_subscriber($subscriber_mail);

        $display = !empty($existing_subscriber[LL_mailer::subscriber_attribute_name])
          ? $existing_subscriber[LL_mailer::subscriber_attribute_name] . ' (' . $existing_subscriber[LL_mailer::subscriber_attribute_mail] . ')'
          : $existing_subscriber[LL_mailer::subscriber_attribute_mail];
        LL_mailer::message(sprintf(__('%s hat das E-Mail Abo abgemeldet.', 'LL_mailer'), '<b>' . $display . '</b>'), LL_mailer::msg_id_lost_subscriber($subscriber_mail));

        $unsubscribed_page = get_option(LL_mailer::option_unsubscribed_page);
        if (!is_null($unsubscribed_page)) {
          wp_redirect(get_permalink(get_page_by_path($unsubscribed_page)));
        }
        else {
          wp_redirect(home_url());
        }
        exit;
      }
    }
    wp_redirect(home_url());
    exit;
  }
  
  static function post_status_transition($new_status, $old_status, $post)
  {
    if ($new_status == 'publish' && $old_status != 'publish') {
      LL_mailer::message(sprintf(__('Du hast den Post %s veröffentlicht.', 'LL_mailer'), '<b>' . get_the_title($post) . '</b>') .
                         ' &nbsp; <a href="' . LL_mailer::json_url() . 'new-post-mail?to=all&post=' . $post->ID . '">' . __('Jetzt E-Mail Abonnenten informieren', 'LL_mailer') . '</a>',
                         LL_mailer::msg_id_new_post_published($post->ID));
    }
  }



  static function get_token_description()
  {
    ob_start();
?>
    <style>
      .LL_mailer_token_table td {
        padding: 10px 0 0 0;
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
      <tr><td colspan=2><?=__('In allen E-Mails:', 'LL_mailer')?></td></tr>
      <tr>
        <td><?=LL_mailer::list_item?></td>
        <td><code><?=LL_mailer::token_SUBSCRIBER_ATTRIBUTE['html']?></code></td>
        <td>
          <?=sprintf(__('Abonnenten-Attribut aus den Einstellungen (%s), z.B. %s', 'LL_mailer'),
            '<a href="' . LL_mailer::admin_url() . LL_mailer::admin_page_settings . '" target="_blank">?</a>',
            '<code>' . implode('</code>, <code>', LL_mailer::token_SUBSCRIBER_ATTRIBUTE['example']) . '</code>')?>
        </td>
      </tr>

      <tr><td colspan=2><?=__('In Neuer-Post E-Mails:', 'LL_mailer')?></td></tr>
      <tr>
        <td><?=LL_mailer::list_item?></td>
        <td><code><?=LL_mailer::token_POST_ATTRIBUTE['html']?></code></td>
        <td>
          <?=sprintf(__('WP_Post Attribute (%s), z.B. %s', 'LL_mailer'),
            '<a href="https://codex.wordpress.org/Class_Reference/WP_Post" target="_blank">?</a>',
            '<code>' . implode('</code>, <code>', LL_mailer::token_POST_ATTRIBUTE['example']) . '</code>')?>
          <p><?=__('Zusätzlich verfügbare Attribute', 'LL_mailer')?>: <code>"url"</code></p>
        </td>
      </tr><tr>
        <td><?=LL_mailer::list_item?></td>
        <td><code><?=LL_mailer::token_POST_META['html']?></code></td>
        <td>
          <?=sprintf(__('Individuelle Post-Metadaten (%s), z.B. %s', 'LL_mailer'),
            '<a href="https://codex.wordpress.org/Custom_Fields" target="_blank">?</a>',
            '<code>' . implode('</code>, <code>', LL_mailer::token_POST_META['example']) . '</code>')?>
        </td>
      </tr><tr>
        <td><?=LL_mailer::list_item?></td>
        <td><code><?=LL_mailer::token_IN_NEW_POST_MAIL['html']?></code></td>
        <td>
          <?=__('Ein Textbereich, der nur in E-Mails zu neuen Posts enthalten sein soll, z.B. für einen Abmelden-Link', 'LL_mailer')?>
        </td>
      </tr>

      <tr><td colspan=2><?=__('In Willkommen E-Mails:', 'LL_mailer')?></td></tr>
      <tr>
        <td><?=LL_mailer::list_item?></td>
        <td><code><?=LL_mailer::token_CONFIRMATION_URL['html']?></code></td>
        <td><?=__('URL für Bestätigungs-Link', 'LL_mailer')?></td>
      </tr>
    </table>
<?php
    return ob_get_clean();
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
    $messages = LL_mailer::db_get_messages(array('slug', 'subject'));
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
            <td <?=LL_mailer::secondary_settings_label?>><?=__('Den Blog abonnieren', 'LL_mailer')?></td>
            <td>
              <input type="text" id="<?=LL_mailer::option_subscribe_page?>" name="<?=LL_mailer::option_subscribe_page?>" value="<?=esc_attr(get_option(LL_mailer::option_subscribe_page))?>" placeholder="Seite" class="regular-text" />
              &nbsp; <span id="<?=LL_mailer::option_subscribe_page?>_response"></span>
              <p class="description"><?=sprintf(__('Nutze <code>%s</code> um ein Anmelde-Formular anzuzeigen', 'LL_mailer'), LL_mailer::shortcode_SUBSCRIPTION_FORM['html'])?></p>
            </td>
          </tr>
          <tr>
            <td <?=LL_mailer::secondary_settings_label?>><?=__('Bestätigungs-E-Mail gesendet', 'LL_mailer')?></td>
            <td>
              <input type="text" id="<?=LL_mailer::option_confirmation_sent_page?>" name="<?=LL_mailer::option_confirmation_sent_page?>" value="<?=esc_attr(get_option(LL_mailer::option_confirmation_sent_page))?>" placeholder="Seite" class="regular-text" />
              &nbsp; <span id="<?=LL_mailer::option_confirmation_sent_page?>_response"></span>
            </td>
          </tr>
          <tr>
            <td <?=LL_mailer::secondary_settings_label?>><?=__('E-Mail bestätigt', 'LL_mailer')?></td>
            <td>
              <input type="text" id="<?=LL_mailer::option_confirmed_page?>" name="<?=LL_mailer::option_confirmed_page?>" value="<?=esc_attr(get_option(LL_mailer::option_confirmed_page))?>" placeholder="Seite" class="regular-text" />
              &nbsp; <span id="<?=LL_mailer::option_confirmed_page?>_response"></span>
              <p class="description"><?=sprintf(__('Nutze <code>%s</code> um Attribute des neuen Abonnenten auf der Seite anzuzeigen', 'LL_mailer'), LL_mailer::shortcode_SUBSCRIBER_ATTRIBUTE['html'])?></p>
            </td>
          </tr>
          <tr>
            <td <?=LL_mailer::secondary_settings_label?>><?=__('Abo abgemeldet', 'LL_mailer')?></td>
            <td>
              <input type="text" id="<?=LL_mailer::option_unsubscribed_page?>" name="<?=LL_mailer::option_unsubscribed_page?>" value="<?=esc_attr(get_option(LL_mailer::option_unsubscribed_page))?>" placeholder="Seite" class="regular-text" />
              &nbsp; <span id="<?=LL_mailer::option_unsubscribed_page?>_response"></span>
            </td>
          </tr>
          
          <tr>
            <th scope="row" style="padding-bottom: 0;"><?=__('E-Mails an Abonnenten', 'LL_mailer')?></th>
          </tr>
          <tr>
            <td <?=LL_mailer::secondary_settings_label?>><?=__('Bestätigungs-E-Mail', 'LL_mailer')?></td>
            <td>
              <select id="<?=LL_mailer::option_confirmation_msg?>" name="<?=LL_mailer::option_confirmation_msg?>">
                <option value="">--</option>
<?php
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
          <tr>
            <td <?=LL_mailer::secondary_settings_label?>><?=__('Neuer-Post-E-Mail', 'LL_mailer')?></td>
            <td>
              <select id="<?=LL_mailer::option_new_post_msg?>" name="<?=LL_mailer::option_new_post_msg?>">
                <option value="">--</option>
<?php
              $selected_msg = get_option(LL_mailer::option_new_post_msg);
              foreach ($messages as $msg) {
?>
                <option value="<?=$msg['slug']?>" <?=$msg['slug'] == $selected_msg ? 'selected' : ''?>><?=$msg['subject'] . ' (' . $msg['slug'] . ')'?></option>
<?php
              }
?>
              </select>
              &nbsp;
              <a id="<?=LL_mailer::option_new_post_msg?>_link" href="<?=LL_mailer::admin_url() . LL_mailer::admin_page_message_edit . urlencode($selected_msg)?>">(<?=__('Zur Nachricht', 'LL_mailer')?>)</a>
            </td>
          </tr>

          <tr>
            <th scope="row" style="padding-bottom: 0;"><?=__('E-Mails an dich', 'LL_mailer')?></th>
          </tr>
          <tr>
            <td <?=LL_mailer::secondary_settings_label?>><?=__('Neuer Abonnent', 'LL_mailer')?></td>
            <td>
              <select id="<?=LL_mailer::option_confirmed_admin_msg?>" name="<?=LL_mailer::option_confirmed_admin_msg?>">
                <option value="">--</option>
<?php
              $selected_msg = get_option(LL_mailer::option_confirmed_admin_msg);
              foreach ($messages as $msg) {
?>
                <option value="<?=$msg['slug']?>" <?=$msg['slug'] == $selected_msg ? 'selected' : ''?>><?=$msg['subject'] . ' (' . $msg['slug'] . ')'?></option>
<?php
              }
?>
              </select>
              &nbsp;
              <a id="<?=LL_mailer::option_confirmed_admin_msg?>_link" href="<?=LL_mailer::admin_url() . LL_mailer::admin_page_message_edit . urlencode($selected_msg)?>">(<?=__('Zur Nachricht', 'LL_mailer')?>)</a>
            </td>
          </tr>
          <tr>
            <td <?=LL_mailer::secondary_settings_label?>><?=__('Abonnent abgemeldet', 'LL_mailer')?></td>
            <td>
              <select id="<?=LL_mailer::option_unsubscribed_admin_msg?>" name="<?=LL_mailer::option_unsubscribed_admin_msg?>">
                <option value="">--</option>
<?php
              $selected_msg = get_option(LL_mailer::option_unsubscribed_admin_msg);
              foreach ($messages as $msg) {
?>
                <option value="<?=$msg['slug']?>" <?=$msg['slug'] == $selected_msg ? 'selected' : ''?>><?=$msg['subject'] . ' (' . $msg['slug'] . ')'?></option>
<?php
              }
?>
              </select>
              &nbsp;
              <a id="<?=LL_mailer::option_unsubscribed_admin_msg?>_link" href="<?=LL_mailer::admin_url() . LL_mailer::admin_page_message_edit . urlencode($selected_msg)?>">(<?=__('Zur Nachricht', 'LL_mailer')?>)</a>
            </td>
          </tr>
        </table>
        <?php submit_button(); ?>
      </form>
      <script>
        new function() {
          var timeout = {};
          function check_page_exists(tag_id) {
            var page_input = document.querySelector('#' + tag_id);
            var response_tag = document.querySelector('#' + tag_id + '_response');
            timeout[tag_id] = null;
            function check_now() {
              timeout[tag_id] = null;
              jQuery.getJSON('<?=LL_mailer::json_url()?>get?find_post=' + page_input.value, function(post) {
                if (post.id > 0) {
                  response_tag.innerHTML = '(<a href="' + post.url + '"><?=__('Zur Seite')?></a>)';
                }
                else {
                  response_tag.innerHTML = '<span style="color: red;"><?=__('Seite nicht gefunden', 'LL_mailer')?></span>';
                }
              });
            }
            function check_later() {
              if (timeout[tag_id] !== null) {
                clearTimeout(timeout[tag_id]);
              }
              if (page_input.value === '') {
                response_tag.innerHTML = '';
                return;
              }
              response_tag.innerHTML = '...';
              timeout[tag_id] = setTimeout(check_now, 1000);
            }
            jQuery(page_input).on('input', check_later);
            if (page_input.value !== '') {
              check_now();
            }
          }
          function link_message(tag_id) {
            var message_select = document.querySelector('#' + tag_id);
            var link_tag = document.querySelector('#' + tag_id + '_link');
            function link_now() {
              link_tag.href = '<?=LL_mailer::admin_url() . LL_mailer::admin_page_message_edit?>' + encodeURI(message_select.value);
            }
            jQuery(message_select).on('input', link_now);
            link_now();
          }
          check_page_exists('<?=LL_mailer::option_subscribe_page?>');
          check_page_exists('<?=LL_mailer::option_confirmation_sent_page?>');
          check_page_exists('<?=LL_mailer::option_confirmed_page?>');
          check_page_exists('<?=LL_mailer::option_unsubscribed_page?>');
          link_message('<?=LL_mailer::option_confirmation_msg?>');
          link_message('<?=LL_mailer::option_new_post_msg?>');
          link_message('<?=LL_mailer::option_confirmed_admin_msg?>');
          link_message('<?=LL_mailer::option_unsubscribed_admin_msg?>');
        };
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
    register_setting(LL_mailer::_ . '_general', LL_mailer::option_confirmation_msg);
    register_setting(LL_mailer::_ . '_general', LL_mailer::option_confirmed_admin_msg);
    register_setting(LL_mailer::_ . '_general', LL_mailer::option_confirmed_page);
    register_setting(LL_mailer::_ . '_general', LL_mailer::option_unsubscribed_admin_msg);
    register_setting(LL_mailer::_ . '_general', LL_mailer::option_unsubscribed_page);
    register_setting(LL_mailer::_ . '_general', LL_mailer::option_new_post_msg);
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
              <td <?=LL_mailer::secondary_settings_label?>><?=__('Vorschau (HTML)', 'LL_mailer')?></th>
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
                <?=sprintf(__('Im Layout (HTML und Text) muss %s an der Stelle verwendet werden, an der später die eigentliche Nachricht eingefügt werden soll.', 'LL_mailer'), '<code>' . LL_mailer::token_CONTENT['html'] . '</code>')?>
                <p><?=__('Außerdem können folgende Platzhalter verwendet werden.', 'LL_mailer')?></p>
                <?=LL_mailer::get_token_description()?>
              </td>
            </tr>
          </table>
        </form>
        <script>
          new function() {
            var preview = document.querySelector('#body_html_preview');
            jQuery('[name="body_html"]').on('input', function () {
              preview.contentWindow.document.body.innerHTML = this.value;
            });
          };
        </script>
        
        <hr />
        
        <h1><?=__('Löschen', 'LL_mailer')?></h1>
        
<?php
        $using_messages = LL_mailer::db_get_messages_by_template($template_slug);
        $message_url = LL_mailer::admin_url() . LL_mailer::admin_page_message_edit;
        if (!empty($using_messages)) {
?>
          <p class="description"><?=__('Diese Vorlage kann nicht gelöscht werden, da sie von folgenden Nachrichten verwendet wird:', 'LL_mailer')?></p>
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
              <th scope="row"><?=__('Betreff', 'LL_mailer')?></th>
              <td>
                <input type="text" name="subject" value="<?=esc_attr($message['subject'])?>" placeholder="Betreff" style="width: 100%;" />
              </td>
            </tr>
            <tr>
              <td <?=LL_mailer::secondary_settings_label?>><?=__('Vorschau', 'LL_mailer')?></td>
              <td>
                <input disabled id="subject_preview" type="text" value="<?=esc_attr($message['subject'])?>" style="width: 100%; color: black; background: white;" />
              </td>
            </tr>
            <tr>
              <th scope="row"><?=__('Inhalt (HTML)', 'LL_mailer')?></th>
              <td>
                <textarea name="body_html" style="width: 100%;" rows=10 autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"><?=$message['body_html']?></textarea>
              </td>
            </tr>
            <tr>
              <td <?=LL_mailer::secondary_settings_label?>><?=__('Vorschau (HTML)', 'LL_mailer')?></th>
              <td>
                <iframe id="body_html_preview" style="width: 100%; height: 200px; resize: vertical; border: 1px solid #ddd; background: white;" srcdoc="<?=htmlspecialchars(
                    LL_mailer::html_prefix . $preview_body_html . LL_mailer::html_suffix
                  )?>">
                </iframe>
              </td>
            </tr>
            <tr>
              <th scope="row"><?=__('Inhalt (Text)', 'LL_mailer')?></th>
              <td>
                <textarea name="body_text" style="width: 100%;" rows=10 autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"><?=$message['body_text']?></textarea>
              </td>
            </tr>
            <tr>
              <td <?=LL_mailer::secondary_settings_label?>><?=__('Vorschau (Text)', 'LL_mailer')?></th>
              <td>
                <textarea disabled id="body_text_preview" style="width: 100%; color:black; background: white;" rows=10><?=$preview_body_text?></textarea>
              </td>
            </tr>
            <tr>
              <td style="vertical-align: top;"><?php submit_button(__('Nachricht speichern', 'LL_mailer'), 'primary', '', false); ?></td>
              <td>
                <p><?=__('Im Inhalt (HTML und Text) können folgende Platzhalter verwendet werden.', 'LL_mailer')?></p>
                <?=LL_mailer::get_token_description()?>
              </td>
            </tr>
          </table>
        </form>

        <hr />

        <h1><?=__('Testnachricht', 'LL_mailer')?></h1>
        <br />

<?php
        $subscribers = LL_mailer::db_get_subscribers(array(LL_mailer::subscriber_attribute_mail, LL_mailer::subscriber_attribute_name));
        $test_posts = new WP_Query(array(
          'post_type' => 'post',
//          'orderby' => array(
//            'date' => 'DESC'
//          ),
          'posts_per_page' => 10
        ));
        if (empty($subscribers)) {
?>
          <i><?=__('Es wird mindestens ein Abonnent für die Empfänger-Auswahl benötigt.', 'LL_mailer')?></i>
<?php
        }
        else {
?>
          <form id="<?=LL_mailer::_?>_testmail">
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
            <select id="post" name="post">
              <option value="" style="color: gray;">(<?=__('Kein Test-Post')?>)</option>
<?php
              foreach ($test_posts->posts as $post) {
                $cats = wp_get_post_categories($post->ID);
                $cats = array_map(function($cat) { return get_category($cat)->name; }, $cats);
?>
                <option value="<?=$post->ID?>"><?=$post->post_title?> (<?=implode(', ', $cats)?>)</option>
<?php
              }
?>
            </select>
          </form>
          <p class="description" id="<?=LL_mailer::_?>_testmail_response"></p>
          <p><?php submit_button(__('Test-E-Mail senden', 'LL_mailer'), '', 'send_testmail', false); ?></p>
          <script>
            //new function() {
              var template_select = document.querySelector('[name="template_slug"]');
              var testmail_replace_dict = { block : { text: [], html: [] }, inline: { text: [], html: [] } };
              var template_body_html;
              var template_body_text;
              var template_edit_link = document.querySelector('#LL_mailer_template_edit_link');
              var input_subject = document.querySelector('[name="subject"]');
              var preview_subject = document.querySelector('#subject_preview');
              var textarea_html = document.querySelector('[name="body_html"]');
              var textarea_text = document.querySelector('[name="body_text"]');
              var preview_html = document.querySelector('#body_html_preview');
              var preview_text = document.querySelector('#body_text_preview');
              var show_hide = [template_select, textarea_html, textarea_text];
              if (template_select.value === '') {
                template_body_html = '<?=LL_mailer::token_CONTENT['pattern']?>';
                template_body_text = '<?=LL_mailer::token_CONTENT['pattern']?>';
              }
              else {
                jQuery.getJSON('<?=LL_mailer::json_url()?>get?template=' + template_select.value, function (new_template) {
                  template_body_html = new_template.body_html;
                  template_body_text = new_template.body_text;
                });
              }
              jQuery(input_subject).on('input', function () {
                var text = input_subject.value;
                for (var r in testmail_replace_dict.block.text) text = text.replace(r, testmail_replace_dict.block.text[r], 'g');
                for (var r in testmail_replace_dict.inline.text) text = text.replace(r, testmail_replace_dict.inline.text[r], 'g');
                preview_subject.value = text;
              });
              function update_preview_html() {
                var html = template_body_html.replace('<?=LL_mailer::token_CONTENT['pattern']?>', textarea_html.value, 'g');
                for (var r in testmail_replace_dict.block.html) html = html.replace(r, testmail_replace_dict.block.html[r], 'g');
                for (var r in testmail_replace_dict.inline.html) html = html.replace(r, testmail_replace_dict.inline.html[r], 'g');
                preview_html.contentWindow.document.body.innerHTML = html;
              }
              function update_preview_text() {
                var text = template_body_text.replace('<?=LL_mailer::token_CONTENT['pattern']?>', textarea_text.value, 'g');
                for (var r in testmail_replace_dict.block.text) text = text.replace(r, testmail_replace_dict.block.text[r], 'g');
                for (var r in testmail_replace_dict.inline.text) text = text.replace(r, testmail_replace_dict.inline.text[r], 'g');
                preview_text.value = text;
              }
              jQuery(textarea_html).on('input', function () {
                update_preview_html();
              });
              jQuery(textarea_text).on('input', function () {
                update_preview_text();
              });
              jQuery(template_select).on('input', function () {
                if (template_select.value === '') {
                  template_edit_link.href = '';
                  template_edit_link.style.display = 'none';
                  template_body_html = '<?=LL_mailer::token_CONTENT['pattern']?>';
                  template_body_text = '<?=LL_mailer::token_CONTENT['pattern']?>';
                  update_preview_html();
                  update_preview_text();
                }
                else {
                  for (var i = 0; i < show_hide.length; i++) show_hide[i].disabled = true;
                  jQuery.getJSON('<?=LL_mailer::json_url()?>get?template=' + template_select.value, function (new_template) {
                    template_edit_link.href = '<?=LL_mailer::admin_url() . LL_mailer::admin_page_template_edit?>' + encodeURI(new_template.slug);
                    template_edit_link.style.display = 'inline';
                    template_body_html = new_template.body_html;
                    template_body_text = new_template.body_text;
                    update_preview_html();
                    update_preview_text();
                    for (var i = 0; i < show_hide.length; i++) show_hide[i].disabled = false;
                  });
                }
              });


              var testmail_to_select = document.querySelector('#LL_mailer_testmail #to');
              var testmail_post_select = document.querySelector('#LL_mailer_testmail #post');
              var testmail_response_tag = document.querySelector('#LL_mailer_testmail_response');
              function request_message_preview() {
                testmail_to_select.disabled = true;
                testmail_post_select.disabled = true;
                testmail_response_tag.innerHTML = '...';
                jQuery.getJSON('<?=LL_mailer::json_url() . 'testmail?preview&msg=' . $message_slug . '&to='?>' + encodeURIComponent(testmail_to_select.value) + '&post=' + testmail_post_select.value, function (response) {
                  testmail_to_select.disabled = false;
                  testmail_post_select.disabled = false;
                  if (response.error !== null) {
                    testmail_response_tag.innerHTML = response.error;
                  }
                  else {
                    testmail_response_tag.innerHTML = '<?=__('Vorschau aktualisiert')?>';
                    preview_subject.value = response.subject;
                    preview_html.contentWindow.document.body.innerHTML = response.html;
                    preview_text.value = response.text;
                    testmail_replace_dict = response.replace_dict;
                  }
                });
              }
              jQuery(testmail_to_select).on('input', request_message_preview);
              jQuery(testmail_post_select).on('input', request_message_preview);
              request_message_preview();
              jQuery('#send_testmail').click(function (e) {
                var select_tag = this;
                select_tag.disabled = true;
                testmail_response_tag.innerHTML = '...';
                jQuery.getJSON('<?=LL_mailer::json_url() . 'testmail?send&msg=' . $message_slug . '&to='?>' + encodeURIComponent(testmail_to_select.value) + '&post=' + testmail_post_select.value, function (response) {
                  select_tag.disabled = false;
                  testmail_response_tag.innerHTML = response;
                });
              });
            //};
          </script>
<?php
        }
?>
        
        <hr />
        
        <h1><?=__('Löschen', 'LL_mailer')?></h1>
        
<?php
        if ($message_slug == get_option(LL_mailer::option_confirmation_msg)) {
?>
          <p class="description">
            <?=__('Diese Nachricht kann nicht gelöscht werden, da sie für die Bestätigungs-E-Mail verwendet wird.', 'LL_mailer')?><br />
            (<a href="<?=LL_mailer::admin_url() . LL_mailer::admin_page_settings?>"><?=__('Zu den Einstellungen', 'LL_mailer')?></a>)
          </p>
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
            'template_slug' => $_POST['template_slug'] ?: null,
            'subject' => $_POST['subject'] ?: null,
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
          </table>
          <?php submit_button(__('Abonnent speichern', 'LL_mailer')); ?>
        </form>

        <table class="form-table">
          <tr>
            <th scope="row"><?=__('Abonniert am', 'LL_mailer')?></th>
            <td>
              <?php
              if (isset($subscriber[LL_mailer::subscriber_attribute_subscribed_at])) {
                echo $subscriber[LL_mailer::subscriber_attribute_subscribed_at];
              }
              else {
                ?>
                <i>( <?=__('unbestätigt', 'LL_mailer')?> )</i> &nbsp;
                <form method="post" action="admin-post.php" style="display: inline;">
                  <input type="hidden" name="action" value="<?=LL_mailer::_?>_subscriber_action" />
                  <?php wp_nonce_field(LL_mailer::_ . '_subscriber_manual_confirm'); ?>
                  <input type="hidden" name="subscriber_mail" value="<?=$subscriber_mail?>" />
                  <?php submit_button(__('Bestätigen (E-Mail-Link überspringen)', 'LL_mailer'), '', 'submit', false, array('style' => 'vertical-align: baseline;')); ?>
                </form>
                <?php
              }
              ?>
            </td>
          </tr>
        </table>
        
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
        
        else if (wp_verify_nonce($_POST['_wpnonce'], LL_mailer::_ . '_subscriber_manual_confirm')) {
          LL_mailer::db_confirm_subscriber($subscriber_mail);

          LL_mailer::message(sprintf(__('Abonnent <b>%s</b> bestätigt.', 'LL_mailer'), $subscriber_mail));
          wp_redirect(LL_mailer::admin_url() . LL_mailer::admin_page_subscriber_edit . urlencode($subscriber_mail));
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
    <form action="<?=LL_mailer::json_url()?>subscribe" method="post" <?=$atts['html_attr'] ?: ''?>>
      <table>
<?php
    foreach ($attributes as $attr => $attr_label) {
?>
      <tr>
        <td><?=$attr_label?></td>
        <td><input type="text" name="<?=$attr?>" /></td>
        <td><?=$attr == LL_mailer::subscriber_attribute_mail ? _('(Pflichtfeld)') : ''?></td>
      </tr>
<?php
    }
?>
        <tr><td></td><td><input type="submit" value="<?=__('Jetzt anmelden', 'LL_mailer')?>" class="Button" <?=$atts['button_attr'] ?: ''?> /></td></tr>
      </table>
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

    add_action('transition_post_status', LL_mailer::_('post_status_transition'), 10, 3);

    add_action('rest_api_init', function ()
    {
      register_rest_route('LL_mailer/v1', 'get', array(
        'callback' => LL_mailer::_('json_get')
      ));
      register_rest_route('LL_mailer/v1', 'testmail', array(
        'callback' => LL_mailer::_('testmail')
      ));
      register_rest_route('LL_mailer/v1', 'new-post-mail', array(
        'callback' => LL_mailer::_('new_post_mail')
      ));
      register_rest_route('LL_mailer/v1', 'subscribe', array(
        'callback' => LL_mailer::_('subscribe'),
        'methods' => 'POST'
      ));
      register_rest_route('LL_mailer/v1', 'confirm-subscription', array(
        'callback' => LL_mailer::_('confirm_subscription')
      ));
      register_rest_route('LL_mailer/v1', 'unsubscribe', array(
        'callback' => LL_mailer::_('unsubscribe')
      ));
    });
  }
}

LL_mailer::init_hooks_and_filters();

?>