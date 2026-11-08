=== Antibot Email Shield ===
Contributors: ahmedmc
Tags: email, spam, anti-spam, email protection, turnstile
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Shows your email to people, not bots. Visitors click a button, pass a Cloudflare Turnstile check, and see the email for a limited time.

== Description ==

Spam bots collect email addresses by scanning the code of web pages. Antibot Email Shield never puts the address in the page at all:

1. The email is stored only in your WordPress settings.
2. The `[email_shield]` shortcode shows a "Show email address" button.
3. When a visitor clicks it, a Cloudflare Turnstile check runs (usually invisible).
4. Your server verifies the check with Cloudflare, then returns the email.
5. The email hides again after a set time (25 seconds by default).

Features:

* Email never written into the page's HTML, not even encoded
* Server-side Cloudflare Turnstile verification (with action and hostname checks)
* Per-visitor rate limiting
* Auto-hide after a configurable number of seconds
* Works in the block editor, classic editor, widgets, and Elementor's HTML widget
* Multiple emails, each with its own id
* Accessible: a real button that works with keyboards and screen readers
* No tracking, no ads, no data sent anywhere except the Turnstile check described below

Note: no method is 100% bot-proof — a bot that runs a full browser and solves challenges can still reveal an address. Antibot Email Shield stops ordinary harvesters completely and makes targeted harvesting slow and costly.

= About the author =

Antibot Email Shield is a free plugin, developed by Ahmed MC. Website: [ahmedmc.com](https://ahmedmc.com)

== Installation ==

1. Upload the `antibot-email-shield` folder to `/wp-content/plugins/`, or upload the zip under Plugins → Add New → Upload Plugin.
2. Activate the plugin.
3. Go to Settings → Antibot Email Shield and add your email (for example `legal@yourdomain.com`).
4. Create a free Turnstile widget in your Cloudflare dashboard (Turnstile → Add widget, add your domain) and paste the site key and secret key.
5. Add the shortcode where the email should appear: `[email_shield id="legal"]`

== Frequently Asked Questions ==

= What is the id? =

The part of the email before the @. For `legal@yourdomain.com` the id is `legal`. You can also choose your own id by writing `myid: you@yourdomain.com`.

= Can I change the button text? =

Yes, for all buttons in the settings, or for one button with `[email_shield id="legal" label="Email me"]`.

= Another plugin already uses [email_shield]. =

Then use `[antibot_email_shield id="legal"]`, which always works. This plugin never replaces another plugin's shortcode.

= Does it work without Turnstile? =

Yes, but with weaker protection: the email stays out of the page, but anyone who knows how the plugin works can request it directly. A warning is shown on the settings page until Turnstile keys are added.

= Is my site behind Cloudflare required? =

No. Turnstile works on any website. You do not need to move your DNS to Cloudflare.

== External services ==

This plugin connects to Cloudflare Turnstile, a bot-detection service by Cloudflare, Inc., to check that the visitor revealing an email is a human. It is used only after you add Turnstile keys in the settings, and only on pages that contain the plugin's shortcode.

* When a page with the shortcode loads (on sites built with Elementor, when the visitor clicks the button), the visitor's browser loads the Turnstile script from `https://challenges.cloudflare.com`. When the visitor clicks the button, Turnstile collects technical signals from the browser (such as IP address, user agent, and browser characteristics) to tell humans from bots.
* When the visitor passes the check, your server sends the resulting token, your Turnstile secret key, and the visitor's IP address to `https://challenges.cloudflare.com/turnstile/v0/siteverify` to confirm it.

Cloudflare terms of service: https://www.cloudflare.com/website-terms/
Cloudflare privacy policy: https://www.cloudflare.com/privacypolicy/
Turnstile privacy addendum: https://www.cloudflare.com/turnstile-privacy-policy/

If you use this plugin, mention Cloudflare Turnstile in your site's privacy policy.

== Changelog ==

= 1.0.0 =
* First public release.
