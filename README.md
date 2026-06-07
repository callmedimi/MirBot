# 🤖 Mirza Bot (Mirza Panel)

<p align="center">
    <a href="https://t.me/mirzapanel" target="_blank">
        <img src="https://img.shields.io/badge/Telegram-Channel-blue?style=for-the-badge&logo=telegram" alt="Telegram Channel"/>
    </a>
    <a href="https://t.me/mirzapanelgroup" target="_blank">
        <img src="https://img.shields.io/badge/Telegram-Group-orange?style=for-the-badge&logo=telegram" alt="Telegram Group"/>
    </a>
    <a href="https://github.com/mahdiMGF2/mirzabot" target="_blank">
        <img src="https://img.shields.io/github/stars/mahdiMGF2/mirzabot?style=for-the-badge&logo=github" alt="GitHub Stars"/>
    </a>
</p>

---

## ✨ Overview

**Mirza Bot** is a high-performance, feature-rich Telegram bot designed to automate the sale and management of VPN services. It integrates seamlessly with popular panels such as **Marzban**, **3x-ui**, **Alireza**, **Pasarguard**, **IBSng**, and others. The system automatically builds configurations, manages subscriptions, processes payments, and handles user verification.

Mirza Panel is available in two distinct editions:
*   **Free Version** 🆓: Core features for automating subscription generation, trials, support, and manual card-to-card/cryptocurrency payments.
*   **Pro (Subscription) Version** 💎: Advanced business metrics, enhanced configuration engines, deeper analytics, and custom integrations.

---

## ⚙️ Features

### 🔹 Free Version
*   **Automated Provisioning**: Instantly generates client configurations upon successful purchase or trial requests.
*   **Multi-Panel Integration**: Compatible with Marzban, 3x-ui, Alireza panels, and more.
*   **User Lifecycle Management**: Simple configuration renewals, additional volume purchases, and link updates.
*   **Payment Gateways**: Supports manual Card-to-Card, *NowPayments* (Crypto), and *Aqayepardakht*.
*   **Mandatory Subscriptions**: Restricts usage until the user joins specified Telegram channels.
*   **Verification Engine**: Optional phone number verification to mitigate spam.
*   **Management Dashboard**: Integrated command-line utility (`mirza`) for updates, service management, and backups.


---

## 🖥️ System Requirements

Before proceeding with the installation, verify that your server environment meets the following specifications:

| Requirement | Specification | Notes |
| :--- | :--- | :--- |
| **Operating System** | Ubuntu 22.04 LTS / 24.04 LTS | Fresh installation recommended |
| **Disk Space** | $\ge$ 2 GB free space | Required for dependencies & databases |
| **RAM** | $\ge$ 1 GB (2 GB+ recommended) | MySQL service stability requires sufficient memory |
| **Connectivity** | Public IPv4 + active domain name | Let's Encrypt SSL validation requires a resolved A record |
| **Ports** | `80`, `443`, `8008`, and `88` | Must be open and unrestricted by firewalls |

---

## 🚀 Step-by-Step Installation Tutorial

Follow these steps to deploy Mirza Bot on your Ubuntu server.

### Step 1: Configure DNS Records
Before executing the script, point your domain's **A record** to your server's public IP address. Let's Encrypt will fail to issue certificates if the domain does not resolve correctly.

### Step 2: Run the Installer
Connect to your server via SSH as the `root` user and execute the bootstrap command:

```bash
curl -o install.sh -L https://raw.githubusercontent.com/mahdiMGF2/mirzabot/main/install.sh && bash install.sh
```

> [!NOTE]
> The script automatically installs itself locally to `/root/install.sh` and creates a system link at `/usr/local/bin/mirza`. Once installed, you can access the management panel at any time by running:
> ```bash
> mirza
> ```

### Step 3: Interactive Configuration Flow
When running the interactive wizard (Option `1` from the menu), you will be guided through the following prompts:

1.  **Select Target Version**:
    *   `1` — Automatic (Latest stable release) **[Recommended]**
    *   `2` — Choose a specific tag version
    *   `3` — Beta (Main branch)
2.  **Domain Name**: Enter your domain (e.g., `bot.example.com`). The script validates that the domain points to your server's IP.
3.  **SSL Contact Email**: Provide an email address for Let's Encrypt expiration notifications.
4.  **Telegram Bot Token**: Enter your token from `@BotFather`. The script performs an API request to verify the token is live.
5.  **Admin Chat ID**: Enter the numeric Telegram ID of the administrator (get this from `@userinfobot`).
6.  **Bot Username**: Enter the bot's username without the `@` prefix.
7.  **Database Credentials**: Press `Enter` to auto-generate secure credentials, or specify your own user and password.

### Step 4: Verify Webhook and Launch
Upon completion, the script will:
*   Write Apache configuration files.
*   Request and deploy Let's Encrypt certificates.
*   Set the Telegram webhook securely using a custom secret token.
*   Initialize database tables by querying the internal schema builder.
*   Send a Telegram message to your Admin Chat ID confirming a successful deployment.

