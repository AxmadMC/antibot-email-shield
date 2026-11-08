# Antibot Email Shield

**Version 1.0.0** · WordPress 6.0+ · PHP 7.4+ · GPLv2 or later

A free WordPress plugin that shows your email address to people, not bots. Visitors click a button, pass a quick Cloudflare Turnstile check, and see the email for a limited time. The address is never written into the page, so spam bots that scan your site can't collect it.

**Documentation:** [docs.ahmedmc.com/email-shield](https://docs.ahmedmc.com/email-shield/)

## Why I built it

My email address was on my website, and spam bots kept finding it. Common tricks like writing "name [at] domain" or encoding the address don't stop modern bots. I wanted the address to stay out of the page completely and appear only for real people.

## How it works

1. You save your email addresses in the plugin settings. Each one gets a short id.
2. The `[email_shield id="support"]` shortcode shows a **Show email address** button.
3. When a visitor clicks it, Cloudflare Turnstile checks they're human (usually invisible).
4. Your server confirms the check with Cloudflare, then sends back the email.
5. The email hides again after 25 seconds (you can change this).

## Features

- The email is never in the page's HTML, not even encoded
- Turnstile verified on the server, with action and hostname checks
- Rate limit per visitor
- Works in the block editor, classic editor, widgets and Elementor
- Several emails, each with its own id
- Accessible: a real button that works with a keyboard and screen readers
- No tracking and no ads

## Install

1. Download the zip from [Releases](../../releases).
2. In WordPress, go to **Plugins → Add New → Upload Plugin**, choose the zip and click **Install Now**, then **Activate**.
3. Follow the [documentation](https://docs.ahmedmc.com/email-shield/) to add your Turnstile keys and emails.

## Security

Please report security problems privately by email, not in a public issue. The bug report address is on the [documentation page](https://docs.ahmedmc.com/email-shield/#support).

## License

GPLv2 or later. Developed by [Ahmed MC](https://ahmedmc.com).
