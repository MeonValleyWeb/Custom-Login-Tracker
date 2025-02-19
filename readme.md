# Custom Login Tracker

Track and monitor WordPress user login activity with detailed statistics and enhanced security features.

![WordPress Plugin](https://img.shields.io/badge/WordPress-Plugin-0073aa)
![Version](https://img.shields.io/badge/Version-1.1.0-brightgreen)
![License](https://img.shields.io/badge/License-GPL%20v2-blue)
![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-0073aa)
![PHP](https://img.shields.io/badge/PHP-7.0%2B-777bb3)

## Description

Custom Login Tracker provides comprehensive monitoring of user login activity in your WordPress site. Designed to work seamlessly with Bedrock architecture, this plugin helps administrators keep track of who is accessing the site, when, and from where.

### Key Features

* **Successful Login Tracking** - Record user information, location, browser, and device details
* **Failed Login Monitoring** - Track failed login attempts with IP tracking
* **Session Duration** - Calculate how long users stay logged in
* **Geolocation** - Track the geographical origin of logins
* **Security Alerts** - Receive email notifications for suspicious login activity  
* **Customizable Retention** - Set how long login data is stored
* **Data Export** - Export login history to CSV for reporting and compliance
* **Dashboard Widget** - Get a quick overview of recent login activity
* **Detailed Statistics** - Analyze login patterns and user behavior

### Perfect For

* Security-conscious websites
* Membership sites
* Multi-author blogs
* Sites requiring compliance documentation
* Bedrock-based WordPress installations

## Installation

### Standard WordPress Installation

1. Upload the `custom-login-tracker` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure settings under Users > Login Tracker Settings

### Bedrock Installation

1. Upload the `custom-login-tracker` folder to the `/app/plugins/` directory
2. Add the plugin to your `composer.json` file or activate through WordPress admin
3. Configure settings under Users > Login Tracker Settings

## Configuration

After activation, visit **Users > Login Tracker Settings** to configure:

* Data retention period (days to keep login records)
* Location tracking (enable/disable)
* Browser and device tracking (enable/disable) 
* Failed login alert threshold
* Alert email address
* Dashboard widget visibility

## Screenshots

![Login History Dashboard](https://yoursite.com/wp-content/uploads/screenshots/login-history.png)
![Failed Login Attempts](https://yoursite.com/wp-content/uploads/screenshots/failed-logins.png)
![Statistics Overview](https://yoursite.com/wp-content/uploads/screenshots/statistics.png)
![Settings Page](https://yoursite.com/wp-content/uploads/screenshots/settings.png)

## Frequently Asked Questions

### Does this plugin work with Bedrock?
Yes, Custom Login Tracker is specifically designed to work with Bedrock architecture.

### Will this plugin slow down my site?
No, the plugin has minimal impact on site performance. Login tracking happens asynchronously and doesn't affect the user experience.

### How long is login data stored?
By default, login data is stored for 90 days, but this can be configured in the settings.

### Can I export the login data?
Yes, you can export both successful logins and failed login attempts to CSV format.

### Is location data accurate?
The plugin uses IP-based geolocation which provides city/country level accuracy. It's not 100% precise but gives a good general indication of login origins.

### Does this work with WooCommerce or membership plugins?
Yes, the plugin tracks all WordPress logins regardless of how they're initiated.

## Changelog

### 1.1.0
* Enhanced tracking of browser and device information
* Added data export functionality
* Improved dashboard widget
* Added statistics page

### 1.0.0
* Initial release

## Upgrade Notice

### 1.1.0
This update adds enhanced tracking capabilities and export functionality.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch: `git checkout -b my-new-feature`
3. Commit your changes: `git commit -am 'Add some feature'`
4. Push to the branch: `git push origin my-new-feature`
5. Submit a pull request

## License

This plugin is licensed under the GPL v2 or later.

A copy of the license is included in the root of the plugin's directory. The file is named `LICENSE`.

## Credits

* Developed by [Meon Valley Web](https://meonvalleyweb.com)
* Icon made by [Andrew Wilkinson] from [www.meonvalleyweb.com](https://www.meonvalleyweb.com.com)

## Support

For support, please visit [https://meonvalleyweb.com/support](https://meonvalleyweb.com/support) or email support@ymeonvalleyweb.com.