Open your Telegram client, navigate to your bot, and send `/start` to begin setup.

---

## ⚡ Non-Interactive & Automated Installation

For advanced deployments and CI/CD pipelines, you can run the script non-interactively by supplying arguments directly.

### Command Syntax
```bash
mirza install [options]
```

### Supported Parameters

| Flag | Argument | Description |
| :--- | :--- | :--- |
| `--name` | `username` | Telegram bot username (exclude `@`) |
| `--token` | `token` | Telegram bot token (`12345678:ABC...`) |
| `--admin` | `id` | Numeric Telegram chat ID for admin notifications |
| `--domain` | `domain` | Resolvable domain name |
| `--email` | `email` | Registration email for Let's Encrypt certificates |
| `--db-user` | `username`| Custom database user name (alphanumeric only) |
| `--db-pass` | `password`| Custom database password (6+ characters) |
| `--version` | `tag` | Target GitHub release tag (e.g., `0.1.7`) |
| `--channel` | `channel` | Channel selection: `stable` \| `beta` \| `release` \| `auto` |

### Automation Example
```bash
mirza install \
  --channel auto \
  --name myvpnbot \
  --token 123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ \
  --admin 987654321 \
  --domain bot.mydomain.com \
  --email admin@mydomain.com
```

---

## 🛠️ System Architecture & Port Mapping

Mirza Bot implements a specialized network architecture to optimize security and ensure compatibility with Telegram's Webhook requirements:

```mermaid
graph TD
    Telegram[Telegram API] -->|HTTPS Requests via Port 88| Apache
    Client[Web Browsers] -->|HTTP Redirect via Port 8008| Apache
    Apache -->|Document Root| BotFiles[/var/www/html/mirzaprobotconfig]
    BotFiles -->|Local Socket| PHP[PHP 8.2 FPM]
    PHP -->|Database Connection| MySQL[(MySQL Server)]
```

*   **Port 88 (HTTPS)**: Set as the primary secure endpoint. Telegram webhooks are mapped directly to `https://yourdomain:88/index.php`. Decoupling the webhook to port 88 prevents conflicts with standard web traffic and enhances request-handling isolation.
*   **Port 8008 (HTTP)**: Used for initial web requests and redirects. It automatically routes incoming traffic to Port 88 via a permanent rewrite rule.
*   **Decoupled SSL (Certbot Webroot)**: The Let's Encrypt challenge uses Certbot's `--webroot` authentication method targeted at `/var/www/html/mirzaprobotconfig/.well-known/acme-challenge`. This allows certificate renewals without disabling or interfering with Apache ports.

---

## 🔄 Updates, Maintenance & Backups

You can run the management utility at any time using the `mirza` command.

### 1. Updating Mirza
To update the bot to the latest release or switch branches, select Option `2` in the interactive menu, or run:
```bash
mirza update --channel release
```
*The updater backs up your `config.php`, downloads the new files, restores the configuration, verifies Apache syntax, and runs database migrations automatically.*

### 2. Migration: Free ➡️ Pro
If you are transitioning from the Free to the Pro version, select Option `4` from the dashboard or execute:
```bash
mirza migrate
```
*   **Requirement**: You must create a database backup (`mirzabot_backup.sql`) prior to migrating.
*   The script renames the database from `mirzabot` to `mirzaprobot`, provisions new secure users, adjusts local routing configurations, and reapplies webhooks.

### 3. Database Management
Select Option `7` (`Database Backup & Restore`) to:
*   **Create Backups**: Backs up the schema and table values to `/root/mirza_db_backup_[TIMESTAMP].sql`.
*   **Restore Backups**: Interactively scans `/root` for available SQL backup scripts and imports them into MySQL.

### 4. Diagnostics & Troubleshooting
If the bot stops responding to Telegram updates, run `mirza` and choose Option `5` (`Bot Diagnostics & Status`). The system checks:
*   Apache and MySQL service states.
*   Local database connection credentials.
*   Telegram API connectivity and live Webhook configuration (showing pending counts and recent delivery errors).
*   SSL certificate validity and days remaining.

---

## ❌ Uninstallation

To remove all configuration files, databases, and package installations from the server:

1.  Select Option `3` (`Remove Mirza`) in the interactive menu, or run:
    ```bash
    mirza remove
    ```
2.  Confirm the prompt with `y`. The script will securely shred `config.php`, purge MySQL, Apache2, phpMyAdmin, and PHP 8.2 packages, and reset the UFW firewall rules.

---

## 👥 Contributors

<p align="center">
  <a href="https://github.com/mahdiMGF2/mirzabot/graphs/contributors">
    <img src="https://contrib.rocks/image?repo=mahdiMGF2/mirzabot" alt="Contributors List"/>
  </a>
</p>
