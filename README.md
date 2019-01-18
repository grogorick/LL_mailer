# LL mailer
*Wordpress new-post email notification plugin* (originally developed for [Linda liest](https://linda-liest.de))

###### On Page
- Shortcodes to customize pages
  - Subscription form
  - Subscriber attributes

###### WP-Admin Section
- Subscriber management
- Write (html and text version) templates to get a consistent look for all your mails
- Write (html and text version) mail "drafts"
- Live preview of messages with example subscriber/post data included
- Use custom shortcodes to include arbitrary data in your mails
  - WP_Post
  - Custom post meta
  - Subscriber attributes
  - Confirmation url (in subscription confirmation)
  - Unsubscribe url
  - Message content (only in templates)
  - In new post mail (if-like block whose contents are visible only in new-post mails)

###### Themes/Plugins
- Use filters to further customize shortcode results before used in a mail
  - WP_Post
  - Custom post meta
  - Subscriber attributes

###### Used third-party libs
- PHPMailer ([GitHub](https://github.com/PHPMailer/PHPMailer))
- cssin ([GitHub](https://github.com/djfm/cssin))
