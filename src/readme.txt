=== MSO AI Meta Description ===
Contributors: msonly
Tags: meta description, seo, AI, Gemini Mistral OpenAI ChatGPT Anthropic
Requires at least: 6.7
Tested up to: 6.8
Stable tag: 1.0.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://www.ms-only.fr/donation

Add easily customizable meta descriptions to your WordPress site, with optional AI-powered suggestions.

== Description ==

**Take control of your SEO snippets with MSO AI Meta Description.**

This plugin allows you to easily add custom meta description tags to the HTML header of your posts, pages, custom post types, and even your homepage. Optimize how your content appears in search engine results!

**Key Features:**

* **Manual Editing:** Add or modify meta descriptions directly from the post editor via a dedicated meta box.
* **AI-Powered Suggestions (Optional):** Get help writing compelling descriptions! If you provide API keys, you can instantly generate suggestions using:
    * Google Gemini
    * Mistral AI
    * OpenAI (ChatGPT)
    * Anthropic
    * Cohere
* **Simple Configuration:** Configure API keys and select preferred AI models via the plugin's settings page (under Settings > General).
* **Homepage Description:** Set a custom meta description for your homepage (whether it shows latest posts or a static page).
* **Character Counter:** Easily track description length to stay within recommended limits (120-160 characters).
* **Lightweight and Focused:** Does one job well without unnecessary bloat.

Improve your site's visibility and click-through rates by crafting perfect meta descriptions for every piece of content.

== Installation ==

**Minimum Requirements:**

* WordPress 6.7 or greater
* PHP version 8.1 or greater

**Automatic Installation (Easiest):**

1.  Log in to your WordPress admin dashboard.
2.  Navigate to Plugins > Add New.
3.  Search for "MSO AI Meta Description".
4.  Find the plugin by MS-ONLY and click "Install Now".
5.  Click "Activate" once installation is complete.

**Manual Installation (Upload):**

1.  Download the plugin zip file (mso-ai-meta-description.zip) from the WordPress Plugin Directory (or source).
2.  Log in to your WordPress admin dashboard.
3.  Navigate to Plugins > Add New.
4.  Click the "Upload Plugin" button at the top.
5.  Choose the downloaded zip file and click "Install Now".
6.  Click "Activate" once installation is complete.

**Manual Installation (FTP):**

1.  Download the plugin zip file and unzip it.
2.  Using an FTP client, upload the `mso-ai-meta-description` folder to the `/wp-content/plugins/` directory on your server.
3.  Log in to your WordPress admin dashboard.
4.  Navigate to Plugins > Installed Plugins.
5.  Find "MSO AI Meta Description" and click "Activate".

**Configuration (Required for AI Features):**

1.  After activation, go to MSO AI Meta Description in your WordPress admin menu.
2.  Enter your API keys for the AI services (Gemini, Mistral, OpenAI, Anthropic ) you wish to use. API keys are **only** required if you want to use the AI generation feature.
3.  Select the desired AI models from the dropdown lists (models are loaded dynamically if the API key is valid).
4.  Click "Save Changes".

== Screenshots ==

1. Settings: activation of the different APIs

2. Settings screen: adding and modifying the model API key and customizing the prompt

== Frequently Asked Questions ==

= Where are the plugin settings? =

You can find the settings to enter API keys and select AI models under **MSO AI Meta Description** in your WordPress admin dashboard.

= Do I need API keys to use this plugin? =

No. You only need to provide API keys in the settings if you want to use the optional **AI-powered description generation** feature (Gemini, Mistral, ChatGPT, Anthropic). You can still manually write and save meta descriptions without any API keys.

= Which AI models can I use? =

The plugin is designed to work with models from Gemini, Mistral, OpenAI and Anthropic based on your API key. Popular default options include `gemini-2.0-flash`, `mistral-small-latest`, `gpt-3.5-turbo` and `claude-3-sonnet-20240229`. You can select your preferred available model in the plugin settings.

= Will this conflict with my existing SEO plugin (Yoast, Rank Math, etc.)? =

This plugin adds its meta description tag directly to the `wp_head` action hook with an early priority. If your existing SEO plugin *also* outputs a meta description tag, you might end up with duplicates in your HTML source code, which is bad for SEO.

**Recommendation:** It's best practice to only have **one** plugin managing your meta descriptions. If you use MSO AI Meta Description, consider disabling the meta description feature in your other SEO plugin(s), or vice-versa.

= How do I set the description for the homepage? =

* If your homepage displays a **Static Page** (Settings > Reading), the meta description is edited in the meta box when you edit that specific page.
* If your homepage displays your **Latest Posts** (Settings > Reading), you can set a specific meta description for it under **Settings > Reading**, in the "Front page meta description" field added by this plugin.

== Changelog ==

= 1.0.0 =
* Date: 2025-04-17
* Initial release.
* Features: Manual meta description editing, AI generation via Gemini Mistral OpenAI Anthropic Cohere.

== Upgrade Notice ==

= 1.0.0 =
This version adds support for OpenAI (ChatGPT) Anthropic Gemini Cohere and Mistral for generating meta descriptions! Please review the updated settings page under Settings > General to add your OpenAI API key if desired.