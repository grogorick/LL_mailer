<?php
/*
Plugin Name:  LL_mailer
Plugin URI:   https://github.com/schtiefel/LL_mailer
Description:  New Post Notification Mail
Version:      1.0
Author:       Steve Grogorick
Author URI:   https://grogorick.de/
License:      GPLv3
License URI:  http://www.gnu.org/licenses/gpl-3.0.html
*/

if (!defined('ABSPATH')) {
  echo '<html><body><span style="font-size: 100vh; font-family: monospace; color: #eee; position: absolute; bottom: 0;">404</span></body></html>';
  http_response_code(404);
  exit;
}



class LL_mailer
{
  const _ = 'LL_mailer';
  
  const option_msg                          = self::_ . '_msg';
  const option_sender_name                  = self::_ . '_sender_name';
  const option_sender_mail                  = self::_ . '_sender_mail';
  const option_subscriber_attributes        = self::_ . '_subscriber_attributes';
  const option_subscribe_page               = self::_ . '_subscribe_page';
  const option_confirmation_sent_page       = self::_ . '_confirmation_sent_page';
  const option_confirmation_msg             = self::_ . '_confirmation_msg';
  const option_confirmed_admin_msg          = self::_ . '_confirmed_admin_msg';
  const option_confirmed_page               = self::_ . '_confirmed_page';
  const option_unsubscribed_admin_msg       = self::_ . '_unsubscribed_admin_msg';
  const option_unsubscribed_page            = self::_ . '_unsubscribed_page';
  const option_new_post_msg                 = self::_ . '_new_post_msg';

  const subscriber_attribute_mail           = 'mail';
  const subscriber_attribute_name           = 'name';
  const subscriber_attribute_subscribed_at  = 'subscribed_at';

  const table_templates                     = self::_ . '_templates';
  const table_messages                      = self::_ . '_messages';
  const table_subscribers                   = self::_ . '_subscribers';
  
