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

if (!function_exists('BETA')) {
  function BETA()
  {
    return is_user_logged_in();
  }
}



class LL_mailer
{
  const _ = 'LL_mailer';

  const option_version                      = self::_ . '_version';
  const option_msg                          = self::_ . '_msg';
  const option_sender_name                  = self::_ . '_sender_name';
  const option_sender_mail                  = self::_ . '_sender_mail';
  const option_subscriber_attributes        = self::_ . '_subscriber_attributes';
  const option_subscribe_page               = self::_ . '_subscribe_page';
  const option_show_filter_categories       = self::_ . '_subscribe_form_show_filter_categories';
  const option_form_submitted_page          = self::_ . '_form_submitted_page';
  const option_confirmation_msg             = self::_ . '_confirmation_msg';
  const option_confirmed_admin_msg          = self::_ . '_confirmed_admin_msg';
  const option_confirmed_page               = self::_ . '_confirmed_page';
  const option_unsubscribed_admin_msg       = self::_ . '_unsubscribed_admin_msg';
  const option_unsubscribed_page            = self::_ . '_unsubscribed_page';
  const option_new_post_msg                 = self::_ . '_new_post_msg';
  const option_recaptcha_website_key        = self::_ . '_recaptcha_website_key';
  const option_recaptcha_secret_key         = self::_ . '_recaptcha_secret_key';
  const option_use_robot_check              = self::_ . '_use_robot_check';

  const subscriber_attribute_id             = 'id';
  const subscriber_attribute_mail           = 'mail';
  const subscriber_attribute_name           = 'name';
  const subscriber_attribute_subscribed_at  = 'subscribed_at';
  const subscriber_attribute_meta           = 'meta';
  const subscriber_attributes_default       = array(self::subscriber_attribute_id,
                                                    self::subscriber_attribute_mail,
                                                    self::subscriber_attribute_name,
                                                    self::subscriber_attribute_subscribed_at,
                                                    self::subscriber_attribute_meta);

  const meta_ip                             = 'ip';
  const meta_submitted_at                   = 'submitted_at';
  const meta_disabled                       = 'disabled';
  const meta_test_receiver                  = 'test_receiver';

  const table_templates                     = self::_ . '_templates';
  const table_messages                      = self::_ . '_messages';
  const table_filters                       = self::_ . '_filters';
  const table_subscribers                   = self::_ . '_subscribers';
  const table_subscriptions                 = self::_ . '_subscriptions';

  const admin_page_settings                 = self::_ . '_settings';
  const admin_page_templates                = self::_ . '_templates';
  const admin_page_template_edit            = self::_ . '_templates&edit=';
  const admin_page_messages                 = self::_ . '_messages';
  const admin_page_message_edit             = self::_ . '_messages&edit=';
  const admin_page_message_abo_mail_preview = self::_ . '_messages&abo_mail_preview=';
  const admin_page_subscribers              = self::_ . '_subscribers';
  const admin_page_subscriber_edit          = self::_ . '_subscribers&edit=';
  const admin_page_subscriber_attributes    = self::_ . '_subscriber_attributes';
  const admin_page_filters                  = self::_ . '_filters';

  const attr_fmt_alt                        = '\s+"([^"]+)"(\s+(fmt)="([^"]*)")?(\s+(alt)="([^"]*)")?(\s+(escape-html))?(\s+(nl2br))?';
  const attr_fmt_alt_html                   = array('fmt' => '{fmt="&percnt;s"}',
                                                    'alt' => '{alt=""}',
                                                    'escape' => '{escape-html}',
                                                    'br' => '{nl2br}');
  const attr_options_html                   = ' "<i>Attribut-Slug</i>" {...}';

  const pattern_any_token                   = '/\[[^\]]*\]/';

  const token_CONTENT                       = array('pattern' => '[CONTENT]',
                                                    'html'    => '[CONTENT]'
                                                    );
  const token_CONFIRMATION_URL              = array('pattern' => '[CONFIRMATION_URL]',
                                                    'html'    => '[CONFIRMATION_URL]'
                                                    );
  const token_UNSUBSCRIBE_URL               = array('pattern' => '[UNSUBSCRIBE_URL]',
                                                    'html'    => '[UNSUBSCRIBE_URL]'
                                                    );
  const token_IN_ABO_MAIL                   = array('pattern' => '/\[IN_ABO_MAIL\]((?:(?!\[IN_ABO_MAIL\]).)*)\[\/IN_ABO_MAIL\]/s',
                                                    'html'    => '[IN_ABO_MAIL]...[/IN_ABO_MAIL]'
                                                    );
  const token_ESCAPE_HTML                   = array('pattern' => '/\[ESCAPE_HTML\]((?:(?!\[ESCAPE_HTML\]).)*)\[\/ESCAPE_HTML\]/s',
                                                    'html'    => '[ESCAPE_HTML]...[/ESCAPE_HTML]'
                                                    );
  const token_SUBSCRIBER_ATTRIBUTE          = array('pattern' => '/\[SUBSCRIBER' . self::attr_fmt_alt . '\]/',
                                                    'html'    => '[SUBSCRIBER' . self::attr_options_html . ']',
                                                    'filter'  => self::_ . '_SUBSCRIBER_attribute',
                                                    'example' => array('[SUBSCRIBER "mail"]',
                                                                       '[SUBSCRIBER "name" fmt="Hallo %s, willkommen" alt="Willkommen"]')
                                                    );
  const token_POST_ATTRIBUTE                = array('pattern' => '/\[POST' . self::attr_fmt_alt . '\]/',
                                                    'html'    => '[POST' . self::attr_options_html . ']',
                                                    'filter'  => self::_ . '_POST_attribute',
                                                    'example' => array('[POST "post_title"]',
                                                                       '[POST "post_excerpt" alt="" escape-html nl2br]')
                                                    );
  const token_POST_META                     = array('pattern' => '/\[POST_META' . self::attr_fmt_alt . '\]/',
                                                    'html'    => '[POST_META' . self::attr_options_html . ']',
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
                                                    'html'    => '[LL_mailer_SUBSCRIBER "<i>Attribut-Slug</i>"]'
                                                    );

  const robot_questions = array(array('Welche Farbe haben Bananen?', 'gelb'),
                                array('Auf welchem Planeten leben wir?', 'Erde'),
                                array('Ist es tags hell oder dunkel?', 'hell'),
                                array('Schwimmen Fische in Sand oder Wasser?', 'Wasser'));

  const all_posts = '*';
  const user_msg = self::_ . '_usermsg';
  const retry = self::_ . '_retry';
  const list_item = '<span style="padding: 5px;">&ndash;</span>';
  const arrow_up = '&#x2934;';
  const arrow_down = '&#x2935;';
  const secondary_settings_label = 'style="vertical-align: baseline;"';

  const html_prefix = '<html><head></head><body>';
  const html_suffix = '</body></html>';

  static $error = array('replace_token' => 0);



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

  static function in_array($haystack, $compare_callback)
  {
    foreach ($haystack as $key => &$item) {
      if ($compare_callback($item, $key)) {
        return true;
      }
    }
    return false;
  }

  static function is_predefined_subscriber_attribute($attr) { return in_array($attr, self::subscriber_attributes_default); }

  static function make_cid($i) { return 'attachment.' . $i . '@' . $_SERVER['SERVER_NAME']; }

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
        self::inline_message($msg[0], $msg[1]);
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

  static function inline_message($msg, $sticky_id = false)
  {
    $hide_class = ($sticky_id) ? ' ' . self::_ . '_sticky_message' : '';
    echo '<div class="notice notice-info' . $hide_class . '">';
    if ($sticky_id) {
      echo '<p style="float: right; padding-left: 20px;">' .
        '(<a class="' . self::_ . '_sticky_message_hide_link" href="' . self::json_url() . 'get?hide_message=' . urlencode($sticky_id) . '">' . __('Ausblenden', 'LL_mailer') . '</a>)' .
        '</p>';
    }
    echo '<p>' . nl2br($msg) . '</p></div>';
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
    if ($key === '*')
      return $key;
    if ($key[0] === '#')
      return substr($key, 1);
    return '`' . $key . '`';
  }

  static function escape_keys($keys)
  {
    if (is_array($keys)) {
      return array_map(function($key) {
        return self::escape_key($key);
      }, $keys);
    }
    return self::escape_key($keys);
  }

