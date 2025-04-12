# MSO Meta Description

**Easily add customizable meta descriptions to your WordPress site, with optional suggestions based on AI from Gemini, Mistral, and OpenAI (ChatGPT).**

---

## 🧠 Introduction

**MSO Meta Description** is a lightweight WordPress plugin designed to give you full control over your site's meta description tags for better SEO. Write them manually or get suggestions using the latest LLMs like Gemini, Mistral, and OpenAI (ChatGPT).

---

## 📑 Table of Contents

- [Features](#-features)
- [Installation](#-installation)
- [Usage](#-usage)
- [Configuration](#-configuring-ai-features)
- [FAQ](#-frequently-asked-questions)
- [Changelog](#-changelog)
- [Troubleshooting](#-troubleshooting)
- [Contributors](#-contributors)
- [License](#-license)

---

## ✨ Features

- ✅ **Manual Meta Descriptions** for posts, pages, custom post types, and the homepage.
- 🤖 **AI Suggestions (Optional)** using:
  - Google Gemini
  - Mistral AI
  - OpenAI (ChatGPT)
- 🧩 **Character Counter** to stay within the optimal range of 120 to 160 characters.
- ⚙️ **Homepage Description Support** whether you use a static page or the latest posts.
- 🪶 **Lightweight and Focused**: Does one thing well, without bloat.
- 🛠️ **Simple Configuration** with dynamic loading of models after API key entry.

---

## 🛠️ Installation

### Minimum Requirements

- WordPress 6.0+
- PHP 8.1+

### Automatic Installation

1. Go to your WordPress admin dashboard.
2. Navigate to **Plugins > Add New**.
3. Search for **"MSO Meta Description"**.
4. Click **Install Now** and then **Activate**.

### Manual Installation (Download)

1. Download the plugin zip file (`mso-meta-description.zip`).
2. Go to **Plugins > Add New > Upload Plugin**.
3. Select the zip file and install it.
4. Click **Activate**.

### Manual Installation (FTP)

1. Unzip the plugin.
2. Upload the `mso-meta-description` folder to `/wp-content/plugins/`.
3. Go to **Plugins > Installed Plugins** and **Activate** it.

---

## 🚀 Usage

After activating the plugin:

- Go to any post, page, or custom post type.
- Scroll down to the **MSO Meta Description** box.
- Write your custom description or use the AI buttons (if configured).
- Save or publish the item.

---

## ⚙️ Configuring AI Features

1. Navigate to **Settings > General**.
2. Scroll down to the **MSO Meta Description** section.
3. Enter your API keys for OpenAI, Mistral, Gemini.
4. Choose your preferred model from the dropdown lists (e.g., `gpt-3.5-turbo`, `mistral-small-latest`, `gemini-2.0-flash`).
5. Click **Save Changes**.

---

## ❓ Frequently Asked Questions

### Where are the plugin settings located?

The settings are located under **Settings > General** in your WordPress admin dashboard.

### Do I need API keys?

Only if you want to use the **AI-powered description generation**. Manual editing works without API keys.

### Which models are supported?

The plugin dynamically fetches available models once a valid API key is entered. Popular default models include:
- `gpt-3.5-turbo`
- `mistral-small-latest`
- `gemini-2.0-flash`

### Will this conflict with SEO plugins like Yoast or Rank Math?

Potentially. Both plugins can generate a meta description. **Avoid duplicates** by disabling meta descriptions in one of the plugins.

### How do I set the homepage description?

- **Static Page**: Edit the page and use the meta box.
- **Latest Posts**: Go to **Settings > Reading**, find the “Homepage Meta Description” field.

---

## 🧾 Changelog

### 1.2.0 – *2025-04-12*

- ✨ Added support for **OpenAI (ChatGPT)**
- ⚙️ Improved error handling for all providers
- 🎨 Enhanced UI for settings and editor
- 🛠️ Fixed AI button visibility logic

### 1.1.0

- 🧱 Major codebase refactor (SoC-based structure)
- 🎛️ Improved settings page user experience
- 📡 Standardized API responses

### 1.0.0

- 🚀 Initial release with Gemini and Mistral support

---

## 🛠️ Troubleshooting

- **Duplicate Meta Tags?** Check if another SEO plugin is also adding them. Disable in one.
- **AI Not Working?** Verify your API key is correct and the model list loads properly.
- **Homepage Description Missing?** Ensure your homepage type (static vs. latest posts) matches the plugin's input location.

---

## 👥 Contributors

- **MS-ONLY** – [https://www.ms-only.fr](https://www.ms-only.fr)

---

## 📄 License

**MSO Meta Description** is licensed under the [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html).