  const admin_page_settings                 = self::_ . '_settings';
  const admin_page_templates                = self::_ . '_templates';
  const admin_page_template_edit            = self::_ . '_templates&edit=';
  const admin_page_messages                 = self::_ . '_messages';
  const admin_page_message_edit             = self::_ . '_messages&edit=';
  const admin_page_subscribers              = self::_ . '_subscribers';
  const admin_page_subscriber_edit          = self::_ . '_subscribers&edit=';

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
  const token_SUBSCRIBER_ATTRIBUTE          = array('pattern' => '/\[SUBSCRIBER' . self::attr_fmt_alt . '\]/',
                                                    'html'    => '[SUBSCRIBER' . self::attr_fmt_alt_html . ']',
                                                    'filter'  => self::_ . '_SUBSCRIBER_attribute',
                                                    'example' => array('[SUBSCRIBER "mail"]',
                                                                       '[SUBSCRIBER "name" fmt="Hallo %s, willkommen" alt="Willkommen"]')
                                                    );
  const token_POST_ATTRIBUTE                = array('pattern' => '/\[POST' . self::attr_fmt_alt . '\]/',
                                                    'html'    => '[POST' . self::attr_fmt_alt_html . ']',
                                                    'filter'  => self::_ . '_POST_attribute',
                                                    'example' => array('[POST "post_title"]',
                                                                       '[POST "post_excerpt" alt="&lt;i&gt;Kein Auszug verfügbar&lt;/i&gt;"]')
                                                    );
  const token_POST_META                     = array('pattern' => '/\[POST_META' . self::attr_fmt_alt . '\]/',
                                                    'html'    => '[POST_META' . self::attr_fmt_alt_html . ']',
                                                    'filter'  => self::_ . '_POST_META_attribute',
                                                    'example' => array('[POST_META "plugin-post-meta-key"]',
                                                                       '[POST_META "genre" fmt="Genre: %s&lt;br /&gt;" alt=""]')
                                                    );
  const token_ATTACH                        = array('pattern' => '/\[ATTACH\s+"([^"]+)"\]/',
                                                    'html'    => '[ATTACH "<i>Image-URL</i>"]',
                                                    'example' => array('[ATTACH "/wp-content/uploads/2019/01/some_image.jpg"]')
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
  
  static $error = ['replace_token' => 0];
  
  
  
	static function _($member_function) { return array(self::_, $member_function); }
  
  static function pluginPath() { return plugin_dir_path(__FILE__); }
  static function admin_url() { return get_admin_url() . 'admin.php?page='; }
  static function json_url() { return get_rest_url() . self::_ . '/v1/'; }

  static function get_option_array($option) {
    $val = get_option($option);
    if (empty($val))
      return array();
    return $val;
  }
  
  static function is_predefined_subscriber_attribute($attr) { return in_array($attr, array(self::subscriber_attribute_mail, self::subscriber_attribute_name)); }

  static function make_cid($i) { return 'attachment.' . $i . '@' . $_SERVER["SERVER_NAME"]; }

  static function msg_id_new_post_published($post_id) { return 'new-post-published-' . $post_id; }
  static function msg_id_new_subscriber($subscriber_mail) { return 'new-subscriber-' . base64_encode($subscriber_mail); }
  static function msg_id_lost_subscriber($subscriber_mail) { return 'lost-subscriber-' . base64_encode($subscriber_mail); }
  static function msg_id_new_post_mail_failed($msg_id, $post_id) { return 'new-post-mail-failed-' . $msg_id . '-' . $post_id . '-' . time(); }


  
  static function message($msg, $sticky_id = false)
  {
    $msgs = self::get_option_array(self::option_msg);
    $msgs[] = array($msg, $sticky_id);
    update_option(self::option_msg, $msgs);
  }

  static function hide_message($sticky_id)
  {
    $msgs = self::get_option_array(self::option_msg);
    foreach ($msgs as $key => &$msg) {
      if ($msg[1] === $sticky_id) {
        unset($msgs[$key]);
      }
    }
    if (empty($msgs)) {
      delete_option(self::option_msg);
    }
    else {
      update_option(self::option_msg, $msgs);
    }
  }
  
  static function admin_notices()
  {
    // notice
    // notice-error, notice-warning, notice-success or notice-info
    // is-dismissible
    $msgs = self::get_option_array(self::option_msg);
    if (!empty($msgs)) {
      foreach ($msgs as $key => &$msg) {
        $hide_class = ($msg[1]) ? ' ' . self::_ . '_sticky_message' : '';
        echo '<div class="notice notice-info' . $hide_class . '">';
        if ($msg[1]) {
          echo '<p style="float: right; padding-left: 20px;">' .
                '(<a class="' . self::_ . '_sticky_message_hide_link" href="' . self::json_url() . 'get?hide_message=' . urlencode($msg[1]) . '">' . __('Ausblenden', 'LL_mailer') . '</a>)' .
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
          var msg_tags = document.querySelectorAll('.<?=self::_?>_sticky_message');
          for (var i = 0; i < msg_tags.length; i++) {
            var msg_tag = msg_tags[i];
            var a_tag = msg_tag.querySelector('.<?=self::_?>_sticky_message_hide_link');
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
        delete_option(self::option_msg);
      }
      else {
        update_option(self::option_msg, $msgs);
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
        return self::escape_key($key);
      }, $keys);
    }
    if ($keys != '*')
      return self::escape_key($keys);
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
        return (!is_null($val)) ? self::escape_value($val) : 'NULL';
      }, $values);
    }
    return self::escape_value($values);
  }
  
  static function escape($assoc_array)
  {
    $ret = array();
    foreach ($assoc_array as $key => $val) {
      $ret[self::escape_key($key)] = (!is_null($val)) ? self::escape_value($val) : 'NULL';
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
          $ret[] = self::escape_key($key) . ' ' . $value[0] . ' ' . self::escape_value($value[1]);
        }
        else {
          $ret[] = self::escape_key($key) . ' ' . $value[0];
        }
      }
      else {
        $ret[] = self::escape_key($key) . ' = ' . self::escape_value($value);
      }
    }
    return ' WHERE ' . implode(' AND ', $ret);
  }
  
  static function _db_build_select($table, $what, $where)
  {
    if (is_array($what)) {
      $what = implode(', ', self::escape_keys($what));
    }
    else {
      $what = self::escape_keys($what);
    }
    $sql = 'SELECT ' . $what . ' FROM ' . self::escape_keys($table) . self::build_where($where) . ';';
    // self::message($sql);
    return $sql;
  }
  
  static function _db_insert($table, $data, $timestamp_key = null)
  {
    $data = self::escape($data);
    if (!is_null($timestamp_key))
      $data[self::escape_key($timestamp_key)] = 'NOW()';
    global $wpdb;
    $sql = 'INSERT INTO ' . self::escape_key($wpdb->prefix . $table) . ' ( ' . implode(', ', array_keys($data)) . ' ) VALUES ( ' . implode(', ', array_values($data)) . ' );';
    // self::message($sql);
    return $wpdb->query($sql);
  }
  
  static function _db_update($table, $data, $where, $timestamp_key = null)
  {
    $data = self::escape($data);
    if (!is_null($timestamp_key))
      $data[self::escape_key($timestamp_key)] = 'NOW()';
    global $wpdb;
    $sql = 'UPDATE ' . self::escape_key($wpdb->prefix . $table) . ' SET ' . self::array_zip(' = ', $data, ', ') . self::build_where($where) . ';';
    // self::message($sql);
    return $wpdb->query($sql);
  }
  
  static function _db_delete($table, $where)
  {
    global $wpdb;
    $sql = 'DELETE FROM ' . self::escape_key($wpdb->prefix . $table) . self::build_where($where) . ';';
    // self::message($sql);
    return $wpdb->query($sql);
  }
  
  static function _db_select($table, $what = '*', $where = array())
  {
    global $wpdb;
    return $wpdb->get_results(self::_db_build_select($wpdb->prefix . $table, $what, $where), ARRAY_A);
  }
  
  static function _db_select_row($table, $what = '*', $where = array())
  {
    global $wpdb;
    return $wpdb->get_row(self::_db_build_select($wpdb->prefix . $table, $what, $where), ARRAY_A);
  }
  
  
  
  static function db_find_post($slug) {
    global $wpdb;
    return (int) $wpdb->get_var('SELECT ID FROM ' . $wpdb->posts . self::build_where(array('post_name' => $slug)) . ';');
  }
  
  // templates
  // - slug
  // - body_html
  // - body_text
  // - last_modified
  static function db_add_template($template) { return self::_db_insert(self::table_templates, $template); }
  static function db_update_template($template, $slug) { return self::_db_update(self::table_templates, $template, array('slug' => $slug), 'last_modified'); }
  static function db_delete_template($slug) { return self::_db_delete(self::table_templates, array('slug' => $slug)); }
  static function db_get_template_by_slug($slug) { return self::_db_select_row(self::table_templates, '*', array('slug' => $slug)); }
  static function db_get_templates($what) { return self::_db_select(self::table_templates, $what); }
  
  // messages
  // - slug
  // - subject
  // - template_slug
  // - body_html
  // - body_text
  // - last_modified
  static function db_add_message($message) { return self::_db_insert(self::table_messages, $message); }
  static function db_update_message($message, $slug) { return self::_db_update(self::table_messages, $message, array('slug' => $slug), 'last_modified'); }
  static function db_delete_message($slug) { return self::_db_delete(self::table_messages, array('slug' => $slug)); }
  static function db_get_message_by_slug($slug) { return self::_db_select_row(self::table_messages, '*', array('slug' => $slug)); }
  static function db_get_messages($what) { return self::_db_select(self::table_messages, $what); }
  static function db_get_messages_by_template($template_slug) { return array_map(function($v) { return $v['slug']; }, self::_db_select(self::table_messages, 'slug', array('template_slug' => $template_slug))); }
  
  // subscribers
  // - mail
  // - subscribed_at
  // [...]
  static function db_add_subscriber($subscriber) { return self::_db_insert(self::table_subscribers, $subscriber); }
  static function db_update_subscriber($subscriber, $old_mail) { return self::_db_update(self::table_subscribers, $subscriber, array(self::subscriber_attribute_mail => $old_mail)); }
  static function db_confirm_subscriber($mail) { return self::_db_update(self::table_subscribers, array(), array(self::subscriber_attribute_mail => $mail), self::subscriber_attribute_subscribed_at); }
  static function db_delete_subscriber($mail) { return self::_db_delete(self::table_subscribers, array(self::subscriber_attribute_mail => $mail)); }
  static function db_get_subscriber_by_mail($mail) { return self::_db_select_row(self::table_subscribers, '*', array(self::subscriber_attribute_mail => $mail)); }
  static function db_get_subscribers($what, $confirmed_only = false) { return self::_db_select(self::table_subscribers, $what, $confirmed_only ? array(self::subscriber_attribute_subscribed_at => array('IS NOT NULL')) : array()); }
  
  static function db_subscribers_add_attribute($attr) {
    global $wpdb;
    $sql = 'ALTER TABLE ' . self::escape_keys($wpdb->prefix . self::table_subscribers) . ' ADD ' . self::escape_keys($attr) . ' TEXT NULL DEFAULT NULL;';
    // self::message($sql);
    return $wpdb->query($sql);
  }
  static function db_subscribers_rename_attribute($attr, $new_attr) {
    global $wpdb;
    $sql = 'ALTER TABLE ' . self::escape_keys($wpdb->prefix . self::table_subscribers) . ' CHANGE ' . self::escape_keys($attr) . ' ' . self::escape_keys($new_attr) . ' TEXT;';
    // self::message($sql);
    return $wpdb->query($sql);
  }
  static function db_subscribers_delete_attribute($attr) {
    global $wpdb;
    $sql = 'ALTER TABLE ' . self::escape_keys($wpdb->prefix . self::table_subscribers) . ' DROP ' . self::escape_keys($attr) . ';';
    // self::message($sql);
    return $wpdb->query($sql);
  }
  
  

  static function activate()
  {
    global $wpdb;
    $r = array();

    $r[] = self::table_templates . ' : ' . ($wpdb->query('
      CREATE TABLE ' . self::escape_keys($wpdb->prefix . self::table_templates) . ' (
        `slug` varchar(100) NOT NULL,
        `body_html` text,
        `body_text` text,
        `last_modified` datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (`slug`)
      ) ' . $wpdb->get_charset_collate() . ';') ? 'OK' : $wpdb->last_error);

    $r[] = self::table_messages . ' : ' . ($wpdb->query('
      CREATE TABLE ' . self::escape_keys($wpdb->prefix . self::table_messages) . ' (
        `slug` varchar(100) NOT NULL,
        `subject` tinytext,
        `template_slug` varchar(100),
        `body_html` text,
        `body_text` text,
        `last_modified` datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (`slug`),
        FOREIGN KEY (`template_slug`) REFERENCES ' . self::escape_keys($wpdb->prefix . self::table_templates) . ' (`slug`) ON DELETE RESTRICT ON UPDATE CASCADE
      ) ' . $wpdb->get_charset_collate() . ';') ? 'OK' : $wpdb->last_error);

    $r[] = self::table_subscribers . ' : ' . ($wpdb->query('
      CREATE TABLE ' . self::escape_keys($wpdb->prefix . self::table_subscribers) . ' (
        `' . self::subscriber_attribute_mail . '` varchar(100) NOT NULL,
        `' . self::subscriber_attribute_name . '` TEXT NULL DEFAULT NULL,
        `' . self::subscriber_attribute_subscribed_at . '` datetime NULL DEFAULT NULL,
        PRIMARY KEY (`' . self::subscriber_attribute_mail . '`)
      ) ' . $wpdb->get_charset_collate() . ';') ? 'OK' : $wpdb->last_error);
    
    self::message('Datenbank eingerichtet.<br /><p>- ' . implode('</p><p>- ', $r) . '</p>');
    
    
    add_option(self::option_subscriber_attributes, array(
      self::subscriber_attribute_mail => 'Deine E-Mail Adresse',
      self::subscriber_attribute_name => 'Dein Name'));
    
    self::message('Optionen initialisiert.');
    

    register_uninstall_hook(__FILE__, self::_('uninstall'));
  }
  
  static function uninstall()
  {
    global $wpdb;
    $wpdb->query('DROP TABLE IF EXISTS ' . self::escape_keys($wpdb->prefix . self::table_subscribers) . ';');
    $wpdb->query('DROP TABLE IF EXISTS ' . self::escape_keys($wpdb->prefix . self::table_messages) . ';');
    $wpdb->query('DROP TABLE IF EXISTS ' . self::escape_keys($wpdb->prefix . self::table_templates) . ';');

    delete_option(self::option_msg);
    delete_option(self::option_sender_name);
    delete_option(self::option_sender_mail);
    delete_option(self::option_subscriber_attributes);
    delete_option(self::option_subscribe_page);
    delete_option(self::option_confirmation_sent_page);
    delete_option(self::option_confirmation_msg);
    delete_option(self::option_confirmed_admin_msg);
    delete_option(self::option_confirmed_page);
    delete_option(self::option_unsubscribed_admin_msg);
    delete_option(self::option_unsubscribed_page);
    delete_option(self::option_new_post_msg);
  }



  static function get_post_edit_url($post_id)
  {
    return admin_url('post.php?action=edit&post=' . $post_id);
  }

  static function json_get($request)
  {
    if (isset($request['template'])) {
      return self::db_get_template_by_slug($request['template']);
    }
    else if (isset($request['find_post'])) {
      $id = self::db_find_post($request['find_post']);
      $url = $id ? self::get_post_edit_url($id) : null;
      return array(
        'id'  => $id,
        'url' => $url);
    }
    else if (isset($request['hide_message'])) {
      self::hide_message($request['hide_message']);
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

          if ($is_html) {
            $replacement = nl2br($replacement);
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
    return self::replace_token_using_fmt_and_alt($text, $is_html, self::token_SUBSCRIBER_ATTRIBUTE, $post,
      function(&$attr, &$found_token) use($to, $attributes) {
        if (in_array($attr, $attributes) && isset($to[$attr]) && !empty($to[$attr])) {
          return array($to[$attr], '');
        }
        else {
          self::$error['replace_token']++;
          return array(null, '(' . sprintf(__('Fehler in %s: Abonnenten Attribut "%s" existiert nicht oder ist für diesen Abonnenten nicht gespeichert (nutze alt="")', 'LL_mailer'), '<code>' . $found_token . '</code>', $attr) . ')');
        }
      }, $replace_dict);
  }

  static function replace_token_POST_ATTRIBUTE($text, $is_html, &$post, &$post_a, &$replace_dict)
  {
    return self::replace_token_using_fmt_and_alt($text, $is_html, self::token_POST_ATTRIBUTE, $post,
      function(&$attr, &$found_token) use($post, $post_a) {
        if (array_key_exists($attr, $post_a) && !empty($post_a[$attr]))
          return array($post_a[$attr], '');
        else {
          switch ($attr) {
            case 'url': return array(home_url(user_trailingslashit($post->post_name)), '');
            default:
              self::$error['replace_token']++;
              return array(null, '(' . sprintf(__('Fehler in %s: WP_Post Attribut "%s" existiert nicht oder ist im Post "%s" nicht gespeichert (nutze alt="")', 'LL_mailer'), '<code>' . $found_token . '</code>', $attr, $post->post_title) . ')');
          }
        }
      }, $replace_dict);
  }

  static function replace_token_POST_META($text, $is_html, &$post, &$replace_dict)
  {
    return self::replace_token_using_fmt_and_alt($text, $is_html, self::token_POST_META, $post,
      function(&$attr, &$found_token) use($post) {
        if (metadata_exists('post', $post->ID, $attr))
          return array(get_post_meta($post->ID, $attr, true), '');
        else {
          self::$error['replace_token']++;
          return array(null, '(' . sprintf(__('Fehler in %s: Post-Meta Attribut "%s" existiert nicht oder ist im Post "%s" nicht gespeichert (nutze alt="")', 'LL_mailer'), '<code>' . $found_token . '</code>', $attr, $post->post_title) . ')');
        }
      }, $replace_dict);
  }

  static function prepare_mail_for_template($template_slug, &$body_html, &$body_text)
  {
    $template = self::db_get_template_by_slug($template_slug);
    $body_html = str_replace(self::token_CONTENT['pattern'], $body_html, $template['body_html']);
    $body_text = str_replace(self::token_CONTENT['pattern'], $body_text, $template['body_text']);
  }

  static function replace_token_IN_NEW_POST_MAIL($text, $is_new_post_mail, $is_html, &$replace_dict)
  {
    preg_match_all(self::token_IN_NEW_POST_MAIL['pattern'], $text, $matches, PREG_SET_ORDER);
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

  static function prepare_mail_for_new_post_mail($is_new_post_mail, &$body_html, &$body_text, &$replace_dict)
  {
    $body_html = self::replace_token_IN_NEW_POST_MAIL($body_html, $is_new_post_mail, true, $replace_dict);
    $body_text = self::replace_token_IN_NEW_POST_MAIL($body_text, $is_new_post_mail, false, $replace_dict);
  }

  static function prepare_mail_attachments(&$body_html, $is_preview, &$replace_dict)
  {
    $attachments = array();
    preg_match_all(self::token_ATTACH['pattern'], $body_html, $matches, PREG_SET_ORDER);
    if (!empty($matches)) {
      $FULL = 0;
      $CONTENT = 1;
      foreach ($matches as &$match) {
        if (!is_null($replace_dict) && isset($replace_dict['inline']['html'][$match[$FULL]])) {
          $replacement = $replace_dict['inline']['html'][$match[$FULL]];
        }
        else {
          if ($is_preview) {
            $replacement = $match[$CONTENT];
          }
          else {
            $replacement = self::make_cid(count($attachments));
          }
          $attachments[$replacement] = $match[$CONTENT];

          if (!is_null($replace_dict)) {
            $replace_dict['inline']['html'][$match[$FULL]] = $replacement;
          }
        }
        $body_html = str_replace($match[$FULL], 'cid:' . $replacement, $body_html);
      }
    }
    return $attachments;
  }

  static function prepare_mail_for_receiver($to, &$subject, &$body_html, &$body_text, &$replace_dict)
  {
    $confirm_url = self::json_url() . 'confirm-subscription?subscriber=' . urlencode(base64_encode($to[self::subscriber_attribute_mail]));
    $body_html = str_replace(self::token_CONFIRMATION_URL['pattern'], $confirm_url, $body_html);
    $body_text = str_replace(self::token_CONFIRMATION_URL['pattern'], $confirm_url, $body_text);
    $replace_dict['inline']['html'][self::token_CONFIRMATION_URL['pattern']] = $confirm_url;
    $replace_dict['inline']['text'][self::token_CONFIRMATION_URL['pattern']] = $confirm_url;

    $unsubscribe_url = self::json_url() . 'unsubscribe?subscriber=' . urlencode(base64_encode($to[self::subscriber_attribute_mail]));
    $body_html = str_replace(self::token_UNSUBSCRIBE_URL['pattern'], $unsubscribe_url, $body_html);
    $body_text = str_replace(self::token_UNSUBSCRIBE_URL['pattern'], $unsubscribe_url, $body_text);
    $replace_dict['inline']['html'][self::token_UNSUBSCRIBE_URL['pattern']] = $unsubscribe_url;
    $replace_dict['inline']['text'][self::token_UNSUBSCRIBE_URL['pattern']] = $unsubscribe_url;

    $attributes = array_keys(self::get_option_array(self::option_subscriber_attributes));
    $subject = self::replace_token_SUBSCRIBER_ATTRIBUTE($subject, false, $to, $attributes, $replace_dict);
    $body_html = self::replace_token_SUBSCRIBER_ATTRIBUTE($body_html, true, $to, $attributes, $replace_dict);
    $body_text = self::replace_token_SUBSCRIBER_ATTRIBUTE($body_text, false, $to, $attributes, $replace_dict);
  }

  static function prepare_mail_for_post($post_id, &$subject, &$body_html, &$body_text, &$replace_dict)
  {
    $post = get_post($post_id);
    $post_a = get_post($post, ARRAY_A);

    $subject = self::replace_token_POST_ATTRIBUTE($subject, false, $post, $post_a, $replace_dict);
    $body_html = self::replace_token_POST_ATTRIBUTE($body_html, true, $post, $post_a, $replace_dict);
    $body_text = self::replace_token_POST_ATTRIBUTE($body_text, false, $post, $post_a, $replace_dict);

    $subject = self::replace_token_POST_META($subject, false, $post, $replace_dict);
    $body_html = self::replace_token_POST_META($body_html, true, $post, $replace_dict);
    $body_text = self::replace_token_POST_META($body_text, false, $post, $replace_dict);

    return $post;
  }

  static function prepare_mail_inline_css(&$body_html)
  {
    require_once self::pluginPath() . 'cssin/src/CSSIN.php';
    $cssin = new FM\CSSIN();
    $body_html = $cssin->inlineCSS(site_url(), $body_html);

    $body_html = preg_replace('/class="[^"]*"|class=\'[^\']*\'/i', '', $body_html);
    $body_html = preg_replace('/\\n|\\r|\\r\\n/', '', $body_html);
    $body_html = preg_replace('/>\\s+</i', '><', $body_html);
    $body_html = preg_replace('/\\s\\s+/i', ' ', $body_html);
  }

  static function prepare_mail($msg, $to /* email | null */, $post_id /* ID | null */, $apply_template /* true | false */, $inline_css /* true | false */, $find_and_replace_attachments /* true | 'preview' | false */)
  {
    if (isset($msg)) {
      if (is_string($msg)) {
        $msg = self::db_get_message_by_slug($msg);
        if (is_null($msg)) return __('Nachricht nicht gefunden.', 'LL_mailer');
      }
      $subject = $msg['subject'];
      $body_html = $msg['body_html'];
      $body_text = $msg['body_text'];

      if ($apply_template && !is_null($msg['template_slug'])) {
        self::prepare_mail_for_template($msg['template_slug'], $body_html, $body_text);
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

    self::prepare_mail_for_new_post_mail(!is_null($post_id), $body_html, $body_text, $replace_dict);

    if (!is_null($to)) {
      $to = self::db_get_subscriber_by_mail($to);
      if (is_null($to)) return __('Empfänger nicht gefunden.', 'LL_mailer');

      self::prepare_mail_for_receiver($to, $subject, $body_html, $body_text, $replace_dict);
    }

    $post = null;
    if (!is_null($post_id)) {
      $post = self::prepare_mail_for_post($post_id, $subject, $body_html, $body_text, $replace_dict);
    }

    $attachments = array();
    if ($find_and_replace_attachments !== false) {
      $attachments = self::prepare_mail_attachments($body_html, $find_and_replace_attachments === 'preview', $replace_dict);
    }

    if ($inline_css) {
      self::prepare_mail_inline_css($body_html);
    }

    return array($to, $subject, $body_html, $body_text, $attachments, $replace_dict, $post);
  }

  static function get_sender()
  {
    return array(self::subscriber_attribute_mail => get_option(self::option_sender_mail),
                 self::subscriber_attribute_name => get_option(self::option_sender_name));
  }

  static function send_mail($from, $to, $subject, $body_html, $body_text, $attachments)
  {
    if (empty($from[self::subscriber_attribute_mail]) || empty($from[self::subscriber_attribute_name]))
    {
      return __('Nachricht nicht gesendet. Absender-Name oder E-Mail wurden in den Einstellungen nicht angegeben.', 'LL_mailer');
    }

    try {
      require_once ABSPATH . WPINC . '/class-phpmailer.php';
      require_once ABSPATH . WPINC . '/class-smtp.php';
      $phpmailer = new PHPMailer(true /* enable exceptions */);

      $phpmailer->CharSet = 'utf-8'; // PHPMailer::CHAREST_UTF8;
//      $phpmailer->Encoding = 'quoted-printable';

      $phpmailer->isSendmail();
      $phpmailer->setFrom($from[self::subscriber_attribute_mail], $from[self::subscriber_attribute_name]);
      $phpmailer->addAddress($to[self::subscriber_attribute_mail], $to[self::subscriber_attribute_name]);

      $phpmailer->isHTML(true);
      $phpmailer->Subject = $subject;
      $phpmailer->Body = $body_html;
      $phpmailer->AltBody = $body_text;

      foreach ($attachments as $cid => $url) {
        $phpmailer->addStringEmbeddedImage(file_get_contents($url), $cid, PHPMailer::mb_pathinfo($url, PATHINFO_BASENAME));
      }

      $success = $phpmailer->send();
      return ($success && !$phpmailer->isError()) ? false : sprintf(__('Nachricht nicht gesendet. PHPMailer Fehler: %s', 'LL_mailer'), $phpmailer->ErrorInfo);

    }
    catch (phpmailerException $e) {
      return __('Nachricht nicht gesendet. Fehler: ', 'LL_mailer') . $phpmailer->ErrorInfo;
    }
    catch (Exception $e) {
      return __('Nachricht nicht gesendet. Fehler: ', 'LL_mailer') . $e->getMessage();
    }
  }
  
  static function prepare_and_send_mail($msg_slug, $to, $post_id = null, $receiver_mail_if_different_from_subscriber_mail = null)
  {
    $mail_or_error = self::prepare_mail($msg_slug, $to, $post_id, true, true, true);
    if (is_string($mail_or_error)) return $mail_or_error;
    list($to, $subject, $body_html, $body_text, $attachments) = $mail_or_error;

    if (!is_null($receiver_mail_if_different_from_subscriber_mail)) {
      $to = $receiver_mail_if_different_from_subscriber_mail;
    }

    return self::send_mail(self::get_sender(), $to, $subject, $body_html, $body_text, $attachments);
  }
  
  static function testmail($request)
  {
    if (isset($request['send'])) {
      $error = self::prepare_and_send_mail(
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
      $mail_or_error = self::prepare_mail(
        $request['msg'],
        $request['to'],
        $request['post'] ?: null,
        true,
        false,
        'preview');
      if (is_string($mail_or_error)) return array('error' => $mail_or_error);
      else {
        list($to, $subject, $body_html, $body_text, $attachments, $replace_dict) = $mail_or_error;
        return array('subject' => $subject, 'html' => $body_html, 'text' => $body_text, 'attachments' => $attachments, 'replace_dict' => $replace_dict, 'error' => null);
      }
    }
    return null;
  }

  static function new_post_mail($request)
  {
    if (isset($request['post']) && isset($request['to'])) {
      switch ($request['to']) {
        case 'all':
          $msg = get_option(self::option_new_post_msg);
          $errors = [];
          $txt_goto_message = __('Zur Nachricht', 'LL_mailer');
          $edit_url = self::admin_url() . self::admin_page_message_edit . $msg;
          if (!$msg) {
            $errors[] = __('In den Einstellungen ist keine Nachticht für Neuer-Post-E-Mails ausgewählt.', 'LL_mailer');
          }
          else {
            $mail_or_error = self::prepare_mail($msg, null, $request['post'], true, false, false);
            if (is_string($mail_or_error)) {
              $errors[] = $mail_or_error;
            }
            if (self::$error['replace_token']) {
              $errors[] = sprintf(__('Die Nachricht enthält %d Platzhalter-Fehler.', 'LL_mailer'), self::$error['replace_token']) . ' (<a href="' . $edit_url . '">' . $txt_goto_message . '</a>)<br />' . __('Versandt für alle Abonnenten <b>abgebrochen</b>.', 'LL_mailer');
            }
            if (empty($errors)) {
              list($to, $subject, $body_html, $body_text, $attachments, $replace_dict, $post) = $mail_or_error;

              $from = self::get_sender();
              if (empty($from[self::subscriber_attribute_mail]) || empty($from[self::subscriber_attribute_name]))
              {
                $errors[] = __('Nachricht(en) nicht gesendet. Absender-Name oder E-Mail wurden in den Einstellungen nicht angegeben.', 'LL_mailer');
              }
              else {

                $subscribers = self::db_get_subscribers('*', true);
                $token_errors = [];
                foreach ($subscribers as $subscriber) {
                  $tmp_subject = $subject;
                  $tmp_body_html = $body_html;
                  $tmp_body_text = $body_text;
                  $tmp_replace_dict = $replace_dict;
                  self::prepare_mail_for_receiver($subscriber, $tmp_subject, $tmp_body_html, $tmp_body_text, $tmp_replace_dict);
                  self::prepare_mail_inline_css($tmp_body_html);
                  $tmp_attachments = self::prepare_mail_attachments($tmp_body_html, false, $tmp_replace_dict);

                  if (self::$error['replace_token']) {
                    $token_errors[] = $subscriber[self::subscriber_attribute_name] . ' (' . $subscriber[self::subscriber_attribute_mail] . ')';
                    self::$error['replace_token'] = 0;
                  }
                  else {
                    $err = self::send_mail($from, $subscriber, $tmp_subject, $tmp_body_html, $tmp_body_text, $tmp_attachments);
                    if ($err) $errors[] = $err;
                  }
                }
                if (!empty($token_errors)) {
                  $errors[] = sprintf(__('Die Nachricht enthält Abonnenten-Platzhalter-Fehler.', 'LL_mailer'), $token_errors[0]) . ' (<a href="' . $edit_url . '">' . $txt_goto_message . '</a>)<br />' . __('Versandt für folgende Abonnenten <b>abgebrochen</b>:<br />', 'LL_mailer') . implode("<br />", $token_errors);
                }
              }
            }
          }
          if (!empty($errors)) {
            self::message("Fehler: " . implode('<br />', $errors), self::msg_id_new_post_mail_failed($msg, $request['post']));
          }
          else {
            self::message(sprintf(__('E-Mails zum Post %s wurden an %d Abonnent(en) versandt.', 'LL_mailer'), '<b>' . get_the_title($post) . '</b>', count($subscribers)));
            self::hide_message(self::msg_id_new_post_published($post->ID));
          }
          wp_redirect(wp_get_referer());
          exit;

        default:
          break;
      }
    }
    return null;
  }

  static function confirm_subscriber($subscriber_mail)
  {
    $existing_subscriber = self::db_get_subscriber_by_mail($subscriber_mail);
    if (!is_null($existing_subscriber)) {
      self::db_confirm_subscriber($subscriber_mail);

      $admin_notification_msg = get_option(self::option_confirmed_admin_msg);
      if (!is_null($admin_notification_msg)) {
        $admin_mail = self::get_sender();
        $error = self::prepare_and_send_mail($admin_notification_msg, $subscriber_mail, null, $admin_mail);
        if ($error !== false) {
          self::message($error);
        }
      }

      $display = !empty($existing_subscriber[self::subscriber_attribute_name])
        ? $existing_subscriber[self::subscriber_attribute_name] . ' (' . $existing_subscriber[self::subscriber_attribute_mail] . ')'
        : $existing_subscriber[self::subscriber_attribute_mail];
      self::message(sprintf(__('%s hat sich für das E-Mail Abo angemeldet.', 'LL_mailer'), '<b>' . $display . '</b>'), self::msg_id_new_subscriber($subscriber_mail));

      $confirmed_page = get_option(self::option_confirmed_page);
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
    if (!empty($_POST) && isset($_POST[self::subscriber_attribute_mail]) && !empty($_POST[self::subscriber_attribute_mail])) {
      $attributes = self::get_option_array(self::option_subscriber_attributes);
      $new_subscriber = array();
      foreach ($attributes as $attr => $attr_label) {
        if (!empty($_POST[$attr])) {
          $new_subscriber[$attr] = $_POST[$attr];
        }
      }
      
      self::db_add_subscriber($new_subscriber);

      $confirmation_msg = get_option(self::option_confirmation_msg);
      if (!is_null($confirmation_msg)) {
        $error = self::prepare_and_send_mail($confirmation_msg, $new_subscriber[self::subscriber_attribute_mail]);
        if ($error === false) {
          $confirmation_sent_page = get_option(self::option_confirmation_sent_page);
          if (!is_null($confirmation_sent_page)) {
            wp_redirect(get_permalink(get_page_by_path($confirmation_sent_page)) . '?subscriber=' . urlencode(base64_encode($new_subscriber[self::subscriber_attribute_mail])));
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
        self::confirm_subscriber($new_subscriber[self::subscriber_attribute_mail]);
      }
    }
    wp_redirect(get_permalink(get_page_by_path(get_option(self::option_subscribe_page))));
    exit;
  }
  
  static function confirm_subscription($request)
  {
    if (isset($_GET['subscriber']) && !empty($_GET['subscriber'])) {
      $subscriber_mail = base64_decode(urldecode($_GET['subscriber']));
      self::confirm_subscriber($subscriber_mail);
    }
    wp_redirect(get_permalink(get_page_by_path(get_option(self::option_subscribe_page))));
    exit;
  }

  static function unsubscribe($request)
  {
    if (isset($_GET['subscriber']) && !empty($_GET['subscriber'])) {
      $subscriber_mail = base64_decode(urldecode($_GET['subscriber']));
      $existing_subscriber = self::db_get_subscriber_by_mail($subscriber_mail);
      if (!is_null($existing_subscriber)) {

        $admin_notification_msg = get_option(self::option_unsubscribed_admin_msg);
        if (!is_null($admin_notification_msg)) {
          $admin_mail = self::get_sender();
          $error = self::prepare_and_send_mail($admin_notification_msg, $subscriber_mail, null, $admin_mail);
          if ($error !== false) {
            self::message($error);
          }
        }

        self::db_delete_subscriber($subscriber_mail);

        $display = !empty($existing_subscriber[self::subscriber_attribute_name])
          ? $existing_subscriber[self::subscriber_attribute_name] . ' (' . $existing_subscriber[self::subscriber_attribute_mail] . ')'
          : $existing_subscriber[self::subscriber_attribute_mail];
        self::message(sprintf(__('%s hat das E-Mail Abo abgemeldet.', 'LL_mailer'), '<b>' . $display . '</b>'), self::msg_id_lost_subscriber($subscriber_mail));

        $unsubscribed_page = get_option(self::option_unsubscribed_page);
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
      self::message(sprintf(__('Du hast den Post %s veröffentlicht.', 'LL_mailer'), '<b>' . get_the_title($post) . '</b>') .
                         ' &nbsp; <a href="' . self::json_url() . 'new-post-mail?to=all&post=' . $post->ID . '">' . __('Jetzt E-Mail Abonnenten informieren', 'LL_mailer') . '</a>',
                         self::msg_id_new_post_published($post->ID));
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
    <table class="<?=self::_?>_token_table">
      <tr><td colspan=2><?=__('In Neuer-Post E-Mails:', 'LL_mailer')?></td></tr>
      <tr>
        <td><?=self::list_item?></td>
        <td><code><?=self::token_POST_ATTRIBUTE['html']?></code></td>
        <td>
          <?=sprintf(__('WP_Post Attribute (%s), z.B. %s.', 'LL_mailer'),
            '<a href="https://codex.wordpress.org/Class_Reference/WP_Post" target="_blank">?</a>',
            '<code>' . implode('</code>, <code>', self::token_POST_ATTRIBUTE['example']) . '</code>')?>
          <p><?=__('Zusätzlich verfügbare Attribute: ', 'LL_mailer')?><code>"url"</code></p>
        </td>
      </tr><tr>
        <td><?=self::list_item?></td>
        <td><code><?=self::token_POST_META['html']?></code></td>
        <td>
          <?=sprintf(__('Individuelle Post-Metadaten (%s), z.B. %s.', 'LL_mailer'),
            '<a href="https://codex.wordpress.org/Custom_Fields" target="_blank">?</a>',
            '<code>' . implode('</code>, <code>', self::token_POST_META['example']) . '</code>')?>
        </td>
      </tr><tr>
        <td><?=self::list_item?></td>
        <td><code><?=self::token_IN_NEW_POST_MAIL['html']?></code></td>
        <td>
          <?=__('Ein Textbereich, der nur in E-Mails zu neuen Posts enthalten sein soll, z.B. für einen Abmelden-Link.', 'LL_mailer')?>
        </td>
      </tr>

      <tr><td colspan=2><?=__('In allen E-Mails:', 'LL_mailer')?></td></tr>
      <tr>
        <td><?=self::list_item?></td>
        <td><code><?=self::token_SUBSCRIBER_ATTRIBUTE['html']?></code></td>
        <td>
          <?=sprintf(__('Abonnenten-Attribut aus den Einstellungen (%s), z.B. %s.', 'LL_mailer'),
            '<a href="' . self::admin_url() . self::admin_page_settings . '" target="_blank">?</a>',
            '<code>' . implode('</code>, <code>', self::token_SUBSCRIBER_ATTRIBUTE['example']) . '</code>')?>
        </td>
      </tr><tr>
        <td><?=self::list_item?></td>
        <td><code><?=self::token_ATTACH['html']?></code></td>
        <td>
          <?=sprintf(__('Bild (URL) als Anhang einbetten, z.B. %s.', 'LL_mailer'),
            '<code>' . implode('</code>, <code>', self::token_ATTACH['example']) . '</code>')?>
          <p><?=__('(Wird in vielen E-Mail Programmen leider nicht angezeigt)', 'LL_mailer')?></p>
        </td>
      </tr><tr>
        <td><?=self::list_item?></td>
        <td><code><?=self::token_CONFIRMATION_URL['html']?></code></td>
        <td><?=__('URL für Bestätigungs-Link bei der Anmeldung zum Abo.', 'LL_mailer')?></td>
      </tr><tr>
        <td><?=self::list_item?></td>
        <td><code><?=self::token_UNSUBSCRIBE_URL['html']?></code></td>
        <td><?=__('URL zur Abmeldung vom Abo.', 'LL_mailer')?></td>
      </tr>
    </table>
<?php
    return ob_get_clean();
  }



  static function admin_menu()
  {
    $required_capability = 'administrator';
    add_menu_page(self::_,                      self::_,                  $required_capability, self::admin_page_settings,    self::_('admin_page_settings'), plugins_url('/icon.png', __FILE__));
    add_submenu_page(self::admin_page_settings, self::_, 'Einstellungen', $required_capability, self::admin_page_settings,    self::_('admin_page_settings'));
    add_submenu_page(self::admin_page_settings, self::_, 'Vorlagen',      $required_capability, self::admin_page_templates,   self::_('admin_page_templates'));
    add_submenu_page(self::admin_page_settings, self::_, 'Nachrichten',   $required_capability, self::admin_page_messages,    self::_('admin_page_messages'));
    add_submenu_page(self::admin_page_settings, self::_, 'Abonnenten',    $required_capability, self::admin_page_subscribers, self::_('admin_page_subscribers'));

    add_action('admin_init', self::_('admin_page_settings_general_action'));
  }

  
  
  static function admin_page_settings()
  {
    $messages = self::db_get_messages(array('slug', 'subject'));
?>
    <div class="wrap">
      <h1><?=__('Allgemeine Einstellungen', 'LL_mailer')?></h1>

      <form method="post" action="options.php">
        <?php settings_fields(self::_ . '_general'); ?>
        <table class="form-table">
          <tr>
            <th scope="row"><?=__('Absender', 'LL_mailer')?></th>
            <td>
              <input type="text" name="<?=self::option_sender_name?>" value="<?=esc_attr(get_option(self::option_sender_name))?>" placeholder="Name" class="regular-text" />
              <input type="text" name="<?=self::option_sender_mail?>" value="<?=esc_attr(get_option(self::option_sender_mail))?>" placeholder="E-Mail" class="regular-text" />
              &nbsp; <span id="<?=self::option_sender_mail?>_response"></span>
            </td>
          </tr>
          
          <tr>
            <th scope="row" style="padding-bottom: 0;"><?=__('Blog-Seiten', 'LL_mailer')?></th>
          </tr>
          <tr>
            <td <?=self::secondary_settings_label?>><?=__('Den Blog abonnieren', 'LL_mailer')?></td>
            <td>
              <input type="text" id="<?=self::option_subscribe_page?>" name="<?=self::option_subscribe_page?>" value="<?=esc_attr(get_option(self::option_subscribe_page))?>" placeholder="Seite" class="regular-text" />
              &nbsp; <span id="<?=self::option_subscribe_page?>_response"></span>
              <p class="description"><?=sprintf(__('Nutze <code>%s</code> um ein Anmelde-Formular anzuzeigen', 'LL_mailer'), self::shortcode_SUBSCRIPTION_FORM['html'])?></p>
            </td>
          </tr>
          <tr>
            <td <?=self::secondary_settings_label?>><?=__('Bestätigungs-E-Mail gesendet', 'LL_mailer')?></td>
            <td>
              <input type="text" id="<?=self::option_confirmation_sent_page?>" name="<?=self::option_confirmation_sent_page?>" value="<?=esc_attr(get_option(self::option_confirmation_sent_page))?>" placeholder="Seite" class="regular-text" />
              &nbsp; <span id="<?=self::option_confirmation_sent_page?>_response"></span>
            </td>
          </tr>
          <tr>
            <td <?=self::secondary_settings_label?>><?=__('E-Mail bestätigt', 'LL_mailer')?></td>
            <td>
              <input type="text" id="<?=self::option_confirmed_page?>" name="<?=self::option_confirmed_page?>" value="<?=esc_attr(get_option(self::option_confirmed_page))?>" placeholder="Seite" class="regular-text" />
              &nbsp; <span id="<?=self::option_confirmed_page?>_response"></span>
              <p class="description"><?=sprintf(__('Nutze <code>%s</code> um Attribute des neuen Abonnenten auf der Seite anzuzeigen', 'LL_mailer'), self::shortcode_SUBSCRIBER_ATTRIBUTE['html'])?></p>
            </td>
          </tr>
          <tr>
            <td <?=self::secondary_settings_label?>><?=__('Abo abgemeldet', 'LL_mailer')?></td>
            <td>
              <input type="text" id="<?=self::option_unsubscribed_page?>" name="<?=self::option_unsubscribed_page?>" value="<?=esc_attr(get_option(self::option_unsubscribed_page))?>" placeholder="Seite" class="regular-text" />
              &nbsp; <span id="<?=self::option_unsubscribed_page?>_response"></span>
            </td>
          </tr>
          
          <tr>
            <th scope="row" style="padding-bottom: 0;"><?=__('E-Mails an Abonnenten', 'LL_mailer')?></th>
          </tr>
          <tr>
            <td <?=self::secondary_settings_label?>><?=__('Bestätigungs-E-Mail', 'LL_mailer')?></td>
            <td>
              <select id="<?=self::option_confirmation_msg?>" name="<?=self::option_confirmation_msg?>">
                <option value="">--</option>
<?php
              $selected_msg = get_option(self::option_confirmation_msg);
              foreach ($messages as $msg) {
?>
                <option value="<?=$msg['slug']?>" <?=$msg['slug'] == $selected_msg ? 'selected' : ''?>><?=$msg['subject'] . ' (' . $msg['slug'] . ')'?></option>
<?php
              }
?>
              </select>
              &nbsp;
              <a id="<?=self::option_confirmation_msg?>_link" href="<?=self::admin_url() . self::admin_page_message_edit . urlencode($selected_msg)?>">(<?=__('Zur Nachricht', 'LL_mailer')?>)</a>
            </td>
          </tr>
          <tr>
            <td <?=self::secondary_settings_label?>><?=__('Neuer-Post-E-Mail', 'LL_mailer')?></td>
            <td>
              <select id="<?=self::option_new_post_msg?>" name="<?=self::option_new_post_msg?>">
                <option value="">--</option>
<?php
              $selected_msg = get_option(self::option_new_post_msg);
              foreach ($messages as $msg) {
?>
                <option value="<?=$msg['slug']?>" <?=$msg['slug'] == $selected_msg ? 'selected' : ''?>><?=$msg['subject'] . ' (' . $msg['slug'] . ')'?></option>
<?php
              }
?>
              </select>
              &nbsp;
              <a id="<?=self::option_new_post_msg?>_link" href="<?=self::admin_url() . self::admin_page_message_edit . urlencode($selected_msg)?>">(<?=__('Zur Nachricht', 'LL_mailer')?>)</a>
            </td>
          </tr>

          <tr>
            <th scope="row" style="padding-bottom: 0;"><?=__('E-Mails an dich', 'LL_mailer')?></th>
          </tr>
          <tr>
            <td <?=self::secondary_settings_label?>><?=__('Neuer Abonnent', 'LL_mailer')?></td>
            <td>
              <select id="<?=self::option_confirmed_admin_msg?>" name="<?=self::option_confirmed_admin_msg?>">
                <option value="">--</option>
<?php
              $selected_msg = get_option(self::option_confirmed_admin_msg);
              foreach ($messages as $msg) {
?>
                <option value="<?=$msg['slug']?>" <?=$msg['slug'] == $selected_msg ? 'selected' : ''?>><?=$msg['subject'] . ' (' . $msg['slug'] . ')'?></option>
<?php
              }
?>
              </select>
              &nbsp;
              <a id="<?=self::option_confirmed_admin_msg?>_link" href="<?=self::admin_url() . self::admin_page_message_edit . urlencode($selected_msg)?>">(<?=__('Zur Nachricht', 'LL_mailer')?>)</a>
            </td>
          </tr>
          <tr>
            <td <?=self::secondary_settings_label?>><?=__('Abonnent abgemeldet', 'LL_mailer')?></td>
            <td>
              <select id="<?=self::option_unsubscribed_admin_msg?>" name="<?=self::option_unsubscribed_admin_msg?>">
                <option value="">--</option>
<?php
              $selected_msg = get_option(self::option_unsubscribed_admin_msg);
              foreach ($messages as $msg) {
?>
                <option value="<?=$msg['slug']?>" <?=$msg['slug'] == $selected_msg ? 'selected' : ''?>><?=$msg['subject'] . ' (' . $msg['slug'] . ')'?></option>
<?php
              }
?>
              </select>
              &nbsp;
              <a id="<?=self::option_unsubscribed_admin_msg?>_link" href="<?=self::admin_url() . self::admin_page_message_edit . urlencode($selected_msg)?>">(<?=__('Zur Nachricht', 'LL_mailer')?>)</a>
            </td>
          </tr>
        </table>
        <?php submit_button(); ?>
      </form>
      <script>
        new function() {
          var timeout = {};
          function check_sender() {
            var mail_tag = document.querySelector('[name="<?=self::option_sender_mail?>"]');
            var response_tag = document.querySelector('#<?=self::option_sender_mail?>_response');
            function check_now() {
              if (/\S+@\S+\.\S+/.test(mail_tag.value)) {
                response_tag.innerHTML = '';
              }
              else {
                response_tag.innerHTML = '<span style="color: red;"><?=__('E-Mail Adresse ungültig', 'LL_mailer')?></span>';
              }
            }
            jQuery(mail_tag).on('input', check_now);
            check_now();
          }
          function check_page_exists(tag_id) {
            var page_input = document.querySelector('#' + tag_id);
            var response_tag = document.querySelector('#' + tag_id + '_response');
            timeout[tag_id] = null;
            function check_now() {
              timeout[tag_id] = null;
              jQuery.getJSON('<?=self::json_url()?>get?find_post=' + page_input.value, function(post) {
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
              if (message_select.value !== '') {
                link_tag.href = '<?=self::admin_url() . self::admin_page_message_edit?>' + encodeURI(message_select.value);
                link_tag.style.display = '';
              }
              else {
                link_tag.href = '';
                link_tag.style.display = 'none';
              }
            }
            jQuery(message_select).on('input', link_now);
            link_now();
          }
          check_sender();
          check_page_exists('<?=self::option_subscribe_page?>');
          check_page_exists('<?=self::option_confirmation_sent_page?>');
          check_page_exists('<?=self::option_confirmed_page?>');
          check_page_exists('<?=self::option_unsubscribed_page?>');
          link_message('<?=self::option_confirmation_msg?>');
          link_message('<?=self::option_new_post_msg?>');
          link_message('<?=self::option_confirmed_admin_msg?>');
          link_message('<?=self::option_unsubscribed_admin_msg?>');
        };
      </script>
      <hr />
      <table class="form-table">
        <tr>
          <th scope="row"><?=__('Abonnenten-Attribute', 'LL_mailer')?></th>
          <td>
<?php
            $attributes = self::get_option_array(self::option_subscriber_attributes);
            $attribute_groups = array(
              'predefined' => array(
                self::subscriber_attribute_mail => $attributes[self::subscriber_attribute_mail],
                self::subscriber_attribute_name => $attributes[self::subscriber_attribute_name]),
              'dynamic' => array_filter($attributes, function($key) { return !self::is_predefined_subscriber_attribute($key); }, ARRAY_FILTER_USE_KEY));
?>
            <style>
              .LL_mailer_attributes_table td {
                padding-top: 5px;
                padding-bottom: 0px;
              }
            </style>
            <table class="<?=self::_?>_attributes_table">
            <tr><td><?=__('Attribut Label', 'LL_mailer')?></td><td><?=__('Attribut Slug', 'LL_mailer')?></td></tr>
<?php
            foreach ($attribute_groups as $group => &$attrs) {
              foreach ($attrs as $attr => $attr_label) {
?>
              <tr><td>
                <form method="post" action="admin-post.php" style="display: inline;">
                  <input type="hidden" name="action" value="<?=self::_?>_settings_action" />
                  <?php wp_nonce_field(self::_ . '_subscriber_attribute_edit'); ?>
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
                    <input type="hidden" name="action" value="<?=self::_?>_settings_action" />
                    <?php wp_nonce_field(self::_ . '_subscriber_attribute_delete'); ?>
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
                  <input type="hidden" name="action" value="<?=self::_?>_settings_action" />
                  <?php wp_nonce_field(self::_ . '_subscriber_attribute_add'); ?>
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
    register_setting(self::_ . '_general', self::option_sender_name);
    register_setting(self::_ . '_general', self::option_sender_mail);
    register_setting(self::_ . '_general', self::option_subscribe_page);
    register_setting(self::_ . '_general', self::option_confirmation_sent_page);
    register_setting(self::_ . '_general', self::option_confirmation_msg);
    register_setting(self::_ . '_general', self::option_confirmed_admin_msg);
    register_setting(self::_ . '_general', self::option_confirmed_page);
    register_setting(self::_ . '_general', self::option_unsubscribed_admin_msg);
    register_setting(self::_ . '_general', self::option_unsubscribed_page);
    register_setting(self::_ . '_general', self::option_new_post_msg);
  }

  static function admin_page_settings_action()
  {
    if (!empty($_POST) && isset($_POST['_wpnonce'])) {
      $attribute = trim($_POST['attribute']);
      if (!empty($attribute)) {
        if (wp_verify_nonce($_POST['_wpnonce'], self::_ . '_subscriber_attribute_add')) {
          $attribute_label = $attribute;
          $attribute = sanitize_title($attribute);
          if (!empty($attribute)) {
            $attributes = self::get_option_array(self::option_subscriber_attributes);
            if (in_array($attribute, array_keys($attributes))) {
              self::message(sprintf(__('Ein Abonnenten-Attribut <b>%s</b> existiert bereits.', 'LL_mailer'), $attribute_label));
              wp_redirect(self::admin_url() . self::admin_page_settings);
              exit;
            }
            
            $attributes[$attribute] = $attribute_label;
            update_option(self::option_subscriber_attributes, $attributes);
            $r = self::db_subscribers_add_attribute($attribute);
            
            self::message(sprintf(__('Neues Abonnenten-Attribut <b>%s</b> hinzugefügt.', 'LL_mailer'), $attribute_label));
          }
        }
        else if (wp_verify_nonce($_POST['_wpnonce'], self::_ . '_subscriber_attribute_edit')) {
          $new_attribute_label = trim($_POST['new_attribute_label']);
          $new_attribute = sanitize_title($new_attribute_label);
          if (!empty($new_attribute_label) && !empty($new_attribute)) {
            $attributes = self::get_option_array(self::option_subscriber_attributes);
            if ($new_attribute != $attribute && in_array($new_attribute, array_keys($attributes))) {
              self::message(sprintf(__('Ein Abonnenten-Attribut <b>%s</b> existiert bereits.', 'LL_mailer'), $new_attribute_label));
              wp_redirect(self::admin_url() . self::admin_page_settings);
              exit;
            }
            
            $attribute_label = $attributes[$attribute];
            if (self::is_predefined_subscriber_attribute($attribute)) {
              $attributes[$attribute] = $new_attribute_label;
            } else {
              unset($attributes[$attribute]);
              $attributes[$new_attribute] = $new_attribute_label;
              self::db_subscribers_rename_attribute($attribute, $new_attribute);
            }
            update_option(self::option_subscriber_attributes, $attributes);
            
            self::message(sprintf(__('Abonnenten-Attribut <b>%s</b> in <b>%s</b> umbenannt.', 'LL_mailer'), $attribute_label, $new_attribute_label));
          }
        }
        else if (wp_verify_nonce($_POST['_wpnonce'], self::_ . '_subscriber_attribute_delete')) {
          $attributes = self::get_option_array(self::option_subscriber_attributes);
          
          $attribute_label = $attributes[$attribute];
          unset($attributes[$attribute]);
          update_option(self::option_subscriber_attributes, $attributes);
          self::db_subscribers_delete_attribute($attribute);
          
          self::message(sprintf(__('Abonnenten-Attribut <b>%s</b> gelöscht.', 'LL_mailer'), $attribute_label));
        }
      }
    }
    wp_redirect(self::admin_url() . self::admin_page_settings);
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
          <input type="hidden" name="action" value="<?=self::_?>_template_action" />
          <?php wp_nonce_field(self::_ . '_template_add'); ?>
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
          $templates = self::db_get_templates(array('slug', 'last_modified'));
          $edit_url = self::admin_url() . self::admin_page_template_edit;
          foreach ($templates as &$template) {
?>
            <?=self::list_item?> <a href="<?=$edit_url . $template['slug']?>"><b><?=$template['slug']?></b></a> &nbsp; <span style="color: gray;">( <?=__('zuletzt bearbeitet: ', 'LL_mailer') . $template['last_modified']?> )</span><br />
<?php
          }
?>
        </p>
<?php
      } break;
      
      case 'edit':
      {
        $template_slug = $_GET['edit'];
        $template = self::db_get_template_by_slug($template_slug);
        if (empty($template)) {
          self::message(sprintf(__('Es existiert keine Vorlage <b>%s</b>.', 'LL_mailer'), $template_slug));
          wp_redirect(self::admin_url() . self::admin_page_templates);
          exit;
        }
?>
        <h1><?=__('Vorlagen', 'LL_mailer')?> &gt; <?=$template_slug?></h1>

        <form method="post" action="admin-post.php">
          <input type="hidden" name="action" value="<?=self::_?>_template_action" />
          <?php wp_nonce_field(self::_ . '_template_edit'); ?>
          <input type="hidden" name="template_slug" value="<?=$template_slug?>" />
          <table class="form-table">
            <tr>
              <th scope="row"><?=__('Layout (HTML)', 'LL_mailer')?></th>
              <td>
                <textarea name="body_html" style="width: 100%;" rows=10 autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"><?=$template['body_html']?></textarea>
              </td>
            </tr>
            <tr>
              <td <?=self::secondary_settings_label?>><?=__('Vorschau (HTML)', 'LL_mailer')?></th>
              <td>
                <iframe id="body_html_preview" style="width: 100%; height: 200px; resize: vertical; border: 1px solid #ddd; background: white;" srcdoc="<?=htmlspecialchars(
                    self::html_prefix . $template['body_html'] . self::html_suffix
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
                <?=sprintf(__('Im Layout (HTML und Text) muss %s an der Stelle verwendet werden, an der später die eigentliche Nachricht eingefügt werden soll.', 'LL_mailer'), '<code>' . self::token_CONTENT['html'] . '</code>')?>
                <p><?=__('Außerdem können folgende Platzhalter verwendet werden.', 'LL_mailer')?></p>
                <?=self::get_token_description()?>
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
        $using_messages = self::db_get_messages_by_template($template_slug);
        $message_url = self::admin_url() . self::admin_page_message_edit;
        if (!empty($using_messages)) {
?>
          <p class="description"><?=__('Diese Vorlage kann nicht gelöscht werden, da sie von folgenden Nachrichten verwendet wird:', 'LL_mailer')?></p>
          <ul>
            <?=implode('<br />', array_map(function($v) use ($message_url) { return self::list_item . ' <a href="' . $message_url . $v . '">' . $v . '</a>'; }, $using_messages))?>
          </ul>
<?php
        } else {
?>
          <form method="post" action="admin-post.php">
            <input type="hidden" name="action" value="<?=self::_?>_template_action" />
            <?php wp_nonce_field(self::_ . '_template_delete'); ?>
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
        if (wp_verify_nonce($_POST['_wpnonce'], self::_ . '_template_add')) {
          $template_slug = sanitize_title($template_slug);
          if (empty($template_slug)) {
            self::message(sprintf(__('<b>%s</b> kann nicht als Vorlagen-Slug verwendet werden.', 'LL_mailer'), $template_slug));
            wp_redirect(self::admin_url() . self::admin_page_templates);
            exit;
          }
          
          $existing_template = self::db_get_template_by_slug($template_slug);
          if (!empty($existing_template)) {
            self::message(sprintf(__('Die Vorlage <b>%s</b> existiert bereits.', 'LL_mailer'), $template_slug));
            wp_redirect(self::admin_url() . self::admin_page_templates);
            exit;
          }
          
          self::db_add_template(array(
            'slug' => $template_slug,
            'body_html' => "...<br />\n" . self::token_CONTENT['pattern'] . "\n<hr />...",
            'body_text' => "...\n" . self::token_CONTENT['pattern'] . "\n----------\n..."));
          
          self::message(sprintf(__('Neue Vorlage <b>%s</b> angelegt.', 'LL_mailer'), $template_slug));
          wp_redirect(self::admin_url() . self::admin_page_template_edit . $template_slug);
          exit;
        }
        
        else if (wp_verify_nonce($_POST['_wpnonce'], self::_ . '_template_edit')) {
          $template = array(
            'body_html' => $_POST['body_html'] ?: null,
            'body_text' => strip_tags($_POST['body_text']) ?: null);
          self::db_update_template($template, $template_slug);

          self::message(sprintf(__('Vorlage <b>%s</b> gespeichert.', 'LL_mailer'), $template_slug));
          wp_redirect(self::admin_url() . self::admin_page_template_edit . $template_slug);
          exit;
        }
        
        else if (wp_verify_nonce($_POST['_wpnonce'], self::_ . '_template_delete')) {
          self::db_delete_template($template_slug);
          
          self::message(sprintf(__('Vorlage <b>%s</b> gelöscht.', 'LL_mailer'), $template_slug));
          wp_redirect(self::admin_url() . self::admin_page_templates);
          exit;
        }
      }
    }
    wp_redirect(self::admin_url() . self::admin_page_templates);
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
          <input type="hidden" name="action" value="<?=self::_?>_message_action" />
          <?php wp_nonce_field(self::_ . '_message_add'); ?>
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
          $messages = self::db_get_messages(array('slug', 'subject', 'template_slug', 'last_modified'));
          $edit_url = self::admin_url() . self::admin_page_message_edit;
          foreach ($messages as &$message) {
?>
            <?=self::list_item?> <a href="<?=$edit_url . urlencode($message['slug'])?>"><b><?=$message['slug']?></b></a> &nbsp; 
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
        $message = self::db_get_message_by_slug($message_slug);
        if (empty($message)) {
          self::message(sprintf(__('Es existiert keine Nachricht <b>%s</b>.', 'LL_mailer'), $message_slug));
          wp_redirect(self::admin_url() . self::admin_page_messages);
          exit;
        }
        $preview_body_html = $message['body_html'];
        $preview_body_text = $message['body_text'];
        
        $templates = self::db_get_templates('slug');
        
        $template_body_html = '';
        $template_body_text = '';
        if (!is_null($message['template_slug'])) {
          $template = self::db_get_template_by_slug($message['template_slug']);
          $template_body_html = $template['body_html'];
          $template_body_text = $template['body_text'];
          
          $preview_body_html = str_replace(self::token_CONTENT['pattern'], $message['body_html'], $template_body_html);
          $preview_body_text = str_replace(self::token_CONTENT['pattern'], $message['body_text'], $template_body_text);
        }
?>
        <h1><?=__('Nachrichten', 'LL_mailer')?> &gt; <?=$message_slug?></h1>

        <form method="post" action="admin-post.php">
          <input type="hidden" name="action" value="<?=self::_?>_message_action" />
          <?php wp_nonce_field(self::_ . '_message_edit'); ?>
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
                <a id="<?=self::_?>_template_edit_link" href="<?=self::admin_url() . self::admin_page_template_edit . urlencode($message['template_slug'])?>">(<?=__('Zur Vorlage', 'LL_mailer')?>)</a>
              </td>
            </tr>
            <tr>
              <th scope="row"><?=__('Betreff', 'LL_mailer')?></th>
              <td>
                <input type="text" name="subject" value="<?=esc_attr($message['subject'])?>" placeholder="Betreff" style="width: 100%;" />
              </td>
            </tr>
            <tr>
              <td <?=self::secondary_settings_label?>><?=__('Vorschau', 'LL_mailer')?></td>
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
              <td <?=self::secondary_settings_label?>><?=__('Vorschau (HTML)', 'LL_mailer')?></th>
              <td>
                <iframe id="body_html_preview" style="width: 100%; height: 200px; resize: vertical; border: 1px solid #ddd; background: white;" srcdoc="<?=htmlspecialchars(
                    self::html_prefix . $preview_body_html . self::html_suffix
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
              <td <?=self::secondary_settings_label?>><?=__('Vorschau (Text)', 'LL_mailer')?></th>
              <td>
                <textarea disabled id="body_text_preview" style="width: 100%; color:black; background: white;" rows=10><?=$preview_body_text?></textarea>
              </td>
            </tr>
            <tr>
              <td style="vertical-align: top;"><?php submit_button(__('Nachricht speichern', 'LL_mailer'), 'primary', '', false); ?></td>
              <td>
                <p><?=__('Im Inhalt (HTML und Text) können folgende Platzhalter verwendet werden.', 'LL_mailer')?></p>
                <?=self::get_token_description()?>
              </td>
            </tr>
          </table>
        </form>

        <hr />

        <h1><?=__('Testnachricht', 'LL_mailer')?></h1>
        <br />

<?php
        $subscribers = self::db_get_subscribers(array(self::subscriber_attribute_mail, self::subscriber_attribute_name));
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
          <form id="<?=self::_?>_testmail">
            <input type="hidden" name="msg" value="<?=$message_slug?>" />
            <select id="to" name="to">
<?php
            foreach ($subscribers as &$subscriber) {
?>
              <option value="<?=$subscriber[self::subscriber_attribute_mail]?>"><?=$subscriber[self::subscriber_attribute_name] . ' / ' . $subscriber[self::subscriber_attribute_mail]?></option>
<?php
            }
?>
            </select>
            <select id="post" name="post">
<?php
            foreach ($test_posts->posts as $post) {
              $cats = wp_get_post_categories($post->ID);
              $cats = array_map(function($cat) { return get_category($cat)->name; }, $cats);
?>
              <option value="<?=$post->ID?>"><?=$post->post_title?> (<?=implode(', ', $cats)?>)</option>
<?php
            }
?>
              <option value="" style="color: gray;">(<?=__('Kein Test-Post')?>)</option>
            </select>
            &nbsp; <span class="description" id="<?=self::_?>_testmail_preview_response"></span>
          </form>
          <p>
            <?php submit_button(__('Test-E-Mail senden', 'LL_mailer'), '', 'send_testmail', false); ?>
            &nbsp; <span class="description" id="<?=self::_?>_testmail_send_response"></span>
          </p>
          <script>
            new function() {
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
                template_body_html = '<?=self::token_CONTENT['pattern']?>';
                template_body_text = '<?=self::token_CONTENT['pattern']?>';
              }
              else {
                jQuery.getJSON('<?=self::json_url()?>get?template=' + template_select.value, function (new_template) {
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
                var html = template_body_html.replace('<?=self::token_CONTENT['pattern']?>', textarea_html.value, 'g');
                for (var r in testmail_replace_dict.block.html) html = html.replace(r, testmail_replace_dict.block.html[r], 'g');
                for (var r in testmail_replace_dict.inline.html) html = html.replace(r, testmail_replace_dict.inline.html[r], 'g');
                preview_html.contentWindow.document.body.innerHTML = html;
              }
              function update_preview_text() {
                var text = template_body_text.replace('<?=self::token_CONTENT['pattern']?>', textarea_text.value, 'g');
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
                  template_body_html = '<?=self::token_CONTENT['pattern']?>';
                  template_body_text = '<?=self::token_CONTENT['pattern']?>';
                  update_preview_html();
                  update_preview_text();
                }
                else {
                  for (var i = 0; i < show_hide.length; i++) show_hide[i].disabled = true;
                  jQuery.getJSON('<?=self::json_url()?>get?template=' + template_select.value, function (new_template) {
                    template_edit_link.href = '<?=self::admin_url() . self::admin_page_template_edit?>' + encodeURI(new_template.slug);
                    template_edit_link.style.display = 'inline';
                    template_body_html = new_template.body_html;
                    template_body_text = new_template.body_text;
                    update_preview_html();
                    update_preview_text();
                    for (var i = 0; i < show_hide.length; i++) show_hide[i].disabled = false;
                  });
                }
              });


              var testmail_to_select = document.querySelector('#<?=self::_?>_testmail #to');
              var testmail_post_select = document.querySelector('#<?=self::_?>_testmail #post');
              var testmail_preview_response_tag = document.querySelector('#<?=self::_?>_testmail_preview_response');
              var testmail_send_response_tag = document.querySelector('#<?=self::_?>_testmail_send_response');
              function request_message_preview() {
                testmail_to_select.disabled = true;
                testmail_post_select.disabled = true;
                testmail_preview_response_tag.innerHTML = '...';
                jQuery.getJSON('<?=self::json_url() . 'testmail?preview&msg=' . $message_slug . '&to='?>' + encodeURIComponent(testmail_to_select.value) + '&post=' + testmail_post_select.value, function (response) {
                  testmail_to_select.disabled = false;
                  testmail_post_select.disabled = false;
                  if (response.error !== null) {
                    testmail_preview_response_tag.innerHTML = response.error;
                  }
                  else {
                    testmail_preview_response_tag.innerHTML = '<?=__('Vorschau aktualisiert')?>';
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
                testmail_send_response_tag.innerHTML = '...';
                jQuery.getJSON('<?=self::json_url() . 'testmail?send&msg=' . $message_slug . '&to='?>' + encodeURIComponent(testmail_to_select.value) + '&post=' + testmail_post_select.value, function (response) {
                  select_tag.disabled = false;
                  testmail_send_response_tag.innerHTML = response;
                });
              });
            };
          </script>
<?php
        }
?>
        
        <hr />
        
        <h1><?=__('Löschen', 'LL_mailer')?></h1>
        
<?php
        if ($message_slug == get_option(self::option_confirmation_msg)) {
?>
          <p class="description">
            <?=__('Diese Nachricht kann nicht gelöscht werden, da sie für die Bestätigungs-E-Mail verwendet wird.', 'LL_mailer')?><br />
            (<a href="<?=self::admin_url() . self::admin_page_settings?>"><?=__('Zu den Einstellungen', 'LL_mailer')?></a>)
          </p>
<?php
        }
        else {
?>
        <form method="post" action="admin-post.php">
          <input type="hidden" name="action" value="<?=self::_?>_message_action" />
          <?php wp_nonce_field(self::_ . '_message_delete'); ?>
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
        if (wp_verify_nonce($_POST['_wpnonce'], self::_ . '_message_add')) {
          $message_slug = sanitize_title($message_slug);
          if (empty($message_slug)) {
            self::message(sprintf(__('<b>%s</b> kann nicht als Nachrichten-Slug verwendet werden.', 'LL_mailer'), $_POST['message_slug']));
            wp_redirect(self::admin_url() . self::admin_page_messages);
            exit;
          }
          
          $existing_message = self::db_get_message_by_slug($message_slug);
          if (!empty($existing_message)) {
            self::message(sprintf(__('Die Nachricht <b>%s</b> existiert bereits.', 'LL_mailer'), $message_slug));
            wp_redirect(self::admin_url() . self::admin_page_messages);
            exit;
          }
          
          self::db_add_message(array('slug' => $message_slug));
          
          self::message(sprintf(__('Neue Nachricht <b>%s</b> angelegt.', 'LL_mailer'), $message_slug));
          wp_redirect(self::admin_url() . self::admin_page_message_edit . $message_slug);
          exit;
        }
        
        else if (wp_verify_nonce($_POST['_wpnonce'], self::_ . '_message_edit')) {
          $message = array(
            'template_slug' => $_POST['template_slug'] ?: null,
            'subject' => $_POST['subject'] ?: null,
            'body_html' => $_POST['body_html'] ?: null,
            'body_text' => strip_tags($_POST['body_text']) ?: null);
          self::db_update_message($message, $message_slug);

          self::message(sprintf(__('Nachricht <b>%s</b> gespeichert.', 'LL_mailer'), $message_slug));
          wp_redirect(self::admin_url() . self::admin_page_message_edit . $message_slug);
          exit;
        }
        
        else if (wp_verify_nonce($_POST['_wpnonce'], self::_ . '_message_delete')) {
          self::db_delete_message($message_slug);
          
          self::message(sprintf(__('Nachricht <b>%s</b> gelöscht.', 'LL_mailer'), $message_slug));
          wp_redirect(self::admin_url() . self::admin_page_messages);
          exit;
        }
      }
    }
    wp_redirect(self::admin_url() . self::admin_page_messages);
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
          <input type="hidden" name="action" value="<?=self::_?>_subscriber_action" />
          <?php wp_nonce_field(self::_ . '_subscriber_add'); ?>
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
          $subscribers = self::db_get_subscribers('*');
          $edit_url = self::admin_url() . self::admin_page_subscriber_edit;
          foreach ($subscribers as &$subscriber) {
?>
            <?=self::list_item?> <a href="<?=$edit_url . urlencode($subscriber[self::subscriber_attribute_mail])?>">
              <b><?=($subscriber[self::subscriber_attribute_name] ?? '</b><i>(' . __('kein Name', 'LL_mailer') . ')</i><b>') . ' / ' . $subscriber[self::subscriber_attribute_mail]?></b>
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
        $subscriber = self::db_get_subscriber_by_mail($subscriber_mail);
        if (empty($subscriber)) {
          self::message(sprintf(__('Es existiert kein Abonnent <b>%s</b>.', 'LL_mailer'), $subscriber_mail));
          wp_redirect(self::admin_url() . self::admin_page_subscribers);
          exit;
        }
?>
        <h1><?=__('Abonnenten', 'LL_mailer')?> &gt; <?=$subscriber_mail?></h1>

        <form method="post" action="admin-post.php">
          <input type="hidden" name="action" value="<?=self::_?>_subscriber_action" />
          <?php wp_nonce_field(self::_ . '_subscriber_edit'); ?>
          <input type="hidden" name="subscriber_mail" value="<?=$subscriber_mail?>" />
          <table class="form-table">
<?php
            $attributes = self::get_option_array(self::option_subscriber_attributes);
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
              if (isset($subscriber[self::subscriber_attribute_subscribed_at])) {
                echo $subscriber[self::subscriber_attribute_subscribed_at];
              }
              else {
                ?>
                <i>( <?=__('unbestätigt', 'LL_mailer')?> )</i> &nbsp;
                <form method="post" action="admin-post.php" style="display: inline;">
                  <input type="hidden" name="action" value="<?=self::_?>_subscriber_action" />
                  <?php wp_nonce_field(self::_ . '_subscriber_manual_confirm'); ?>
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
          <input type="hidden" name="action" value="<?=self::_?>_subscriber_action" />
          <?php wp_nonce_field(self::_ . '_subscriber_delete'); ?>
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
        if (wp_verify_nonce($_POST['_wpnonce'], self::_ . '_subscriber_add')) {
          $subscriber_mail = trim($subscriber_mail);
          if (!filter_var($subscriber_mail, FILTER_VALIDATE_EMAIL)) {
            self::message(sprintf(__('Die E-Mail Adresse <b>%s</b> ist ungültig.', 'LL_mailer'), $subscriber_mail));
            wp_redirect(self::admin_url() . self::admin_page_subscribers);
            exit;
          }
          
          $existing_subscriber = self::db_get_subscriber_by_mail($subscriber_mail);
          if (!empty($existing_subscriber)) {
            self::message(sprintf(__('Der Abonnent <b>%s</b> existiert bereits.', 'LL_mailer'), $subscriber_mail));
            wp_redirect(self::admin_url() . self::admin_page_subscribers);
            exit;
          }
          
          self::db_add_subscriber(array(self::subscriber_attribute_mail => $subscriber_mail));
          
          self::message(sprintf(__('Neuer Abonnent <b>%s</b> angelegt.', 'LL_mailer'), $subscriber_mail));
          wp_redirect(self::admin_url() . self::admin_page_subscriber_edit . urlencode($subscriber_mail));
          exit;
        }
        
        else if (wp_verify_nonce($_POST['_wpnonce'], self::_ . '_subscriber_edit')) {
          $new_subscriber_mail = trim($_POST[self::subscriber_attribute_mail]);
          if (!filter_var($new_subscriber_mail, FILTER_VALIDATE_EMAIL)) {
            self::message(sprintf(__('Die neue E-Mail Adresse <b>%s</b> ist ungültig.', 'LL_mailer'), $new_subscriber_mail));
            wp_redirect(self::admin_url() . self::admin_page_subscriber_edit . urlencode($subscriber_mail));
            exit;
          }
          
          $attributes = self::get_option_array(self::option_subscriber_attributes);
          $subscriber = array();
          foreach (array_keys($attributes) as $attr) {
            $subscriber[$attr] = $_POST[$attr] ?: null;
          }
          $subscriber[self::subscriber_attribute_mail] = $new_subscriber_mail;
          
          self::db_update_subscriber($subscriber, $subscriber_mail);
          
          self::message(sprintf(__('Abonnent <b>%s</b> gespeichert.', 'LL_mailer'), $new_subscriber_mail));
          wp_redirect(self::admin_url() . self::admin_page_subscriber_edit . urlencode($new_subscriber_mail));
          exit;
        }
        
        else if (wp_verify_nonce($_POST['_wpnonce'], self::_ . '_subscriber_manual_confirm')) {
          self::db_confirm_subscriber($subscriber_mail);

          self::message(sprintf(__('Abonnent <b>%s</b> bestätigt.', 'LL_mailer'), $subscriber_mail));
          wp_redirect(self::admin_url() . self::admin_page_subscriber_edit . urlencode($subscriber_mail));
          exit;
        }

        else if (wp_verify_nonce($_POST['_wpnonce'], self::_ . '_subscriber_delete')) {
          self::db_delete_subscriber($subscriber_mail);

          self::message(sprintf(__('Abonnent <b>%s</b> gelöscht.', 'LL_mailer'), $subscriber_mail));
          wp_redirect(self::admin_url() . self::admin_page_subscribers);
          exit;
        }
      }
    }
    wp_redirect(self::admin_url() . self::admin_page_subscribers);
    exit;
  }
  
  
  
  static function shortcode_SUBSCRIPTION_FORM($atts)
  {
    $attributes = self::get_option_array(self::option_subscriber_attributes);
    ob_start();
?>
    <form action="<?=self::json_url()?>subscribe" method="post" <?=$atts['html_attr'] ?: ''?>>
      <table>
<?php
    foreach ($attributes as $attr => $attr_label) {
?>
      <tr>
        <td><?=$attr_label?></td>
        <td><input type="text" name="<?=$attr?>" /></td>
        <td><?=$attr == self::subscriber_attribute_mail ? _('(Pflichtfeld)') : ''?></td>
      </tr>
<?php
    }
?>
        <tr><td></td><td><input type="submit" value="<?=__('Jetzt anmelden', 'LL_mailer')?>" <?=$atts['button_attr'] ?: ''?> /></td></tr>
      </table>
    </form>
<?php
    return ob_get_clean();
  }
  
  static function shortcode_SUBSCRIBER_ATTRIBUTE($atts)
  {
    if (!empty($atts) && isset($_GET['subscriber']) && !empty($_GET['subscriber'])) {
      $subscriber_mail = base64_decode(urldecode($_GET['subscriber']));
      
      $attributes = array_keys(self::get_option_array(self::option_subscriber_attributes));
      if (in_array($atts[0], $attributes)) {
        
        $subscriber = self::db_get_subscriber_by_mail($subscriber_mail);
        if (!is_null($subscriber)) {
          
          return $subscriber[$atts[0]];
        }
      }
    }
    return '';
  }

  
  
  
  
  
 

  static function init_hooks_and_filters()
  {
    add_action('admin_menu', self::_('admin_menu'));
    add_action('admin_post_' . self::_ . '_settings_action', self::_('admin_page_settings_action'));
    add_action('admin_post_' . self::_ . '_template_action', self::_('admin_page_template_action'));
    add_action('admin_post_' . self::_ . '_message_action', self::_('admin_page_message_action'));
    add_action('admin_post_' . self::_ . '_subscriber_action', self::_('admin_page_subscriber_action'));


    add_shortcode(self::shortcode_SUBSCRIPTION_FORM['code'], self::_('shortcode_SUBSCRIPTION_FORM'));
    add_shortcode(self::shortcode_SUBSCRIBER_ATTRIBUTE['code'], self::_('shortcode_SUBSCRIBER_ATTRIBUTE'));


    add_action('admin_notices', self::_('admin_notices'));

    register_activation_hook(__FILE__, self::_('activate'));

    add_action('transition_post_status', self::_('post_status_transition'), 10, 3);

    add_action('rest_api_init', function ()
    {
      register_rest_route(self::_ . '/v1', 'get', array(
        'callback' => self::_('json_get')
      ));
      register_rest_route(self::_ . '/v1', 'testmail', array(
        'callback' => self::_('testmail')
      ));
      register_rest_route(self::_ . '/v1', 'new-post-mail', array(
        'callback' => self::_('new_post_mail')
      ));
      register_rest_route(self::_ . '/v1', 'subscribe', array(
        'callback' => self::_('subscribe'),
        'methods' => 'POST'
      ));
      register_rest_route(self::_ . '/v1', 'confirm-subscription', array(
        'callback' => self::_('confirm_subscription')
      ));
      register_rest_route(self::_ . '/v1', 'unsubscribe', array(
        'callback' => self::_('unsubscribe')
      ));
    });
  }
}

LL_mailer::init_hooks_and_filters();

?>