  static function escape_value($value)
  {
    if ($value[0] === '#')
      return substr($value, 1);
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
      $ret[self::escape_key($key)] = is_null($val) ? 'NULL' : (is_array($val) ? self::escape_values($val) : self::escape_value($val));
    }
    return $ret;
  }

  static function build_where_recursive($where)
  {
    if (empty($where)) {
      return null;
    }
    $ret = array();
    foreach ($where as $key => &$value) {
      if ($key === 0 && is_string($value)) {
        continue;
      }
      if (is_array($value) && isset($value[0])) {
        if (is_array($value[0])) {
          $w = self::build_where_recursive($value[0]);
          if (!is_null($w)) {
            $ret[] = $w;
          }
        }
        else if (isset($value[1])) {
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
    return '(' . implode(' ' . (isset($where[0]) ? $where[0] : 'AND') . ' ', $ret) . ')';
  }

  static function build_where($where)
  {
    // self::message(str_replace(' ', '&nbsp;', print_r($where, true)));
    $w = self::build_where_recursive($where);
    if (!is_null($w)) {
      return ' WHERE ' . $w;
    }
    return $w;
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
    // self::message(htmlspecialchars($sql));
    return $sql;
  }

  static function _db_insert($table, $data)
  {
    $data = self::escape($data);
    global $wpdb;
    $sql = 'INSERT INTO ' . self::escape_key($wpdb->prefix . $table) . ' ( ' . implode(', ', array_keys($data)) . ' ) VALUES ( ' . implode(', ', array_values($data)) . ' );';
    // self::message(htmlspecialchars($sql));
    return $wpdb->query($sql);
  }

  static function _db_insert_multiple($table, $keys, $values_array)
  {
    global $wpdb;
    $sql = 'INSERT INTO ' . self::escape_key($wpdb->prefix . $table) . ' ( ' . implode(', ', self::escape_keys($keys)) . ' ) VALUES';
    foreach ($values_array as &$values) {
      $values = '( ' . implode(', ', self::escape_values($values)) . ' )';
    }
    $sql .= implode(', ', $values_array) . ';';
    // self::message(htmlspecialchars($sql));
    return $wpdb->query($sql);
  }

  static function _db_update($table, $data, $where)
  {
    $data = self::escape($data);
    global $wpdb;
    $sql = 'UPDATE ' . self::escape_key($wpdb->prefix . $table) . ' SET ' . self::array_zip(' = ', $data, ', ') . self::build_where($where) . ';';
    // self::message(htmlspecialchars($sql));
    return $wpdb->query($sql);
  }

  static function _db_delete($table, $where)
  {
    global $wpdb;
    $sql = 'DELETE FROM ' . self::escape_key($wpdb->prefix . $table) . self::build_where($where) . ';';
    // self::message(htmlspecialchars($sql));
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



  static function db_get_new_id() {
	  global $wpdb;
	  return $wpdb->insert_id;
  }

  static function db_get_error() {
	  global $wpdb;
	  return $wpdb->last_error . '<br />' . $wpdb->last_query;
  }

  static function db_find_post($slug) {
    global $wpdb;
    return (int) $wpdb->get_var('SELECT ' . 'ID' . ' FROM ' . $wpdb->posts . self::build_where(array('post_name' => $slug)) . ';');
  }

  // templates
  // - slug
  // - body_html
  // - body_text
  // - last_modified
  static function db_add_template($template) { return self::_db_insert(self::table_templates, $template); }
  static function db_update_template($template, $slug) { $template['last_modified'] = '#NOW()'; return self::_db_update(self::table_templates, $template, array('slug' => $slug)); }
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
  static function db_update_message($message, $slug) { $message['last_modified'] = '#NOW()'; return self::_db_update(self::table_messages, $message, array('slug' => $slug)); }
  static function db_delete_message($slug) { return self::_db_delete(self::table_messages, array('slug' => $slug)); }
  static function db_get_message_by_slug($slug) { return self::_db_select_row(self::table_messages, '*', array('slug' => $slug)); }
  static function db_get_messages($what) { return self::_db_select(self::table_messages, $what); }
  static function db_get_messages_by_template($template_slug) { return array_map(function($v) { return $v['slug']; }, self::_db_select(self::table_messages, 'slug', array('template_slug' => $template_slug))); }

  // filters
  // - id
  // - label
  // - categories
  // - preselected
  static function db_add_filter($filter) { return self::_db_insert(self::table_filters, $filter); }
  static function db_update_filter($filter, $id) { return self::_db_update(self::table_filters, $filter, array('id' => $id)); }
  static function db_delete_filter($id) { return self::_db_delete(self::table_filters, array('id' => $id)); }
  static function db_get_filter_by_id($id) { return self::_db_select_row(self::table_filters, '*', array('id' => $id)); }
  static function db_get_filters($what) { return self::_db_select(self::table_filters, $what); }
  static function db_get_filters_by_categories($what, $categories)
  {
    return self::_db_select(self::table_filters, $what,
      array_merge(array(
        'OR',
        'categories' => self::all_posts),
      array_map(function($cat) { return array(array(
        'categories' => array('LIKE', '%|' . $cat . '|%'))); }, $categories)));
  }
  static function db_filter_exists($where) { return intval(self::_db_select_row(self::table_filters, '#COUNT(0)', $where)['COUNT(0)']); }
  static function implode_filter_categories($categories) { return '|' . implode('|', $categories) . '|'; }
  static function explode_filter_categories($categories)
  {
    if (is_array($categories)) {
      foreach ($categories as &$item) {
        $item['categories'] = self::explode_filter_categories($item['categories']);
      }
      return $categories;
    }
    return ($categories === self::all_posts) ? self::all_posts : array_filter(explode('|', $categories));
  }

  // subscribers
  // - id
  // - mail
  // - subscribed_at
  // - meta
  // [...]
  static function db_add_subscriber($subscriber) {
    $subscriber[self::subscriber_attribute_meta] = addslashes(json_encode(array(
      self::meta_submitted_at => time(),
      self::meta_ip => ($_SERVER['HTTP_CLIENT_IP'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR']))
    )));
	  return self::_db_insert(self::table_subscribers, $subscriber);
	}
  static function db_update_subscriber($subscriber, $old_mail) { return self::_db_update(self::table_subscribers, $subscriber, array(self::subscriber_attribute_mail => $old_mail)); }
  static function db_confirm_subscriber($mail) { return self::_db_update(self::table_subscribers, array(self::subscriber_attribute_subscribed_at => '#NOW()'), array(self::subscriber_attribute_mail => $mail)); }
  static function db_delete_subscriber($mail) { return self::_db_delete(self::table_subscribers, array(self::subscriber_attribute_mail => $mail)); }
  static function db_get_subscriber_by_mail($mail) { return self::_db_select_row(self::table_subscribers, '*', array(self::subscriber_attribute_mail => $mail)); }

  static function db_get_subscribers($confirmed_only = false, $active_only = false, $test_receiver_only = false)
  {
    $subscribers = self::_db_select(self::table_subscribers, '*', $confirmed_only ? array(self::subscriber_attribute_subscribed_at => array('IS NOT NULL')) : array());
    if ($active_only || $test_receiver_only) {
      $subscribers = array_filter($subscribers, function($subscriber) use ($active_only, $test_receiver_only) {
        $meta = json_decode($subscriber[self::subscriber_attribute_meta], true);
        return
          (!$active_only || !isset($meta[self::meta_disabled]) || !$meta[self::meta_disabled]) &&
          (!$test_receiver_only || (isset($meta[self::meta_test_receiver]) && $meta[self::meta_test_receiver]));
      });
    }
    return $subscribers;
  }

  static function db_subscribers_add_attribute($attr)
  {
    global $wpdb;
    $sql = 'ALTER TABLE ' . self::escape_keys($wpdb->prefix . self::table_subscribers) . ' ADD ' . self::escape_keys($attr) . ' TEXT NULL DEFAULT NULL;';
    // self::message($sql);
    return $wpdb->query($sql);
  }
  static function db_subscribers_rename_attribute($attr, $new_attr)
  {
    global $wpdb;
    $sql = 'ALTER TABLE ' . self::escape_keys($wpdb->prefix . self::table_subscribers) . ' CHANGE ' . self::escape_keys($attr) . ' ' . self::escape_keys($new_attr) . ' TEXT;';
    // self::message($sql);
    return $wpdb->query($sql);
  }
  static function db_subscribers_delete_attribute($attr)
  {
    global $wpdb;
    $sql = 'ALTER TABLE ' . self::escape_keys($wpdb->prefix . self::table_subscribers) . ' DROP ' . self::escape_keys($attr) . ';';
    // self::message($sql);
    return $wpdb->query($sql);
  }

  // subscriptions
  // - subscriber
  // - filter
  static function db_add_subscriptions($subscriber_id, $filter_ids)
  {
    return self::_db_insert_multiple(self::table_subscriptions, array('subscriber', 'filter'),
      array_map(function($filter) use ($subscriber_id) {
          return array($subscriber_id, $filter);
        },
        $filter_ids));
  }
  static function db_delete_subscriptions($subscriber_id)
  {
    return self::_db_delete(self::table_subscriptions, array('subscriber' => $subscriber_id));
  }
  static function db_get_filter_ids_by_subscriber($subscriber_id)
  {
    return array_map(
      function($subscription) { return $subscription['filter']; },
      self::_db_select(self::table_subscriptions, 'filter', array('subscriber' => $subscriber_id)));
  }



  static function array_assoc_by($arr, $key = 'id') {
	  $ret = array();
    foreach ($arr as $item) {
      if (array_key_exists($key, $item)) {
        $ret[$item[$key]] = $item;
      }
    }
    return $ret;
  }

  static function filter_subscribers_by_post(&$subscribers, $post_id)
  {
    if (is_null($post_id)) {
      return $subscribers;
    }
    $filters = self::array_assoc_by(self::db_get_filters(array('id', 'categories')));
    $filters = self::explode_filter_categories($filters);
    $post_categories = wp_get_post_categories($post_id);
    return array_filter($subscribers, function(&$subscriber) use ($post_categories, $filters) {
      $subscriber_filter_ids = self::db_get_filter_ids_by_subscriber($subscriber['id']);
      $subscriber_filter_categories = array();
      foreach ($subscriber_filter_ids as &$filter_id) {
        if ($filters[$filter_id]['categories'] == self::all_posts) {
          return true;
        }
        $subscriber_filter_categories = array_merge($subscriber_filter_categories, $filters[$filter_id]['categories']);
      }
      return !empty(array_intersect($post_categories, $subscriber_filter_categories));
    });
  }

  static function get_db_labels()
  {
    return array(
      'table_created' => __('Tabelle erstellt', 'LL_mailer'),
      'table_updated' => __('Tabelle aktualisiert', 'LL_mailer'),
      'table_init' => __('Tabelle initialisiert', 'LL_mailer'),
      'option_init' => __('Einstellung initialisiert', 'LL_mailer'));
  }

  static function activate()
  {
    global $wpdb;
    $r = array();
    $labels = self::get_db_labels();

    $r[] = self::table_templates . ' : ' .
      ($wpdb->query('
        CREATE TABLE ' . self::escape_keys($wpdb->prefix . self::table_templates) . ' (
          `slug` VARCHAR(100) NOT NULL,
          `body_html` TEXT,
          `body_text` TEXT,
          `last_modified` DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
          PRIMARY KEY (`slug`)
        )
        ENGINE=InnoDB ' . $wpdb->get_charset_collate() . ';'
      ) ? $labels['table_created'] : self::db_get_error());

    $r[] = self::table_messages . ' : ' .
      ($wpdb->query('
        CREATE TABLE ' . self::escape_keys($wpdb->prefix . self::table_messages) . ' (
          `slug` VARCHAR(100) NOT NULL,
          `subject` TINYTEXT,
          `template_slug` VARCHAR(100),
          `body_html` TEXT,
          `body_text` TEXT,
          `last_modified` DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
          PRIMARY KEY (`slug`),
          FOREIGN KEY (`template_slug`) REFERENCES ' . self::escape_keys($wpdb->prefix . self::table_templates) . ' (`slug`) ON DELETE RESTRICT ON UPDATE CASCADE
        )
        ENGINE=InnoDB ' . $wpdb->get_charset_collate() . ';'
      ) ? $labels['table_created'] : self::db_get_error());

    $r[] = self::table_subscribers . ' : ' .
      ($wpdb->query('
        CREATE TABLE ' . self::escape_keys($wpdb->prefix . self::table_subscribers) . ' (
          `' . self::subscriber_attribute_id . '` INT NOT NULL AUTO_INCREMENT,
          `' . self::subscriber_attribute_mail . '` VARCHAR(100) NOT NULL,
          `' . self::subscriber_attribute_name . '` TEXT NULL DEFAULT NULL,
          `' . self::subscriber_attribute_subscribed_at . '` DATETIME NULL DEFAULT NULL,
          `' . self::subscriber_attribute_meta . '` TEXT NULL DEFAULT NULL,
          PRIMARY KEY (`' . self::subscriber_attribute_id . '`),
          UNIQUE (`' . self::subscriber_attribute_mail . '`)
        )
        ENGINE=InnoDB ' . $wpdb->get_charset_collate() . ';'
      ) ? $labels['table_created'] : self::db_get_error());

    $r[] = self::table_filters . ' : ' .
      ($wpdb->query('
        CREATE TABLE ' . self::escape_keys($wpdb->prefix . self::table_filters) . ' (
          `id` INT NOT NULL AUTO_INCREMENT,
          `label` TINYTEXT NOT NULL,
          `categories` TINYTEXT NOT NULL,
          `preselected` BOOLEAN NOT NULL,
          PRIMARY KEY (`id`)
        )
        ENGINE=InnoDB ' . $wpdb->get_charset_collate() . ';'
      ) ? $labels['table_created'] : self::db_get_error());

    $r[] = self::table_filters . ' : ' .
      (self::db_add_filter(array(
        'label' => __('Alle Posts', 'LL_mailer'),
        'categories' => self::all_posts,
        'preselected' => true)
      ) ? $labels['table_init'] : self::db_get_error());

    $r[] = self::table_subscriptions . ' : ' .
      ($wpdb->query('
        CREATE TABLE ' . self::escape_keys($wpdb->prefix . self::table_subscriptions) . ' (
          `subscriber` INT NOT NULL,
          `filter` INT NOT NULL,
          INDEX (`subscriber`),
          INDEX (`filter`),
          CONSTRAINT FOREIGN KEY (`subscriber`)
            REFERENCES ' . self::escape_keys($wpdb->prefix . self::table_subscribers) . '(`' . self::subscriber_attribute_id . '`)
            ON UPDATE CASCADE
            ON DELETE CASCADE,
          CONSTRAINT FOREIGN KEY (`filter`)
            REFERENCES ' . self::escape_keys($wpdb->prefix . self::table_filters) . '(`id`)
            ON UPDATE CASCADE
            ON DELETE CASCADE
        )
        ENGINE=InnoDB ' . $wpdb->get_charset_collate() . ';'
      ) ? $labels['table_created'] : self::db_get_error());

    $r[] = self::option_subscriber_attributes . ' : ' .
      (add_option(self::option_subscriber_attributes, array(
        self::subscriber_attribute_mail => 'Deine E-Mail Adresse',
        self::subscriber_attribute_name => 'Dein Name')
      ) ? $labels['option_init'] : self::db_get_error());

    self::message(__('Datenbank eingerichtet und Optionen initialisiert.', 'LL_mailer') . '<br /><p>- ' . implode('</p><p>- ', $r) . '</p>');

    register_uninstall_hook(__FILE__, self::_('uninstall'));
  }

  static function check_for_db_updates()
  {
    if (is_admin()) {
//      update_option(self::option_version, 0);
      $db_version = intval(get_option(self::option_version, 0));
      while (method_exists(self::_, 'update_' . ++$db_version)) {
        $r = self::{ 'update_' . $db_version }();
        self::message(__(
          'Die ' . self::_ . ' Datenbank wurde auf Version ' . $db_version . ' aktualisiert.', 'LL_mailer') .
          '<br /><p>- ' . implode('</p><p>- ', $r) . '</p>');
        update_option(self::option_version, $db_version);
      }
    }
  }

  static function update_1()
  {
    global $wpdb;
    $r = array();
    $labels = self::get_db_labels();

    $r[] = self::table_subscribers . ' : ' .
      ($wpdb->query('
        ALTER TABLE ' . self::escape_keys($wpdb->prefix . self::table_subscribers) . '
          DROP PRIMARY KEY,
          ADD `' . self::subscriber_attribute_id . '` INT NOT NULL AUTO_INCREMENT FIRST,
          ADD PRIMARY KEY (`' . self::subscriber_attribute_id . '`),
          ADD UNIQUE (`' . self::subscriber_attribute_mail . '`);'
        ) ? $labels['table_updated'] : self::db_get_error());

    $r[] = self::table_filters . ' : ' .
      ($wpdb->query('
        CREATE TABLE ' . self::escape_keys($wpdb->prefix . self::table_filters) . ' (
          `id` INT NOT NULL AUTO_INCREMENT,
          `label` TINYTEXT NOT NULL,
          `categories` TINYTEXT NOT NULL,
          `preselected` BOOLEAN NOT NULL,
          PRIMARY KEY (`id`)
        )
        ENGINE=InnoDB ' . $wpdb->get_charset_collate() . ';'
      ) ? $labels['table_created'] : self::db_get_error());

    $r[] = self::table_filters . ' : ' .
      (self::db_add_filter(array(
        'label' => __('Alle Posts', 'LL_mailer'),
        'categories' => self::all_posts,
        'preselected' => true)
      ) ? $labels['table_init'] : self::db_get_error());
    $default_filter = self::db_get_new_id();

    $r[] = self::table_subscriptions . ' : ' .
      ($wpdb->query('
        CREATE TABLE ' . self::escape_keys($wpdb->prefix . self::table_subscriptions) . ' (
          `subscriber` INT NOT NULL,
          `filter` INT NOT NULL,
          INDEX (`subscriber`),
          INDEX (`filter`),
          CONSTRAINT FOREIGN KEY (`subscriber`)
            REFERENCES ' . self::escape_keys($wpdb->prefix . self::table_subscribers) . '(`' . self::subscriber_attribute_id . '`)
            ON UPDATE CASCADE
            ON DELETE CASCADE,
          CONSTRAINT FOREIGN KEY (`filter`)
            REFERENCES ' . self::escape_keys($wpdb->prefix . self::table_filters) . '(`id`)
            ON UPDATE CASCADE
            ON DELETE CASCADE
        )
        ENGINE=InnoDB ' . $wpdb->get_charset_collate() . ';'
      ) ? $labels['table_created'] : self::db_get_error());

    $subscribers = self::db_get_subscribers();
    $r[] = self::table_subscriptions . ' : ' .
      (self::_db_insert_multiple(
        self::table_subscriptions, array('subscriber', 'filter'),
        array_map(function($subscriber) use ($default_filter) {
          return array($subscriber[self::subscriber_attribute_id], $default_filter);
        }, $subscribers)
      ) ? $labels['table_init'] : self::db_get_error());

    return $r;
  }

  static function uninstall()
  {
    global $wpdb;
    $wpdb->query('DROP TABLE IF EXISTS ' . self::escape_keys($wpdb->prefix . self::table_subscriptions) . ';');
    $wpdb->query('DROP TABLE IF EXISTS ' . self::escape_keys($wpdb->prefix . self::table_subscribers) . ';');
    $wpdb->query('DROP TABLE IF EXISTS ' . self::escape_keys($wpdb->prefix . self::table_filters) . ';');
    $wpdb->query('DROP TABLE IF EXISTS ' . self::escape_keys($wpdb->prefix . self::table_messages) . ';');
    $wpdb->query('DROP TABLE IF EXISTS ' . self::escape_keys($wpdb->prefix . self::table_templates) . ';');

    delete_option(self::option_version);
    delete_option(self::option_msg);
    delete_option(self::option_sender_name);
    delete_option(self::option_sender_mail);
    delete_option(self::option_subscriber_attributes);
    delete_option(self::option_subscribe_page);
    delete_option(self::option_show_filter_categories);
    delete_option(self::option_form_submitted_page);
    delete_option(self::option_confirmation_msg);
    delete_option(self::option_confirmed_admin_msg);
    delete_option(self::option_confirmed_page);
    delete_option(self::option_unsubscribed_admin_msg);
    delete_option(self::option_unsubscribed_page);
    delete_option(self::option_new_post_msg);
    delete_option(self::option_recaptcha_website_key);
    delete_option(self::option_recaptcha_secret_key);
    delete_option(self::option_use_robot_check);
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
          $fmt = null;
          $alt = null;
          $escape_html = null;
          $nl2br = null;
          foreach (array(3, 6, 9, 11) as $i) if (array_key_exists($i, $match)) switch ($match[$i]) {
            case 'fmt' :
              $fmt = $match[$i + 1];
              break;
            case 'alt' :
              $alt = $match[$i + 1];
              break;
            case 'escape-html' :
              $escape_html = true;
              break;
            case 'nl2br' :
              $nl2br = true;
              break;
          }

          list($replace_value, $error) = $get_value_by_attr($attr, $match[$FULL]);

          if (!empty($replace_value)) {
            $replacement = $replace_value;
            if (!is_null($fmt)) {
              $replacement = sprintf($fmt, $replacement);
            }
            if ($is_html) {
              if (!is_null($escape_html)) {
                $replacement = htmlspecialchars($replacement);
              }
              if (!is_null($nl2br)) {
                $replacement = nl2br($replacement);
              }
            }
          } else if (!is_null($alt)) {
            $replacement = $alt;
          } else {
            $replacement = $error;
          }

          $replacement = apply_filters($token['filter'], $replacement, array(
            'plain_replace_value' => $replace_value,
            'error' => $error,
            'attr' => $attr,
            'fmt' => $fmt,
            'alt' => $alt,
            'escape-html' => $escape_html,
            'nl2br' => $nl2br,
            'is_html' => $is_html,
            'post' => $post));

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

  static function replace_token_IN_ABO_MAIL($text, $is_abo_mail, $is_html, &$replace_dict)
  {
    preg_match_all(self::token_IN_ABO_MAIL['pattern'], $text, $matches, PREG_SET_ORDER);
    if (!empty($matches)) {
      $FULL = 0;
      $CONTENT = 1;
      $html_or_text = $is_html ? 'html' : 'text';
      foreach ($matches as &$match) {
        if (!is_null($replace_dict) && isset($replace_dict['block'][$html_or_text][$match[$FULL]])) {
          $replacement = $replace_dict['block'][$html_or_text][$match[$FULL]];
        }
        else {
          if ($is_abo_mail) {
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

  static function prepare_mail_for_abo_mail($is_abo_mail, &$body_html, &$body_text, &$replace_dict)
  {
    $body_html = self::replace_token_IN_ABO_MAIL($body_html, $is_abo_mail, true, $replace_dict);
    $body_text = self::replace_token_IN_ABO_MAIL($body_text, $is_abo_mail, false, $replace_dict);
  }

  static function replace_token_ESCAPE_HTML($text, $is_html, &$replace_dict)
  {
    preg_match_all(self::token_ESCAPE_HTML['pattern'], $text, $matches, PREG_SET_ORDER);
    if (!empty($matches)) {
      $FULL = 0;
      $CONTENT = 1;
      $html_or_text = $is_html ? 'html' : 'text';
      foreach ($matches as &$match) {
        if (!is_null($replace_dict) && isset($replace_dict['block'][$html_or_text][$match[$FULL]])) {
          $replacement = $replace_dict['block'][$html_or_text][$match[$FULL]];
        }
        else {
          $replacement = htmlspecialchars($match[$CONTENT]);

          if (!is_null($replace_dict)) {
            $replace_dict['block'][$html_or_text][$match[$FULL]] = $replacement;
          }
        }
        $text = str_replace($match[$FULL], $replacement, $text);
      }
    }
    return $text;
  }

  static function prepare_mail_to_escape_html(&$body_html, &$body_text, &$replace_dict)
  {
    $body_html = self::replace_token_ESCAPE_HTML($body_html, true, $replace_dict);
    $body_text = self::replace_token_ESCAPE_HTML($body_text, false, $replace_dict);
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
        if (!$is_preview) {
          $replacement = 'cid:' . $replacement;
        }
        $body_html = str_replace($match[$FULL], $replacement, $body_html);
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

  static function prepare_mail($msg, $to /* email | null */, $is_abo_mail /* true | false */, $post_id /* ID | null */, $escape_html /* true | false */, $inline_css /* true | false */, $find_and_replace_attachments /* true | 'preview' | false */)
  {
    if (isset($msg)) {
      if (is_string($msg)) {
        $msg = self::db_get_message_by_slug($msg);
        if (is_null($msg)) return __('Nachricht nicht gefunden.', 'LL_mailer');
      }
      $subject = $msg['subject'];
      $body_html = $msg['body_html'];
      $body_text = $msg['body_text'];

      if (!is_null($msg['template_slug'])) {
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

    self::prepare_mail_for_abo_mail($is_abo_mail, $body_html, $body_text, $replace_dict);

    if (!is_null($to)) {
      list($error, $to) = self::prepare_receiver($to);
      if (!is_null($error)) {
        return $error;
      }
      self::prepare_mail_for_receiver($to, $subject, $body_html, $body_text, $replace_dict);
    }

    $post = null;
    if (!is_null($post_id)) {
      $post = self::prepare_mail_for_post($post_id, $subject, $body_html, $body_text, $replace_dict);
    }

    if ($escape_html) {
      self::prepare_mail_to_escape_html($body_html, $body_text, $replace_dict);
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

  static function prepare_receiver($receiver_data_or_mail)
  {
    $error = null;
    $receiver = $receiver_data_or_mail;
    if (is_string($receiver_data_or_mail)) {
      $tmp_receiver = self::db_get_subscriber_by_mail($receiver_data_or_mail);
      if (is_null($tmp_receiver)) {
        $error = __('Empfänger nicht gefunden.', 'LL_mailer');
      }
      else {
        $receiver = $tmp_receiver;
      }
    }
    return array($error, $receiver);
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
      return __('Nachricht nicht gesendet. Absender-Name oder E-Mail fehlen.', 'LL_mailer');
    }

    if (preg_match(self::pattern_any_token, $subject)) {
      return __('Nachricht nicht gesendet. Im Betreff konnten nicht alle Platzhalter ersetzt werden.', 'LL_mailer');
    }
    if (preg_match(self::pattern_any_token, $body_html)) {
      return __('Nachricht nicht gesendet. Im Inhalt (HTML) konnten nicht alle Platzhalter ersetzt werden.', 'LL_mailer');
    }
    if (preg_match(self::pattern_any_token, $body_text)) {
      return __('Nachricht nicht gesendet. Im Inhalt (Text) konnten nicht alle Platzhalter ersetzt werden.', 'LL_mailer');
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
      if ($success && !$phpmailer->isError()) {
        // SUCCESS
        return null;
      }
      return sprintf(__('Nachricht nicht gesendet. PHPMailer Fehler: %s', 'LL_mailer'), $phpmailer->ErrorInfo);

    }
    catch (phpmailerException $e) {
      return __('Nachricht nicht gesendet. Fehler: ', 'LL_mailer') . $phpmailer->ErrorInfo;
    }
    catch (Exception $e) {
      return __('Nachricht nicht gesendet. Fehler: ', 'LL_mailer') . $e->getMessage();
    }
  }

  static function prepare_and_send_mails($msg, $subscribers, $is_abo_mail = true, $post_id = null, $receiver_if_different_from_subscribers = null)
  {
    $out_errors = array();
    $out_num_mails_sent = 0;
    $out_post = null;
    $txt_goto_message = __('Zur Nachricht', 'LL_mailer');
    $edit_url = self::admin_url() . self::admin_page_message_edit . $msg;
    $mail_or_error = self::prepare_mail($msg, null, $is_abo_mail, $post_id, false, false, false);
    if (is_string($mail_or_error)) {
      $out_errors[] = $mail_or_error;
    }
    if (self::$error['replace_token']) {
      $out_errors[] = sprintf(__('Die Nachricht enthält %d Platzhalter-Fehler.', 'LL_mailer'), self::$error['replace_token']) . ' (<a href="' . $edit_url . '">' . $txt_goto_message . '</a>)<br />' . __('Versandt für alle Abonnenten <b>abgebrochen</b>.', 'LL_mailer');
    }
    if (empty($out_errors)) {
      list($to, $subject, $body_html, $body_text, $attachments, $replace_dict, $out_post) = $mail_or_error;

      $from = self::get_sender();
      if (empty($from[self::subscriber_attribute_mail]) || empty($from[self::subscriber_attribute_name]))
      {
        $out_errors[] = __('Absender-Name oder E-Mail wurden in den Einstellungen nicht angegeben.', 'LL_mailer');
      }
      else {
        $token_errors = array();
        foreach ($subscribers as $subscriber) {
          $tmp_subject = $subject;
          $tmp_body_html = $body_html;
          $tmp_body_text = $body_text;
          $tmp_replace_dict = $replace_dict;
          self::prepare_mail_for_receiver($subscriber, $tmp_subject, $tmp_body_html, $tmp_body_text, $tmp_replace_dict);
          self::prepare_mail_to_escape_html($tmp_body_html, $tmp_body_text, $tmp_replace_dict);
          $tmp_attachments = self::prepare_mail_attachments($tmp_body_html, false, $tmp_replace_dict);
          self::prepare_mail_inline_css($tmp_body_html);

          if (self::$error['replace_token']) {
            $token_errors[] = $subscriber[self::subscriber_attribute_name] . ' (' . $subscriber[self::subscriber_attribute_mail] . ')';
            self::$error['replace_token'] = 0;
          }
          else {
            $to = $receiver_if_different_from_subscribers ?? $subscriber;
            $err = self::send_mail($from, $to, $tmp_subject, $tmp_body_html, $tmp_body_text, $tmp_attachments);
            if (is_null($err)) {
              // SUCCESS
              $out_num_mails_sent++;
            }
            else {
              $out_errors[] = $err;
            }
          }
        }
        if (!empty($token_errors)) {
          $out_errors[] = sprintf(__('Die Nachricht enthält Abonnenten-Platzhalter-Fehler.', 'LL_mailer'), $token_errors[0]) . ' (<a href="' . $edit_url . '">' . $txt_goto_message . '</a>)<br />' . __('Versandt für folgende Abonnenten <b>abgebrochen</b>:<br />', 'LL_mailer') . implode("<br />", $token_errors);
        }
      }
    }
    return array($out_errors, $out_num_mails_sent, $out_post);
  }

  static function prepare_and_send_mail($msg_slug, $subscriber_mail, $is_abo_mail = true, $post_id = null, $receiver_if_different_from_subscriber = null)
  {
    list($error, $receiver) = self::prepare_receiver($subscriber_mail);
    if (!is_null($error)) {
      return $error;
    }
    list($errors, $num_mails_sent, $post) = self::prepare_and_send_mails($msg_slug, array($receiver), $is_abo_mail, $post_id, $receiver_if_different_from_subscriber);
    if (!empty($errors)) {
      return implode('<br />', $errors);
    }
    return false;
  }

  static function testmail($request)
  {
    $post_id = $request['post'] ?: null;
    if (isset($request['send'])) {
      $error = self::prepare_and_send_mail($request['msg'], $request['to'], $request['is-abo-mail'], $post_id);
      return $error ?: __('Testnachricht gesendet.', 'LL_mailer');
    }
    else if (isset($request['preview'])) {
      $mail_or_error = self::prepare_mail(
        $request['msg'],
        $request['to'],
        $request['is-abo-mail'],
        $post_id,
        true,
        false,
        'preview');
      if (is_string($mail_or_error)) {
        return array('error' => $mail_or_error);
      }
      else {
        list($to, $subject, $body_html, $body_text, $attachments, $replace_dict) = $mail_or_error;
        return array('subject' => $subject, 'html' => $body_html, 'text' => $body_text, 'attachments' => $attachments, 'replace_dict' => $replace_dict, 'error' => null);
      }
    }
    return null;
  }

  static function send_abo_mails($request)
  {
    if (isset($request['msg']) && isset($request['to'])) {
      switch ($request['to']) {
        case 'all':
          $subscribers = self::db_get_subscribers(true, true);
          $subscribers = self::filter_subscribers_by_post($subscribers, $request['post']);
          list($errors, $num_mails_sent, $post) = self::prepare_and_send_mails($request['msg'], $subscribers, true, $request['post']);
          $post_link = '<a href="' . get_permalink($request['post']) . '">' . get_the_title($request['post']) . '</a>';
          if ($num_mails_sent > 0) {
            self::message(sprintf(__('E-Mails zum Post %s wurden an %d Abonnent(en) versandt.', 'LL_mailer'), '<b>' . $post_link . '</b>', $num_mails_sent));
            self::hide_message(self::msg_id_new_post_published($post->ID));
          }
          if (!empty($errors)) {
            self::message(sprintf("Fehler für Post-Nachricht %s:", '<b>' . $post_link . '</b>') . '<br />' . implode('<br />', $errors), self::msg_id_new_post_mail_failed($request['msg'], $request['post']));
          }
          wp_redirect($request['redirect_to'] ?? wp_get_referer());
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
      if ($admin_notification_msg !== false) {
        $admin_mail = self::get_sender();
        $error = self::prepare_and_send_mail($admin_notification_msg, $subscriber_mail, false, null, $admin_mail);
        if ($error !== false) {
          self::message($error);
        }
      }

      $display = !empty($existing_subscriber[self::subscriber_attribute_name])
        ? $existing_subscriber[self::subscriber_attribute_name] . ' (' . $existing_subscriber[self::subscriber_attribute_mail] . ')'
        : $existing_subscriber[self::subscriber_attribute_mail];
      self::message(sprintf(__('%s hat sich für das E-Mail Abo angemeldet.', 'LL_mailer'), '<b>' . $display . '</b>'), self::msg_id_new_subscriber($subscriber_mail));

      $confirmed_page = get_option(self::option_confirmed_page);
      if ($confirmed_page !== false) {
        wp_redirect(get_permalink(get_page_by_path($confirmed_page)) . '?subscriber=' . urlencode(base64_encode($subscriber_mail)));
      }
      else {
        $subscribe_page = get_option(self::option_subscribe_page);
        if ($subscribe_page !== false) {
          wp_redirect(get_permalink(get_page_by_path($subscribe_page)) . '?' . self::user_msg . '=' . urlencode(base64_encode(__('Deine Anmeldung war erfolgreich.', 'LL_mailer'))));
        }
        else {
          wp_redirect(home_url());
        }
      }
      exit;
    }
  }

  static function subscribe($request)
  {
    $show_messge = function($msg = null) {
      $redirect_page = get_option(self::option_subscribe_page);
      if ($redirect_page !== false) {
        $url = get_permalink(get_page_by_path($redirect_page));
        if (!is_null($msg)) {
          $url .= '?' . self::retry . '&' . self::user_msg . '=' . urlencode(base64_encode($msg));
        }
        wp_redirect($url);
      }
      else {
        wp_redirect(home_url());
      }
      exit;
    };

    if (!empty($_POST)) {

      if (!isset($_POST[self::_ . '_attr_' . self::subscriber_attribute_mail]) || empty($_POST[self::_ . '_attr_' . self::subscriber_attribute_mail])) {
        $show_messge(__('Bitte gib deine Email Adresse an.', 'LL_mailer'));
      }

      if (BETA())
      {
      if (!isset($_POST[self::_ . '_filters']) || empty($_POST[self::_ . '_filters'])) {
        $show_messge(__('Bitte wähle mindestens einen Filter aus.', 'LL_mailer'));
      }
      }

      if (get_option(self::option_use_robot_check)) {
        $robot_check = isset($_POST[self::_ . '_robot_check']) ? $_POST[self::_ . '_robot_check'] : null;
        $robot_check_2 = intval($_POST[self::_ . '_robot_check_2']);
        if (is_null($robot_check) || strtolower($robot_check) != strtolower(self::robot_questions[$robot_check_2][1])) {
          $show_messge(__('Bitte beantworte die Sicherheitsfrage.', 'LL_mailer'));
        }
      }

      $recaptcha_website_key = get_option(self::option_recaptcha_website_key);
      $recaptcha_secret_key = get_option(self::option_recaptcha_secret_key);
      if ($recaptcha_website_key !== false && $recaptcha_secret_key !== false) {
        $captcha_failed = null;
        if (!isset($_POST['g-recaptcha-response']) || empty($_POST['g-recaptcha-response'])) {
          $captcha_failed = __('Bitte bestätige, dass du kein Roboter bist.', 'LL_mailer');
        }
        if (is_null($captcha_failed)) {
          $recaptcha_result = file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, stream_context_create(
            array('http' => array(
              'method' => 'POST',
              'content' => http_build_query(array(
                'secret' => $recaptcha_secret_key,
                'response' => $_POST['g-recaptcha-response']
              ))))));
          $recaptcha_result = json_decode($recaptcha_result);
          if (!$recaptcha_result->success) {
            $captcha_failed = __('Oh oh. Unser Test sagt, du bist ein Roboter.', 'LL_mailer');
          }
        }
        if (!is_null($captcha_failed)) {
          $show_messge($captcha_failed);
        }
      }

      $attributes = self::get_option_array(self::option_subscriber_attributes);
      $new_subscriber = array();
      foreach ($attributes as $attr => $attr_label) {
        if (!empty($_POST[self::_ . '_attr_' . $attr])) {
          $new_subscriber[$attr] = $_POST[self::_ . '_attr_' . $attr];
        }
      }
      self::db_add_subscriber($new_subscriber);
      $new_subscriber_id = self::db_get_new_id();

      if (BETA())
      {
      self::db_add_subscriptions($new_subscriber_id, array_keys($_POST[self::_ . '_filters']));
      }

      $confirmation_msg = get_option(self::option_confirmation_msg);
      if ($confirmation_msg !== false) {
        $error = self::prepare_and_send_mail($confirmation_msg, $new_subscriber[self::subscriber_attribute_mail], false);
        if ($error === false) {
          $confirmation_sent_page = get_option(self::option_form_submitted_page);
          if ($confirmation_sent_page !== false) {
            wp_redirect(get_permalink(get_page_by_path($confirmation_sent_page)) . '?subscriber=' . urlencode(base64_encode($new_subscriber[self::subscriber_attribute_mail])));
          }
          else {
            $show_messge(sprintf(__('Du erhältst in diesem Moment eine E-Mail an %s. Bitte nutze den Link darin, um deine Anmeldung zu bestätigen.', 'LL_mailer'), $new_subscriber[self::subscriber_attribute_mail]));
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
    $show_messge();
    exit;
  }

  static function confirm_subscription($request)
  {
    if (isset($_GET['subscriber']) && !empty($_GET['subscriber'])) {
      $subscriber_mail = base64_decode(urldecode($_GET['subscriber']));
      self::confirm_subscriber($subscriber_mail);
    }
    $subscribe_page = get_option(self::option_subscribe_page);
    if ($subscribe_page !== false) {
      wp_redirect(get_permalink(get_page_by_path($subscribe_page)));
    }
    else {
      wp_redirect(home_url());
    }
    exit;
  }

  static function unsubscribe($request)
  {
    if (isset($_GET['subscriber']) && !empty($_GET['subscriber'])) {
      $subscriber_mail = base64_decode(urldecode($_GET['subscriber']));
      $existing_subscriber = self::db_get_subscriber_by_mail($subscriber_mail);
      if (!is_null($existing_subscriber)) {

        $admin_notification_msg = get_option(self::option_unsubscribed_admin_msg);
        if ($admin_notification_msg !== false) {
          $admin_mail = self::get_sender();
          $error = self::prepare_and_send_mail($admin_notification_msg, $subscriber_mail, false, null, $admin_mail);
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
        if ($unsubscribed_page !== false) {
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
    if ($post->post_type == 'post' && $new_status == 'publish' && $old_status != 'publish') {
      $url = self::generate_new_post_abo_mail($post->ID);
      if ($url) {
        self::message(
          sprintf(__('Du hast den Post %s veröffentlicht.', 'LL_mailer'), '<b>' . get_the_title($post) . '</b>') .
          ' &nbsp; <a href="' . $url . '">' . __('E-Mails an Abonnenten jetzt senden', 'LL_mailer') . '</a>',
          self::msg_id_new_post_published($post->ID));
      }
    }
  }

  static function generage_abo_mail_url($msg, $post_id)
  {
    return self::admin_url() . self::admin_page_message_abo_mail_preview . $msg . '&to=all&post=' . $post_id;
  }

  static function generate_new_post_abo_mail($post_id)
  {
    $msg = get_option(self::option_new_post_msg);
    if ($msg) {
      return self::generage_abo_mail_url($msg, $post_id);
    }
    return false;
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
            '<a href="https://wordpress.org/support/article/custom-fields/" target="_blank">?</a>',
            '<code>' . implode('</code>, <code>', self::token_POST_META['example']) . '</code>')?>
        </td>
      </tr>

      <tr><td colspan=2><?=__('In allen E-Mails:', 'LL_mailer')?></td></tr>
      <tr>
        <td><?=self::list_item?></td>
        <td><code><?=self::token_SUBSCRIBER_ATTRIBUTE['html']?></code></td>
        <td>
          <?=sprintf(__('Abonnenten-Attribut aus den Einstellungen (%s), z.B. %s.', 'LL_mailer'),
            '<a href="' . self::admin_url() . self::admin_page_settings . '#subscriber-attributes">?</a>',
            '<code>' . implode('</code>, <code>', self::token_SUBSCRIBER_ATTRIBUTE['example']) . '</code>')?>
        </td>
      </tr><tr>
        <td><?=self::list_item?></td>
        <td><code><?=self::token_ATTACH['html']?></code></td>
        <td>
          <?=sprintf(__('Bild (URL) als Anhang einbetten, z.B. %s.', 'LL_mailer'),
            '<code>' . implode('</code>, <code>', self::token_ATTACH['example']) . '</code>')?>
        </td>
      </tr><tr>
        <td><?=self::list_item?></td>
        <td><code><?=self::token_ESCAPE_HTML['html']?></code></td>
        <td>
          <?=sprintf(__('Ein Textbereich, in dem HTML-spezifische Sonderzeichen (z.B. <code>&lt;</code> oder <code>&gt;</code>) in anzeigbaren Text umgewandelt werden (%s).', 'LL_mailer'), '<a href="https://www.php.net/manual/de/function.htmlspecialchars.php" target="_blank">?</a>')?>
        </td>
      </tr><tr>
        <td><?=self::list_item?></td>
        <td><code><?=self::token_IN_ABO_MAIL['html']?></code></td>
        <td>
          <?=__('Ein Textbereich, der nur in regulären Abo-E-Mails und nicht in Anmeldebestätigungs-Emails enthalten sein soll, z.B. für einen Abo-abmelden-Link.', 'LL_mailer')?>
        </td>
      </tr><tr>
        <td><?=self::list_item?></td>
        <td><code><?=self::token_CONFIRMATION_URL['html']?></code></td>
        <td><?=__('URL für Bestätigungs-Link bei der Anmeldung zum Abo.', 'LL_mailer')?></td>
      </tr><tr>
        <td><?=self::list_item?></td>
        <td><code><?=self::token_UNSUBSCRIBE_URL['html']?></code></td>
        <td>
          <?=__('URL zur Abmeldung vom Abo.', 'LL_mailer')?>
        </td>
      </tr>

      <tr><td colspan=2><?=sprintf(__('Optionale Attribute in Platzhaltern %s:', 'LL_mailer'), '<code>{...}</code>')?></td></tr>
      <tr>
        <td><?=self::list_item?></td>
        <td><code><?=self::attr_fmt_alt_html['fmt']?></code></td>
        <td>
          <?=sprintf(__('Formatierung (%s) von eingesetzten Attributen.', 'LL_mailer'), '<a href="https://www.php.net/manual/de/function.sprintf.php" target="_blank">?</a>')?>
        </td>
      </tr>
      <tr>
        <td><?=self::list_item?></td>
        <td><code><?=self::attr_fmt_alt_html['alt']?></code></td>
        <td>
          <?=__('Alternativtext, falls angeforderte Attribute (für den Nutzer/Post) nicht vorhanden sind.', 'LL_mailer')?>
        </td>
      </tr>
      <tr>
        <td><?=self::list_item?></td>
        <td><code><?=self::attr_fmt_alt_html['escape']?></code></td>
        <td>
          <?=sprintf(__('HTML-spezifische Sonderzeichen (z.B. <code>&lt;</code> oder <code>&gt;</code>) in anzeigbaren Text umwandeln (%s).', 'LL_mailer'), '<a href="https://www.php.net/manual/de/function.htmlspecialchars.php" target="_blank">?</a>')?>
        </td>
      </tr>
      <tr>
        <td><?=self::list_item?></td>
        <td><code><?=self::attr_fmt_alt_html['br']?></code></td>
        <td>
          <?=sprintf(__('Zeilenumbrüche in HTML-Zeilenumbrüche umwandeln (%s).', 'LL_mailer'), '<a href="https://www.php.net/manual/de/function.nl2br.php" target="_blank">?</a>')?>
        </td>
      </tr>
    </table>
<?php
    return ob_get_clean();
  }



  static function admin_menu()
  {
    $required_capability = 'administrator';
    add_menu_page(self::_, self::_, $required_capability,
                  self::admin_page_settings, self::_('admin_page_settings'), plugins_url('/icon.png', __FILE__));

    add_submenu_page(self::admin_page_settings, self::_, __('Einstellungen', 'LL_mailer'), $required_capability,
                     self::admin_page_settings, self::_('admin_page_settings'));

    add_submenu_page(self::admin_page_settings, self::_, __('Vorlagen', 'LL_mailer'), $required_capability,
                     self::admin_page_templates, self::_('admin_page_templates'));

    add_submenu_page(self::admin_page_settings, self::_, __('Nachrichten', 'LL_mailer'), $required_capability,
                     self::admin_page_messages, self::_('admin_page_messages'));

    add_submenu_page(self::admin_page_settings, self::_, __('Filter', 'LL_mailer'), $required_capability,
                     self::admin_page_filters, self::_('admin_page_filters'));

    add_submenu_page(self::admin_page_settings, self::_, __('Abonnenten', 'LL_mailer'), $required_capability,
                     self::admin_page_subscribers, self::_('admin_page_subscribers'));

    add_submenu_page(self::admin_page_settings, self::_, __('Abonnenten-Attribute', 'LL_mailer'), $required_capability,
                     self::admin_page_subscriber_attributes, self::_('admin_page_subscriber_attributes'));

    add_action('admin_init', self::_('admin_page_settings_general_action'));
  }

  static function admin_page_footer()
  {
    ?>
    <div id="wpfooter" style="text-align: right; position: unset;">
      Datenbank-Version <?=get_option(self::option_version)?>
    </div>
    <?php
  }



  static function admin_page_settings()
  {
    $messages = self::db_get_messages(array('slug', 'subject'));
?>
    <style>
      td.section-description {
        padding-top: 0;
      }
    </style>
    <div class="wrap">
      <h1><?=__('Allgemeine Einstellungen', 'LL_mailer')?></h1>

      <form method="post" action="options.php">
        <?php settings_fields(self::_ . '_general'); ?>
        <table class="form-table">

          <tr>
            <th scope="row"><?=__('Absender', 'LL_mailer')?></th>
          </tr>
          <tr>
            <td colspan="2" class="section-description">
              <p class="description"><?=__('Name und Email-Adresse, die als Absender der Emails verwendet werden sollen.', 'LL_mailer')?></p>
            </td>
          </tr>
          <tr>
            <td <?=self::secondary_settings_label?>>
              <label for="<?=self::option_sender_name?>" title="<?=__('Dein Name, der als Absender der E-Mails genutzt werden soll', 'LL_mailer')?>">
                <?=__('Name', 'LL_mailer')?></label>
            </td>
            <td>
              <input type="text" id="<?=self::option_sender_name?>" name="<?=self::option_sender_name?>" value="<?=esc_attr(get_option(self::option_sender_name))?>" placeholder="Name" class="regular-text" />
            </td>
          </tr>
          <tr>
            <td <?=self::secondary_settings_label?>>
              <label for="<?=self::option_sender_mail?>" title="<?=__('Deine E-Mail Adresse, die als Absender der E-Mails genutzt werden soll', 'LL_mailer')?>">
                <?=__('E-Mail Adresse', 'LL_mailer')?></label>
            </td>
            <td>
              <input type="text" id="<?=self::option_sender_mail?>" name="<?=self::option_sender_mail?>" value="<?=esc_attr(get_option(self::option_sender_mail))?>" placeholder="...@<?=$_SERVER['SERVER_NAME']?>" class="regular-text" />
              &nbsp; <span id="<?=self::option_sender_mail?>_response"></span>
            </td>
          </tr>

          <tr><td colspan="2"><hr /></td></tr>

          <tr>
            <th scope="row" colspan="2"><?=__('Blogseiten', 'LL_mailer')?></th>
          </tr>
          <tr>
            <td colspan="2" class="section-description">
              <p class="description"><?=sprintf(__('Titelform/Slug der %s, zu denen Besucher bei bestimmten Ereignissen weitergeleitet werden sollen.', 'LL_mailer'), sprintf('<a href="' . get_admin_url() . 'edit.php?post_type=page">%s</a>', __('Blogseiten', 'LL_mailer')))?></p>
            </td>
          </tr>
          <tr>
            <td <?=self::secondary_settings_label?>>
              <label for="<?=self::option_subscribe_page?>" title="<?=__('Die Blogseite, auf der Besucher sich einschreiben können', 'LL_mailer')?>">
                <?=__('E-Mails abonnieren', 'LL_mailer')?></label>
            </td>
            <td>
              <input type="text" id="<?=self::option_subscribe_page?>" name="<?=self::option_subscribe_page?>" value="<?=esc_attr(get_option(self::option_subscribe_page))?>" placeholder="Seite" class="regular-text" />
              &nbsp; <span id="<?=self::option_subscribe_page?>_response"></span>
              <p class="description"><?=sprintf(__('Nutze <code>%s</code> um das Anmeldeformular einzufügen.', 'LL_mailer'), self::shortcode_SUBSCRIPTION_FORM['html'])?></p>
            </td>
          </tr>
          <tr>
            <td <?=self::secondary_settings_label?>>
              <label for="<?=self::option_form_submitted_page?>" title="<?=__('Die Blogseite, auf die Besucher weitergeleitet werden, wenn sie das Anmeldeformular abgeschickt haben', 'LL_mailer')?>">
                <?=__('Bestätigungs-Email abgeschickt', 'LL_mailer')?></label>
            </td>
            <td>
              <input type="text" id="<?=self::option_form_submitted_page?>" name="<?=self::option_form_submitted_page?>" value="<?=esc_attr(get_option(self::option_form_submitted_page))?>" placeholder="Seite" class="regular-text" />
              &nbsp; <span id="<?=self::option_form_submitted_page?>_response"></span>
              <p class="description"><?=sprintf(__('Nutze <code>%s</code> um Attribute des neuen Abonnenten auf der Seite anzuzeigen.', 'LL_mailer'), self::shortcode_SUBSCRIBER_ATTRIBUTE['html'])?></p>
            </td>
          </tr>
          <tr>
            <td <?=self::secondary_settings_label?> title="<?=__('Die Blogseite, auf die Besucher weitergeleitet werden, wenn sie ihre E-Mail Adresse bestätigt haben', 'LL_mailer')?>">
              <label for="<?=self::option_confirmed_page?>">
                <?=__('Anmeldung abgeschlossen / E-Mail bestätigt', 'LL_mailer')?></label>
            </td>
            <td>
              <input type="text" id="<?=self::option_confirmed_page?>" name="<?=self::option_confirmed_page?>" value="<?=esc_attr(get_option(self::option_confirmed_page))?>" placeholder="Seite" class="regular-text" />
              &nbsp; <span id="<?=self::option_confirmed_page?>_response"></span>
              <p class="description"><?=sprintf(__('Nutze <code>%s</code> um Attribute des neuen Abonnenten auf der Seite anzuzeigen.', 'LL_mailer'), self::shortcode_SUBSCRIBER_ATTRIBUTE['html'])?></p>
            </td>
          </tr>
          <tr>
            <td <?=self::secondary_settings_label?>>
              <label for="<?=self::option_unsubscribed_page?>" title="<?=__('Die Blogseite, auf die Besucher weitergeleitet werden, wenn sie sich abgemeldet haben', 'LL_mailer')?>">
                <?=__('Abo abgemeldet', 'LL_mailer')?></label>
            </td>
            <td>
              <input type="text" id="<?=self::option_unsubscribed_page?>" name="<?=self::option_unsubscribed_page?>" value="<?=esc_attr(get_option(self::option_unsubscribed_page))?>" placeholder="Seite" class="regular-text" />
              &nbsp; <span id="<?=self::option_unsubscribed_page?>_response"></span>
            </td>
          </tr>

          <tr><td colspan="2"><hr /></td></tr>

          <tr>
            <th scope="row" colspan="2"><?=__('E-Mails an Abonnenten', 'LL_mailer')?></th>
          </tr>
          <tr>
            <td colspan="2" class="section-description">
              <p class="description"><?=sprintf(__('Auswahl der %s, die bei bestimmten Ereignissen an (potentielle) Abonnenten gesendet werden.', 'LL_mailer'), sprintf('<a href="' . self::admin_url() . self::admin_page_messages . '">%s</a>', __('Nachrichten', 'LL_mailer')))?></p>
            </td>
          </tr>
          <tr>
            <td <?=self::secondary_settings_label?>>
              <label for="<?=self::option_confirmation_msg?>" title="<?=__('Die Nachricht, die Abonnenten erhalten sollen, um ihre Anmeldung zu bestätigen', 'LL_mailer')?>">
                <?=__('Anmeldung bestätigen', 'LL_mailer')?></label>
            </td>
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
              <p class="description">
                <?=__('Wird aktiviert, sobald eine Nachricht ausgewählt ist.', 'LL_mailer')?><br />
                <?=sprintf(__('Nutze <code>%s</code> um den Bestätigungs-Link im Text einzufügen.', 'LL_mailer'), self::token_CONFIRMATION_URL['html'])?>
              </p>
            </td>
          </tr>
          <tr>
            <td <?=self::secondary_settings_label?>>
              <label for="<?=self::option_new_post_msg?>" title="<?=__('Die Nachricht, die Abonnenten erhalten sollen, wenn du einen neuen Post veröffentlichst', 'LL_mailer')?>">
                <?=__('Neuer Post', 'LL_mailer')?></label>
            </td>
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

          <tr><td colspan="2"><hr /></td></tr>

          <tr>
            <th scope="row" colspan="2"><?=__('E-Mails an dich (Absender)', 'LL_mailer')?></th>
          </tr>
          <tr>
            <td colspan="2" class="section-description">
              <p class="description"><?=sprintf(__('Auswahl der %s, die bei bestimmten Ereignissen an dich (Absender, siehe oben) gesendet werden.', 'LL_mailer'), sprintf('<a href="' . self::admin_url() . self::admin_page_messages . '">%s</a>', __('Nachrichten', 'LL_mailer')))?></p>
            </td>
          </tr>
          <tr>
            <td <?=self::secondary_settings_label?>>
              <label for="<?=self::option_confirmed_admin_msg?>"><?=__('Neuer Abonnent', 'LL_mailer')?></label>
            </td>
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
            <td <?=self::secondary_settings_label?>>
              <label for="<?=self::option_unsubscribed_admin_msg?>"><?=__('Abonnent abgemeldet', 'LL_mailer')?></label>
            </td>
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

          <tr><td colspan="2"><hr /></td></tr>

          <tr>
            <th scope="row" colspan="2">
              <h1><?=__('Anmeldeformular', 'LL_mailer')?></h1>
            </th>
          </tr>
          <tr>
            <td <?=self::secondary_settings_label?>>
              <label for="<?=self::option_show_filter_categories?>" title="<?=__('Post-Kategorien zu den wählbaren Filtern anzeigen', 'LL_mailer')?>">
                <?=__('Post-Kategorien anzeigen', 'LL_mailer')?></label>
            </td>
            <td>
<?php
              $show_categories = get_option(self::option_show_filter_categories);
?>
              <select name="<?=self::option_show_filter_categories?>">
                <option value="" <?=!$show_categories ? 'selected' : ''?>><?=__('Nicht anzeigen', 'LL_mailer')?></option>
                <option value="brackets" <?=$show_categories === 'brackets' ? 'selected' : ''?>><?=__('In Klammern', 'LL_mailer')?></option>
                <option value="tooltip" <?=$show_categories === 'tooltip' ? 'selected' : ''?>><?=__('Als Tooltip', 'LL_mailer')?></option>
              </select>
            </td>
          </tr>
          <tr>
            <th scope="row" colspan="2">
              <?=__('Spam-Erkennung für die Anmeldung / Variante 1: Zufällige Frage', 'LL_mailer')?>
            </th>
          </tr>
          <tr>
            <td colspan="2" class="section-description">
              <p class="description"><?=__('Eine der folgenden Fragen wird zufällig ausgewählt und muss richtig beantwortet werden.', 'LL_mailer')?></p>
            </td>
          </tr>
          <tr>
            <td></td>
            <td>
              <p class="description">
                <?=implode('<br />', array_map(function($item) { return $item[0] . ' (' . $item[1] . ')'; }, self::robot_questions))?>
              </p>
            </td>
          </tr>
          <tr>
            <td></td>
            <td>
              <label>
                <input type="checkbox" id="<?=self::option_use_robot_check?>" name="<?=self::option_use_robot_check?>" <?=get_option(self::option_use_robot_check) ? 'checked' : ''?> />
                <?=__('Aktivieren', 'LL_mailer')?>
              </label>
            </td>
          </tr>

          <tr>
            <th scope="row" colspan="2">
              <?=__('Spam-Erkennung für die Anmeldung / Variante 2: reCAPTCHA v2 (Google)', 'LL_mailer')?>
            </th>
          </tr>
          <tr>
            <td colspan="2" class="section-description">
              <p class="description"><?=preg_replace('/%(.+)%/', '<a href="https://www.google.com/recaptcha/admin/" target="_blank">$1</a>', __('Wird aktiviert, sobald beide %Schlüssel% eingetragen sind.', 'LL_mailer'))?></p>
            </td>
          </tr>
          <tr>
            <td <?=self::secondary_settings_label?>>
              <label for="<?=self::option_recaptcha_website_key?>"><?=__('Webseitenschlüssel', 'LL_mailer')?></label>
            </td>
            <td>
              <input type="text" id="<?=self::option_recaptcha_website_key?>" name="<?=self::option_recaptcha_website_key?>" value="<?=esc_attr(get_option(self::option_recaptcha_website_key))?>" placeholder="Code" class="regular-text" />
            </td>
          </tr>
          <tr>
            <td <?=self::secondary_settings_label?>>
              <label for="<?=self::option_recaptcha_secret_key?>"><?=__('Geheimer Schlüssel', 'LL_mailer')?></label>
            </td>
            <td>
              <input type="text" id="<?=self::option_recaptcha_secret_key?>" name="<?=self::option_recaptcha_secret_key?>" value="<?=esc_attr(get_option(self::option_recaptcha_secret_key))?>" placeholder="Code" class="regular-text" />
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
          check_page_exists('<?=self::option_form_submitted_page?>');
          check_page_exists('<?=self::option_confirmed_page?>');
          check_page_exists('<?=self::option_unsubscribed_page?>');
          link_message('<?=self::option_confirmation_msg?>');
          link_message('<?=self::option_new_post_msg?>');
          link_message('<?=self::option_confirmed_admin_msg?>');
          link_message('<?=self::option_unsubscribed_admin_msg?>');
        };
      </script>
    </div>
<?php
    self::admin_page_footer();
  }

  static function admin_page_settings_general_action()
  {
    // Save changed settings via WordPress
    register_setting(self::_ . '_general', self::option_sender_name);
    register_setting(self::_ . '_general', self::option_sender_mail);
    register_setting(self::_ . '_general', self::option_subscribe_page);
    register_setting(self::_ . '_general', self::option_show_filter_categories);
    register_setting(self::_ . '_general', self::option_form_submitted_page);
    register_setting(self::_ . '_general', self::option_confirmation_msg);
    register_setting(self::_ . '_general', self::option_confirmed_admin_msg);
    register_setting(self::_ . '_general', self::option_confirmed_page);
    register_setting(self::_ . '_general', self::option_unsubscribed_admin_msg);
    register_setting(self::_ . '_general', self::option_unsubscribed_page);
    register_setting(self::_ . '_general', self::option_new_post_msg);
    register_setting(self::_ . '_general', self::option_recaptcha_website_key);
    register_setting(self::_ . '_general', self::option_recaptcha_secret_key);
    register_setting(self::_ . '_general', self::option_use_robot_check);
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
            <th scope="row"><?=__('Name für neue Vorlage', 'LL_mailer')?></th>
            <td>
              <input type="text" name="template_slug" placeholder="<?=__('meine-vorlage', 'LL_mailer')?>" class="regular-text" /> &nbsp;
              <?php submit_button(__('Neue Vorlage anlegen', 'LL_mailer'), 'primary', '', false); ?>
            </td>
            </tr>
          </table>
        </form>

        <hr />

        <h1><?=__('Gespeicherte Vorlagen', 'LL_mailer')?></h1>
        <p></p>
        <table class="widefat fixed striped">
          <tr>
            <th><?=__('Name', 'LL_mailer')?></th>
            <th><?=__('Zuletzt bearbeitet', 'LL_mailer')?></th>
          </tr>
<?php
          $templates = self::db_get_templates(array('slug', 'last_modified'));
          $edit_url = self::admin_url() . self::admin_page_template_edit;
          foreach ($templates as &$template) {
?>
            <tr>
              <td><a href="<?=$edit_url . $template['slug']?>" class="row-title"><?=$template['slug']?></a></td>
              <td><?=$template['last_modified']?></td>
            </tr>
<?php
          }
?>
        </table>
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
                    self::html_prefix . preg_replace(self::token_ATTACH['pattern'], '$1', $template['body_html']) . self::html_suffix
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
              preview.contentWindow.document.body.innerHTML = this.value.replace(<?=self::token_ATTACH['pattern']?>g, '$1');
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
            <?php submit_button(__('Vorlage löschen', 'LL_mailer'), '', 'submit', true, array('onclick' => 'return confirm(\'Wirklich löschen?\nDie Vorlage kann nicht wiederhergestellt werden.\')')); ?>
          </form>
<?php
        }
      } break;
    }
?>
    </div>
<?php
    self::admin_page_footer();
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
    else if (isset($_GET['abo_mail_preview'])) $sub_page = 'abo_mail_preview';
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
            <th scope="row"><?=__('Name für neue Nachricht', 'LL_mailer')?></th>
            <td>
              <input type="text" name="message_slug" placeholder="<?=__('meine-nachricht', 'LL_mailer')?>" class="regular-text" /> &nbsp;
              <?php submit_button(__('Neue Nachricht anlegen', 'LL_mailer'), 'primary', '', false); ?>
            </td>
            </tr>
          </table>
        </form>

        <hr />

        <h1><?=__('Gespeicherte Nachrichten', 'LL_mailer')?></h1>
        <p></p>
        <table class="widefat fixed striped">
          <tr>
            <th><?=__('Name', 'LL_mailer')?></th>
            <th><?=__('Betreff', 'LL_mailer')?></th>
            <th><?=__('Vorlage', 'LL_mailer')?></th>
            <th><?=__('Zuletzt bearbeitet', 'LL_mailer')?></th>
          </tr>
<?php
          $messages = self::db_get_messages(array('slug', 'subject', 'template_slug', 'last_modified'));
          $edit_url = self::admin_url() . self::admin_page_message_edit;
          foreach ($messages as &$message) {
?>
            <tr>
              <td><a href="<?=$edit_url . urlencode($message['slug'])?>" class="row-title"><?=$message['slug']?></a></td>
              <td><a href="<?=$edit_url . urlencode($message['slug'])?>"><?=$message['subject'] ?: '<i>(kein Betreff)</i>'?></a></td>
              <td><?=$message['template_slug']?></td>
              <td><?=$message['last_modified']?></td>
            </tr>
<?php
          }
?>
        </table>
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
              <td <?=self::secondary_settings_label?>><?=__('Vorschau (HTML)', 'LL_mailer')?></td>
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
              <td <?=self::secondary_settings_label?>><?=__('Vorschau (Text)', 'LL_mailer')?></td>
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

        <h1><?=__('Optionen für Vorschau / Testnachricht', 'LL_mailer')?></h1>
        <br />

<?php
        $subscribers = self::db_get_subscribers(false, false, true);
        $test_posts = new WP_Query(array(
          'post_type' => 'post',
//          'orderby' => array(
//            'date' => 'DESC'
//          ),
          'posts_per_page' => 10
        ));
        if (empty($subscribers)) {
?>
          <i><?=__('Es wird mindestens ein Abonnent benötigt, der als Testempfänger markiert ist.', 'LL_mailer')?></i>
<?php
        }
        else {
?>
          <div id="<?=self::_?>_testmail">
            <p><span class="description" id="<?=self::_?>_testmail_preview_response"></span></p>
            <input type="hidden" name="msg" value="<?=$message_slug?>" />
            <p>
              <select id="to" name="to">
<?php
            foreach ($subscribers as &$subscriber) {
?>
                <option value="<?=$subscriber[self::subscriber_attribute_mail]?>"><?=$subscriber[self::subscriber_attribute_name] . ' / ' . $subscriber[self::subscriber_attribute_mail]?></option>
<?php
            }
?>
              </select>
            </p>
            <p>
              <select id="post" name="post">
<?php
            foreach ($test_posts->posts as $post) {
              $cats = wp_get_post_categories($post->ID);
              array_walk($cats, function(&$cat, $key) { $cat = get_category($cat)->name; });
?>
                <option value="<?=$post->ID?>"><?=$post->post_title?> (<?=implode(', ', $cats)?>)</option>
<?php
            }
?>
                <option value="" style="color: gray;">(<?=__('Kein Test-Post')?>)</option>
              </select>
            </p>
            <p>
              <input type="checkbox" id="is-abo-mail" name="is-abo-mail" checked /><label for="is-abo-mail"> Ist Abo E-Mail</label>
            </p>
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
              var testmail_is_abo_mail_select = document.querySelector('#<?=self::_?>_testmail #is-abo-mail');
              var testmail_preview_response_tag = document.querySelector('#<?=self::_?>_testmail_preview_response');
              var testmail_send_response_tag = document.querySelector('#<?=self::_?>_testmail_send_response');
              function request_message_preview() {
                testmail_to_select.disabled = true;
                testmail_post_select.disabled = true;
                testmail_is_abo_mail_select.disabled = true;
                testmail_preview_response_tag.innerHTML = '...';
                jQuery.getJSON('<?=self::json_url() . 'testmail?preview&msg=' . $message_slug . '&to='?>' + encodeURIComponent(testmail_to_select.value) + '&post=' + testmail_post_select.value + '&is-abo-mail=' + (testmail_is_abo_mail_select.checked ? 1 : 0), function (response) {
                  testmail_to_select.disabled = false;
                  testmail_post_select.disabled = false;
                  testmail_is_abo_mail_select.disabled = false;
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
              jQuery(testmail_is_abo_mail_select).on('change', request_message_preview);
              request_message_preview();
              jQuery('#send_testmail').click(function (e) {
                var select_tag = this;
                select_tag.disabled = true;
                testmail_send_response_tag.innerHTML = '...';
                jQuery.getJSON('<?=self::json_url() . 'testmail?send&msg=' . $message_slug . '&to='?>' + encodeURIComponent(testmail_to_select.value) + '&post=' + testmail_post_select.value + '&is-abo-mail=' + (testmail_is_abo_mail_select.checked ? 1 : 0), function (response) {
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
        $msg_in_use_error = false;
        if ($message_slug == get_option(self::option_confirmation_msg)) {
          $msg_in_use_error = __('Diese Nachricht kann nicht gelöscht werden, da sie als Bestätigungs-E-Mail verwendet wird.', 'LL_mailer');
        }
        else if ($message_slug == get_option(self::option_new_post_msg)) {
          $msg_in_use_error = __('Diese Nachricht kann nicht gelöscht werden, da sie als Benachrichtigung für neue Posts verwendet wird.', 'LL_mailer');
        }
        else if ($message_slug == get_option(self::option_confirmed_admin_msg)) {
          $msg_in_use_error = __('Diese Nachricht kann nicht gelöscht werden, da sie als Benachrichtigung (an dich) für neue Abonnenten verwendet wird.', 'LL_mailer');
        }
        else if ($message_slug == get_option(self::option_unsubscribed_admin_msg)) {
          $msg_in_use_error = __('Diese Nachricht kann nicht gelöscht werden, da sie als Benachrichtigung (an dich) für abgemeldete Abonnenten verwendet wird.', 'LL_mailer');
        }

        if ($msg_in_use_error !== false) {
?>
          <p class="description">
            <?=$msg_in_use_error?><br />
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
          <?php submit_button(__('Nachricht löschen', 'LL_mailer'), '', 'submit', true, array('onclick' => 'return confirm(\'Wirklich löschen?\nDie Nachricht kann nicht wiederhergestellt werden.\')')); ?>
        </form>
<?php
        }
      } break;

      case 'abo_mail_preview':
      {
        $errors = array();
        if (!isset($_GET['abo_mail_preview'])) {
          $errors[] = __('Keine Nachricht angegeben', 'LL_mailer');
        }
        if (!isset($_GET['to'])) {
          $errors[] = __('Kein Empfänger angegeben', 'LL_mailer');
        }
        if (empty($errors)) {
          $msg = $_GET['abo_mail_preview'];
          $post_id = $_GET['post'] ?: null;
          $mail_or_error = self::prepare_mail(
            $msg,
            self::get_sender(),
            true,
            $post_id,
            true,
            true,
            'preview');
          if (is_string($mail_or_error)) {
            $errors[] = $mail_or_error;
          }
        }
        if (!empty($errors)) {
          self::inline_message(implode('<br />', $errors));
          break;
        }

        list($to, $subject, $body_html, $body_text, $attachments, $replace_dict) = $mail_or_error;
        ?>

        <h1><?=__('Nachricht an Abonnenten senden', 'LL_mailer')?></h1>

        <?php wp_nonce_field(self::_ . '_message_edit'); ?>
        <input type="hidden" name="message_slug" value="<?=$msg?>" />
        <table class="form-table">
          <tr>
            <th><?=__('Betreff', 'LL_mailer')?></th>
            <td>
              <input disabled id="subject_preview" type="text" value="<?=esc_attr($subject)?>" style="width: 100%; color: black; background: white;" />
            </td>
          </tr>
          <tr>
            <th><?=__('Vorschau (HTML)', 'LL_mailer')?></th>
            <td>
              <iframe id="body_html_preview" style="width: 100%; height: 500px; resize: vertical; border: 1px solid #ddd; background: white;" srcdoc="<?=htmlspecialchars(
                  self::html_prefix . $body_html . self::html_suffix
                )?>">
              </iframe>
            </td>
          </tr>
          <tr>
            <th><?=__('Vorschau (Text)', 'LL_mailer')?></th>
            <td>
              <textarea disabled id="body_text_preview" style="width: 100%; height: 500px; color:black; background: white;"><?=$body_text?></textarea>
            </td>
          </tr>
          <tr>
            <th><?=__('Filter (Post-Kategorien)', 'LL_mailer')?></th>
            <td>
              <?php
              $post_category_ids = wp_get_post_categories($post_id);
              $post_categories = array();
              $tmp_categories = get_categories();
              foreach ($tmp_categories as &$cat) {
                if (in_array($cat->term_id, $post_category_ids)) {
                  $post_categories[] = $cat->name;
                }
              }
              $filters = self::db_get_filters_by_categories('label', $post_category_ids);
              echo implode(', ', array_map(function($a) { return $a['label']; }, $filters)) . ' (' . implode(', ', $post_categories) . ')';
              ?>
            </td>
          </tr>
          <tr>
            <th><?=__('Empfänger', 'LL_mailer')?></th>
            <td>
              <?php
              $receivers = self::db_get_subscribers(true, true);
              $receivers = self::filter_subscribers_by_post($receivers, $post_id);
              echo implode(', ', array_map(function($r) { return $r[self::subscriber_attribute_name]; }, $receivers));
              ?>
            </td>
          </tr>
          <tr>
            <td>
              <form method="post" action="admin-post.php">
                <input type="hidden" name="action" value="<?=self::_?>_message_action" />
                <?php wp_nonce_field(self::_ . '_send_abo_mail'); ?>
                <input type="hidden" name="original_referrer" value="<?=wp_get_referer()?>" />
                <input type="hidden" name="message_slug" value="<?=$msg?>" />
                <input type="hidden" name="abo_mail_to" value="<?=$_GET['to']?>" />
                <input type="hidden" name="abo_mail_post" value="<?=$post_id?>" />
                <?php submit_button(__('E-Mails jetzt senden', 'LL_mailer'), 'primary', '', false); ?>
              </form>
            </td>
          </tr>
        </table>
<?php
      } break;
    }
?>
    </div>
<?php
    self::admin_page_footer();
  }

  static function admin_page_message_action()
  {
    if (!empty($_POST) && isset($_POST['_wpnonce'])) {
      $message_slug = $_POST['message_slug'] ?? '';
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

        else if (wp_verify_nonce($_POST['_wpnonce'], self::_ . '_send_abo_mail')) {
          self::send_abo_mails(array(
            'msg' => $message_slug,
            'post' => $_POST['abo_mail_post'],
            'to' => $_POST['abo_mail_to'],
            'redirect_to' => $_POST['original_referrer']));
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
        <p></p>
        <table class="widefat fixed striped">
          <tr>
            <th><?=__('Name', 'LL_mailer')?></th>
            <th><?=__('E-Mail Adresse', 'LL_mailer')?></th>
            <th><?=__('Filter', 'LL_mailer')?></th>
            <th><?=__('Anmeldung / Status', 'LL_mailer')?></th>
          </tr>
<?php
          $filters = self::array_assoc_by(self::db_get_filters(array('id', 'label')));
          $subscribers = self::db_get_subscribers();
          $edit_url = self::admin_url() . self::admin_page_subscriber_edit;
          usort($subscribers, function($a, $b) {
            return (intval(empty($b['subscribed_at'])) - intval(empty($a['subscribed_at']))) ?: strcmp(strtolower($a[self::subscriber_attribute_mail]), strtolower($b[self::subscriber_attribute_mail]));
          });

          foreach ($subscribers as &$subscriber) {
            $meta = json_decode($subscriber['meta'], true);
?>
          <tr>
            <td>
              <a href="<?=$edit_url . urlencode($subscriber[self::subscriber_attribute_mail])?>" class="row-title">
                <?=$subscriber[self::subscriber_attribute_name] ?? ('<i style="font-weight: normal;">' . __('kein Name', 'LL_mailer') . '</i>')?>
              </a>
            </td>
            <td>
              <a href="<?=$edit_url . urlencode($subscriber[self::subscriber_attribute_mail])?>">
                <?=$subscriber[self::subscriber_attribute_mail]?>
              </a>
            </td>
            <td>
<?php
              $subscriber_filters = self::db_get_filter_ids_by_subscriber($subscriber[self::subscriber_attribute_id]);
              array_walk($subscriber_filters, function(&$filter_id, $key) use ($filters) { $filter_id = $filters[$filter_id]['label']; });
              echo implode(', ', $subscriber_filters);
?>
            </td>
            <td>
<?php
              $date = null;
              $status = array();
              if (!empty($subscriber['subscribed_at'])) {
                $date = $subscriber['subscribed_at'];
              }
              else {
                if (isset($meta[self::meta_submitted_at])) {
                  $date = date('Y-m-d H:i:s', $meta[self::meta_submitted_at]);
                }
                $status[] = __('Unbestätigt', 'LL_mailer');
              }
              if (isset($meta[self::meta_disabled]) && $meta[self::meta_disabled]) {
                $status[] = __('Deaktiviert', 'LL_mailer');
              }
              if (isset($meta[self::meta_test_receiver]) && $meta[self::meta_test_receiver]) {
                $status[] = __('Testempfänger', 'LL_mailer');
              }

              $status_output = array();
              if (!is_null($date)) {
                $status_output[] = $date;
              }
              if (!empty($status)) {
                $status_output[] = implode(', ', $status);
              }
              echo implode(' / ', $status_output);
?>
            </td>
          </tr>
<?php
          }
?>
        </table>
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
        $meta = json_decode($subscriber[self::subscriber_attribute_meta], true);
?>
        <h1><?=__('Abonnenten', 'LL_mailer')?> &gt; <?=$subscriber_mail?></h1>

        <form method="post" action="admin-post.php">
          <input type="hidden" name="action" value="<?=self::_?>_subscriber_action" />
          <?php wp_nonce_field(self::_ . '_subscriber_edit'); ?>
          <input type="hidden" name="subscriber_id" value="<?=$subscriber['id']?>" />
          <input type="hidden" name="subscriber_mail" value="<?=$subscriber_mail?>" />
          <table class="form-table">
<?php
            $attributes = self::get_option_array(self::option_subscriber_attributes);
            foreach ($attributes as $attr => $attr_label) {
?>
            <tr>
              <th scope="row"><?=$attr_label?></th>
              <td>
                <input type="text" name="attr_<?=$attr?>" value="<?=esc_attr($subscriber[$attr])?>" placeholder="<?=$attr_label?>" class="regular-text" />
              </td>
            </tr>
<?php
            }
            $filters = self::db_get_filters(array('id', 'label'));
            $subscriber_filters = self::db_get_filter_ids_by_subscriber($subscriber[self::subscriber_attribute_id]);
?>
            <tr>
              <th scope="row"><?=__('Filter', 'LL_mailer')?></th>
              <td>
                <select name="filters[]" multiple size="5" class="regular-text">
<?php
                foreach ($filters as &$filter) {
                  $selected = (in_array($filter['id'], $subscriber_filters)) ? 'selected' : '';
?>
                  <option value="<?=$filter['id']?>" <?=$selected?>><?=$filter['label']?></option>
<?php
                }
?>
                </select>
              </td>
            </tr>
            <tr>
              <th scope="row"><?=__('Deaktiviert', 'LL_mailer')?></th>
              <td>
                <input type="checkbox" name="meta_<?=self::meta_disabled?>" <?=(isset($meta[self::meta_disabled]) && $meta[self::meta_disabled]) ? 'checked' : ''?> />
              </td>
            </tr>
            <tr>
              <th scope="row"><?=__('Testempfänger', 'LL_mailer')?></th>
              <td>
                <input type="checkbox" name="meta_<?=self::meta_test_receiver?>" <?=(isset($meta[self::meta_test_receiver]) && $meta[self::meta_test_receiver]) ? 'checked' : ''?> />
              </td>
            </tr>
          </table>
          <?php submit_button(__('Abonnent speichern', 'LL_mailer')); ?>
        </form>

        <table class="form-table">
          <tr>
            <th scope="row"><?=__('Weitere Daten', 'LL_mailer')?></th>
          </tr>
          <tr>
            <td <?=self::secondary_settings_label?>><?=__('Bestätigt am', 'LL_mailer')?></td>
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
<?php
          $meta_not_stored = '<i>' . __('nicht gespeichert', 'LL_mailer') . '</i>';
?>
          <tr>
            <td <?=self::secondary_settings_label?>><?=__('Anmeldung am', 'LL_mailer')?></td>
            <td><?=isset($meta[self::meta_submitted_at]) ? date('Y-m-d H:i:s', $meta[self::meta_submitted_at]) : $meta_not_stored?></td>
          </tr>
          <tr>
            <td <?=self::secondary_settings_label?>><?=__('IP', 'LL_mailer')?></td>
            <td><?=$meta[self::meta_ip] ?? $meta_not_stored?></td>
          </tr>
        </table>

        <hr />

        <h1><?=__('Löschen', 'LL_mailer')?></h1>

        <form method="post" action="admin-post.php">
          <input type="hidden" name="action" value="<?=self::_?>_subscriber_action" />
          <?php wp_nonce_field(self::_ . '_subscriber_delete'); ?>
          <input type="hidden" name="subscriber_mail" value="<?=$subscriber_mail?>" />
          <?php submit_button(__('Abonnent löschen', 'LL_mailer'), '', 'submit', true, array('onclick' => 'return confirm(\'Wirklich löschen?\nDie Daten des Abonnenten können nicht wiederhergestellt werden.\')')); ?>
        </form>
<?php
      } break;
    }
?>
    </div>
<?php
    self::admin_page_footer();
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
          $subscriber_id = $_POST['subscriber_' . self::subscriber_attribute_id];
          $new_subscriber_mail = trim($_POST['attr_' . self::subscriber_attribute_mail]);
          if (!filter_var($new_subscriber_mail, FILTER_VALIDATE_EMAIL)) {
            self::message(sprintf(__('Die neue E-Mail Adresse <b>%s</b> ist ungültig.', 'LL_mailer'), $new_subscriber_mail));
            wp_redirect(self::admin_url() . self::admin_page_subscriber_edit . urlencode($subscriber_mail));
            exit;
          }

          $attributes = self::get_option_array(self::option_subscriber_attributes);
          $subscriber = array();
          foreach (array_keys($attributes) as $attr) {
            $subscriber[$attr] = $_POST['attr_' . $attr] ?: null;
          }
          $subscriber[self::subscriber_attribute_mail] = $new_subscriber_mail;

          $meta = json_decode($subscriber[self::subscriber_attribute_meta], true);
          if (isset($_POST['meta_' . self::meta_disabled])) {
            $meta[self::meta_disabled] = true;
          }
          else if (isset($meta[self::meta_disabled])) {
            unset($meta[self::meta_disabled]);
          }
          if (isset($_POST['meta_' . self::meta_test_receiver])) {
            $meta[self::meta_test_receiver] = true;
          }
          else if (isset($meta[self::meta_test_receiver])) {
            unset($meta[self::meta_test_receiver]);
          }
          $subscriber[self::subscriber_attribute_meta] = addslashes(json_encode($meta));

          self::db_update_subscriber($subscriber, $subscriber_mail);

          self::db_delete_subscriptions($subscriber_id);
          self::db_add_subscriptions($subscriber_id, $_POST['filters']);

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



  static function admin_page_subscriber_attributes()
  {
    $attributes = self::get_option_array(self::option_subscriber_attributes);
    $attribute_groups = array(
      'predefined' => array(
        self::subscriber_attribute_mail => $attributes[self::subscriber_attribute_mail],
        self::subscriber_attribute_name => $attributes[self::subscriber_attribute_name]),
      'dynamic' => array_filter($attributes, function($key) { return !self::is_predefined_subscriber_attribute($key); }, ARRAY_FILTER_USE_KEY));
?>
    <div class="wrap">
      <h1><?=__('Abonnenten-Attibute', 'LL_mailer')?></h1>
      <p></p>
      <table class="widefat fixed striped">
        <tr>
          <td><?=__('Attribut', 'LL_mailer')?></td>
          <td colspan="2"><?=__('Attribut-Slug', 'LL_mailer')?></td>
        </tr>
<?php
        foreach ($attribute_groups as $group => &$attrs) {
          foreach ($attrs as $attr => $attr_label) {
            $form_id = self::_ . '_form_subscriber_attribute_' . $attr;
?>
        <tr>
          <td>
            <form method="post" action="admin-post.php" style="display: inline;" id="<?=$form_id?>">
              <input type="hidden" name="action" value="<?=self::_?>_subscriber_attributes_action" />
              <?php wp_nonce_field(self::_ . '_subscriber_attribute_edit'); ?>
              <input type="hidden" name="attribute" value="<?=$attr?>" />
              <input type="text" name="new_attribute_label" value="<?=$attr_label?>" class="regular-text" />
            </form>
          </td>
          <td>
            <code>"<?=$attr?>"</code>
          </td>
          <td>
            <?php submit_button(__('Speichern', 'LL_mailer'), '', 'submit', false, array('style' => 'vertical-align: baseline;', 'form' => $form_id)); ?>
<?php
            if ($group == 'dynamic') {
?>
            <form method="post" action="admin-post.php" style="display: inline;">
              <input type="hidden" name="action" value="<?=self::_?>_subscriber_attributes_action" />
              <?php wp_nonce_field(self::_ . '_subscriber_attribute_delete'); ?>
              <input type="hidden" name="attribute" value="<?=$attr?>" />
              <?php submit_button(__('Löschen', 'LL_mailer'), '', 'submit', false, array('style' => 'vertical-align: baseline;', 'onclick' => 'return confirm(\'Wirklich löschen?\nDie entsprechenden Daten der Abonnenten gehen dabei verloren.\')')); ?>
            </form>
<?php
            }
?>
          </td>
        </tr>
<?php
          }
        }
        $new_form_id = self::_ . '_form_new_subscriber_attribute';
?>
          <tr>
            <td colspan="2">
              <form method="post" action="admin-post.php" style="display: inline;" id="<?=$new_form_id?>">
                <input type="hidden" name="action" value="<?=self::_?>_subscriber_attributes_action" />
                <?php wp_nonce_field(self::_ . '_subscriber_attribute_add'); ?>
                <input type="text" name="attribute" placeholder="<?=__('Neues Attribut', 'LL_mailer')?>" class="regular-text" />
              </td>
              <td>
                <?php submit_button(__('Hinzufügen', 'LL_mailer'), '', 'submit', false, array('style' => 'vertical-align: baseline;', 'form' => $new_form_id)); ?>
              </td>
            </form>
          </tr>
        </table>
    </div>
<?php
    self::admin_page_footer();
  }

  static function admin_page_subscriber_attributes_action()
  {
    if (!empty($_POST) && isset($_POST['_wpnonce'])) {
      $attribute = trim($_POST['attribute']);
      if (!empty($attribute)) {
        if (wp_verify_nonce($_POST['_wpnonce'], self::_ . '_subscriber_attribute_add')) {
          $attribute_label = $attribute;
          $attribute = sanitize_title($attribute);
          if (!empty($attribute)) {
            if (self::is_predefined_subscriber_attribute($attribute)) {
              self::message(sprintf(__('Es existiet bereits ein Standard-Abonnenten-Attribut <b>%s</b>.', 'LL_mailer'), $attribute));
              wp_redirect(self::admin_url() . self::admin_page_subscriber_attributes);
              exit;
            }
            $attributes = self::get_option_array(self::option_subscriber_attributes);
            if (in_array($attribute, array_keys($attributes))) {
              self::message(sprintf(__('Ein Abonnenten-Attribut <b>%s</b> existiert bereits.', 'LL_mailer'), $attribute_label));
              wp_redirect(self::admin_url() . self::admin_page_subscriber_attributes);
              exit;
            }

            $attributes[$attribute] = $attribute_label;
            update_option(self::option_subscriber_attributes, $attributes);
            self::db_subscribers_add_attribute($attribute);

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
              wp_redirect(self::admin_url() . self::admin_page_subscriber_attributes);
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
          if (self::is_predefined_subscriber_attribute($attribute)) {
            self::message(sprintf(__('Das Standard-Abonnenten-Attribut <b>%s</b> kann nicht gelöscht werden.', 'LL_mailer'), $attribute));
            wp_redirect(self::admin_url() . self::admin_page_subscriber_attributes);
            exit;
          }

          $attributes = self::get_option_array(self::option_subscriber_attributes);

          $attribute_label = $attributes[$attribute];
          unset($attributes[$attribute]);
          update_option(self::option_subscriber_attributes, $attributes);
          self::db_subscribers_delete_attribute($attribute);

          self::message(sprintf(__('Abonnenten-Attribut <b>%s</b> gelöscht.', 'LL_mailer'), $attribute_label));
        }
      }
    }
    wp_redirect(self::admin_url() . self::admin_page_subscriber_attributes);
    exit;
  }



  static function admin_page_filters()
  {
    $filters = self::db_get_filters('*');
    $categories = get_categories();
?>
    <div class="wrap">
      <h1><?=__('Filter', 'LL_mailer')?></h1>
      <p></p>
      <style>
      .new-filter-row td {
        border-top: 1px solid black;
      }
      </style>
      <table class="widefat fixed striped">
        <tr>
          <td><?=__('Filter', 'LL_mailer')?></td>
          <td colspan="2"><?=__('Post-Kategorien', 'LL_mailer')?></td>
        </tr>
<?php
        foreach ($filters as &$filter) {
          $form_id = self::_ . '_form_filter_' . $filter['id'];
          $filter_categories = self::explode_filter_categories($filter['categories']);
?>
        <tr>
          <td>
            <form method="post" action="admin-post.php" style="display: inline;" id="<?=$form_id?>">
              <input type="hidden" name="action" value="<?=self::_?>_filters_action" />
              <?php wp_nonce_field(self::_ . '_filter_edit'); ?>
              <input type="hidden" name="filter" value="<?=$filter['id']?>" />
              <p>
                <input type="text" name="new_filter_label" value="<?=$filter['label']?>" class="regular-text" />
              </p>
              <p>
                <label><input type="checkbox" name="new_filter_preselected" <?=$filter['preselected'] ? 'checked' : ''?> /> <?=__('Vorausgewählt bei der Anmeldung', 'LL_mailer')?></label>
              </p>
            </form>
          </td><td>
            <select name="new_filter_categories[]" multiple size="5" class="regular-text" form="<?=$form_id?>">
<?php
            foreach ($categories as &$cat) {
              $selected = ($filter_categories !== self::all_posts && in_array($cat->term_id, $filter_categories)) ? 'selected' : '';
 ?>
              <option value="<?=$cat->term_id?>" <?=$selected?>><?=$cat->name?></option>
<?php
            }
 ?>
            </select><br />
            <label><input type="checkbox" name="new_filter_all_categories" form="<?=$form_id?>" <?=$filter_categories === self::all_posts ? 'checked' : ''?> /> <?=__('Alle Kategorien', 'LL_mailer')?></label>
          </td><td>
            <?php submit_button(__('Speichern', 'LL_mailer'), '', 'submit', false, array('style' => 'vertical-align: baseline;', 'form' => $form_id)); ?>

            <form method="post" action="admin-post.php" style="display: inline;">
              <input type="hidden" name="action" value="<?=self::_?>_filters_action" />
              <?php wp_nonce_field(self::_ . '_filter_delete'); ?>
              <input type="hidden" name="filter" value="<?=$filter['id']?>" />
              <?php submit_button(__('Löschen', 'LL_mailer'), '', 'submit', false, array('style' => 'vertical-align: baseline;', 'onclick' => 'return confirm(\'Wirklich löschen?\nDie Zuordnung der Abonnenten geht dabei verloren.\')')); ?>
            </form>
          </td>
        </tr>
<?php
        }
        $new_form_id = self::_ . '_form_new_filter';
?>
        <tr class="new-filter-row">
          <td>
            <form method="post" action="admin-post.php" style="display: inline;" id="<?=$new_form_id?>">
              <input type="hidden" name="action" value="<?=self::_?>_filters_action" />
              <?php wp_nonce_field(self::_ . '_filter_add'); ?>
              <p>
                <input type="text" name="filter" placeholder="<?=__('Neuer Filter', 'LL_mailer')?>" class="regular-text" /><br />
              </p>
              <p>
                <label><input type="checkbox" name="filter_preselected" /> <?=__('Vorausgewählt bei der Anmeldung', 'LL_mailer')?></label>
              </p>
            </form>
          </td><td>
            <select name="filter_categories[]" multiple size="5" placeholder="<?=__('Post-Kategorien', 'LL_mailer')?>" class="regular-text" form="<?=$new_form_id?>">
<?php
            foreach ($categories as &$cat) {
 ?>
              <option value="<?=$cat->term_id?>"><?=$cat->name?></option>
<?php
            }
 ?>
            </select><br />
            <input type="checkbox" name="filter_all_categories" form="<?=$new_form_id?>" />
          </td><td>
            <?php submit_button(__('Hinzufügen', 'LL_mailer'), '', 'submit', false, array('style' => 'vertical-align: baseline;', 'form' => $new_form_id)); ?>
          </td>
        </tr>
      </table>
    </div>
    <script>
      new function() {
        let cats = document.querySelectorAll('[name="new_filter_categories[]"]');
        let checks = document.querySelectorAll('[name="new_filter_all_categories"]');
        for (let i = 0; i < cats.length; ++i)
        {
          let check = checks[i];
          let cat = cats[i];
          cat.disabled = check.checked;
          check.addEventListener('change', function() {
            cat.disabled = check.checked;
          });
        }
      };
    </script>
<?php
    self::admin_page_footer();
  }

  static function admin_page_filters_action()
  {
    if (!empty($_POST) && isset($_POST['_wpnonce'])) {
      $filter_id_str = trim($_POST['filter']);
      if (strlen($filter_id_str) !== 0) {
        $filter_id = intval($filter_id_str);
        if (wp_verify_nonce($_POST['_wpnonce'], self::_ . '_filter_add')) {
          $filter_label = $filter_id_str;
          $filter_all_categories = !!$_POST['filter_all_categories'];
          $filter_categories = $_POST['filter_categories'];
          if (!empty($filter_label) && ($filter_all_categories || !empty($filter_categories))) {
            if (self::db_filter_exists(array('label' => $filter_label))) {
              self::message(sprintf(__('Ein Filter <b>%s</b> existiert bereits.', 'LL_mailer'), $filter_label));
              wp_redirect(self::admin_url() . self::admin_page_filters);
              exit;
            }
            self::db_add_filter(array(
                'label' => $filter_label,
                'categories' => $filter_all_categories ? self::all_posts : self::implode_filter_categories($filter_categories),
                'preselected' => !!$_POST['filter_preselected']
              ));

            self::message(sprintf(__('Neuer Filter <b>%s</b> hinzugefügt.', 'LL_mailer'), $filter_label));
          }
        }
        else if (wp_verify_nonce($_POST['_wpnonce'], self::_ . '_filter_edit')) {
          $new_filter_label = trim($_POST['new_filter_label']);
          $new_filter_all_categories = !!$_POST['new_filter_all_categories'];
          $new_filter_categories = $_POST['new_filter_categories'];
          if (!empty($new_filter_label) && ($new_filter_all_categories || !empty($new_filter_categories))) {
            $old_filter = self::db_get_filter_by_id($filter_id);
            if ($new_filter_label !== $old_filter['label'] && self::db_filter_exists(array('label' => $new_filter_label))) {
              self::message(sprintf(__('Ein Filter <b>%s</b> existiert bereits.', 'LL_mailer'), $new_filter_label));
              wp_redirect(self::admin_url() . self::admin_page_filters);
              exit;
            }

            self::db_update_filter(array(
                'label' => $new_filter_label,
                'categories' => $new_filter_all_categories ? self::all_posts : self::implode_filter_categories($new_filter_categories),
                'preselected' => !!$_POST['new_filter_preselected']
              ), $filter_id);

            if ($old_filter['label'] !== $new_filter_label) {
              self::message(sprintf(__('Filter <b>%s</b> in <b>%s</b> umbenannt.', 'LL_mailer'), $old_filter['label'], $new_filter_label));
            }
            else {
              self::message(sprintf(__('Filter <b>%s</b> geändert.', 'LL_mailer'), $old_filter['label']));
            }
          }
        }
        else if (wp_verify_nonce($_POST['_wpnonce'], self::_ . '_filter_delete')) {
          $filter = self::db_get_filter_by_id($filter_id);
          self::db_delete_filter($filter_id);

          self::message(sprintf(__('Filter <b>%s</b> gelöscht.', 'LL_mailer'), $filter['label']));
        }
      }
    }
    wp_redirect(self::admin_url() . self::admin_page_filters);
    exit;
  }



  static function shortcode_SUBSCRIPTION_FORM($atts)
  {
    if (isset($_GET[self::user_msg])) {
      $msg = base64_decode($_GET[self::user_msg]);
      if (!isset($_GET[self::retry])) {
        return '<b>' . $msg . '</b>';
      }
    }

    ob_start();
    $attributes = self::get_option_array(self::option_subscriber_attributes);
?>
    <form action="<?=self::json_url()?>subscribe" method="post" <?=$atts['html_attr'] ?: ''?>>
<?php
    if (isset($msg)) {
?>
      <p class="<?=self::_?>_message"><?=$msg?></p>
<?php
    }
?>
      <table>
<?php
    foreach ($attributes as $attr => $attr_label) {
      if ($attr !== self::subscriber_attribute_meta) {
        $is_email = $attr === self::subscriber_attribute_mail;
        $input_type = $is_email ? 'email' : 'text';
        $input_required = $is_email ? _('(Pflichtfeld)') : '';
?>
        <tr class="<?=self::_?>_row_attribute">
          <td><?=$attr_label?></td>
          <td><input type="<?=$input_type?>" name="<?=self::_ . '_attr_' . $attr?>" /></td>
          <td><?=$input_required?></td>
        </tr>
<?php
      }
    }

    if (BETA()) {
?>
        <tr class="<?=self::_?>_row_filters">
          <td>Filter</td>
          <td>
            <input type="hidden" name='_wpnonce' value="<?=wp_create_nonce('wp_rest')?>" />
<?php
          $filters = self::db_get_filters('*');
          $show_categories = get_option(self::option_show_filter_categories);
          $categories = array();
          if ($show_categories) {
            $tmp_categories = get_categories();
            array_walk($tmp_categories, function(&$cat, $key) use (&$categories) {
              $categories[$cat->term_id] = $cat->name;
            });
          }
          foreach ($filters as &$filter) {
            if ($filter['categories'] === self::all_posts) {
              $cats_str = '';
            }
            else {
              $cats = self::explode_filter_categories($filter['categories']);
              $cats_str = implode(', ', array_map(function($cat) use ($categories) { return $categories[$cat]; }, $cats));
            }
?>
            <input type="checkbox" name="<?=self::_?>_filters[<?=$filter['id']?>]" id="<?=self::_ . '_filters[' . $filter['id']?>]" <?=$filter['preselected'] ? 'checked' : ''?> />
            <label for="<?=self::_ . '_filters[' . $filter['id']?>]" <?=($show_categories === 'tooltip' && $cats_str) ? 'title="' . $cats_str . '"' : ''?>>
              <?=$filter['label'] . (($show_categories === 'brackets' && $cats_str) ? ' (' . $cats_str . ')' : '')?>
            </label>
<?php
          }
?>
          </td>
        </tr>
<?php
    }

    $use_robot_check = get_option(self::option_use_robot_check);
    if ($use_robot_check) {
      $robot_question_idx = rand(0, count(self::robot_questions) - 1);
?>
        <tr class="<?=self::_?>_row_robot_question">
          <td><?=self::robot_questions[$robot_question_idx][0]?></td>
          <td>
            <input type="text" name="<?=self::_?>_robot_check" />
            <input type="hidden" name="<?=self::_?>_robot_check_2" value="<?=$robot_question_idx?>" />
          </td>
        </tr>
<?php
    }

    $recaptcha_website_key = get_option(self::option_recaptcha_website_key);
    $recaptcha_secret_key = get_option(self::option_recaptcha_secret_key);
    if ($recaptcha_website_key !== false && $recaptcha_secret_key !== false) {
?>
      <tr class="<?=self::_?>_row_google_recaptcha">
        <td></td><td>
          <script src="https://www.google.com/recaptcha/api.js" async defer></script>
          <div class="g-recaptcha" data-sitekey="<?=$recaptcha_website_key?>"></div>
        </td>
      </tr>
<?php
    }
?>
        <tr><td></td><td><input type="submit" id="<?=self::_?>_subscribe_form_submit" value="<?=__('Jetzt anmelden', 'LL_mailer')?>" <?=$atts['button_attr'] ?: ''?> /></td></tr>
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

  static function post_row_actions($actions, $post)
  {
    if ($post->post_status === 'publish') {
      $actions[self::_ . '_send_abo_mail'] = '<a href="' . self::generate_new_post_abo_mail($post->ID) . '">Abo E-Mails senden</a>';
    }
    return $actions;
  }








  static function init_hooks_and_filters()
  {
    add_action('admin_menu', self::_('admin_menu'));
    add_action('admin_post_' . self::_ . '_template_action', self::_('admin_page_template_action'));
    add_action('admin_post_' . self::_ . '_message_action', self::_('admin_page_message_action'));
    add_action('admin_post_' . self::_ . '_subscriber_action', self::_('admin_page_subscriber_action'));
    add_action('admin_post_' . self::_ . '_subscriber_attributes_action', self::_('admin_page_subscriber_attributes_action'));
    add_action('admin_post_' . self::_ . '_filters_action', self::_('admin_page_filters_action'));

    add_filter('post_row_actions', self::_('post_row_actions'), 10, 2);


    add_shortcode(self::shortcode_SUBSCRIPTION_FORM['code'], self::_('shortcode_SUBSCRIPTION_FORM'));
    add_shortcode(self::shortcode_SUBSCRIBER_ATTRIBUTE['code'], self::_('shortcode_SUBSCRIBER_ATTRIBUTE'));


    add_action('admin_notices', self::_('admin_notices'));

    register_activation_hook(__FILE__, self::_('activate'));

    self::check_for_db_updates();

    add_action('transition_post_status', self::_('post_status_transition'), 10, 3);

    add_action('rest_api_init', function()
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