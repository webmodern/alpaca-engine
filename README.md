<p align="center"><a href="https://web-modern.by" target="_blank"><img src="https://web-modern.by/wp-content/uploads/2025/07/alpaca-engine-github.png" alt="Alpaca Engine Logo"></a></p>

<p align="center">
<a href="https://packagist.org/packages/webmodern/alpaca-engine"><img src="https://img.shields.io/packagist/dt/webmodern/alpaca-engine" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/webmodern/alpaca-engine"><img src="https://img.shields.io/packagist/v/webmodern/alpaca-engine" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/webmodern/alpaca-engine"><img src="https://img.shields.io/packagist/l/webmodern/alpaca-engine" alt="License"></a>
</p>

# Alpaca Engine

## 🚀 Laravel 12 Starter Kit

A Starter Kit for rapid project development based on Laravel 12. Includes a modern admin panel powered by filamentphp, along with a set of popular libraries to speed up your workflow. Perfect for building admin panels and web applications with a flexible architecture.
 
## Deployment and Environment Configuration Script

This project includes a utility script for safely switching and managing `.env` files on the server.

### Usage

- **Apply an environment profile** (e.g., `server`, `local`, `staging`):

```bash
./deploy/env_switch.sh [profile]
```

  Replace *[profile]* with the desired environment name (e.g., *server*, *local*).  
  The script expects a file named *env.[profile]* in the project root (for example: *env.server*).

- **Restore the last .env backup**:

```bash
./deploy/env_switch.sh restore
```

### What the Script Does

- Backs up the current *.env* file to */deploy* before replacing it.
- Switches *.env* to the specified profile (*env.[profile]*).
- Keeps a profile-specific log file in */deploy* (e.g., *env_server.log*).
- Cleans up old logs automatically (older than 3 months).
- Restores the last backup if run with *restore*.
- Clears Laravel config cache, runs migrations, and updates Composer dependencies.

### Additional Notes

- The */deploy* directory is *git-ignored* except for the script itself.
- All other files in */deploy* (backups, logs, etc.) are never committed to git.
- Make sure the script is executable:

```bash
chmod +x deploy/env_switch.sh
```

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
