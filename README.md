# Eduroam

This repository contains the codebase for an Eduroam authentication system. Eduroam allows students, researchers, and staff from participating institutions to access the internet securely when visiting other participating institutions.

## Installation

To install and configure the Eduroam system, follow these steps:

1. Clone this repository:
    ```
    git clone https://github.com/anamolsapkota/eduroam.git
    ```

2. Copy `db.example.php` to `db.php` and update the credentials to your Eduroam database:
    ```
    cp db.example.php db.php
    ```
    
3. Copy `config.example.php` to `config.php` and update the credentials to your Eduroam database:
    ```
    cp includes/config.example.php includes/config.php
    ```

4. Change the required configurations in the `includes/config.php` file:
    ```
    nano includes/config.php
    ```

5. Run the initialization script:
    Visit `https://yoursite.edu.np/eduroam/includes/init.php` in your browser.

6. Set up the cron job for monitoring data collection (required for the Monitoring/Graphs page):
    ```
    */10 * * * * /usr/bin/php /var/www/yoursite/eduroam/scripts/collect_radius_auth_stats.php >/dev/null 2>&1
    ```
    This collects FreeRADIUS authentication stats every 10 minutes and stores them as CSV samples for the monitoring charts.

## Usage

Once the installation and configuration are complete, you can use the following URLs:

- **Eduroam Request Page:**  
  `https://yoursite.edu.np/eduroam/request.php`

- **Admin Dashboard:**  
  `https://yoursite.edu.np/eduroam/admin/`

- **Forgot Password:**  
  `https://yoursite.edu.np/eduroam/forgotpass.php`

## Contributing

Contributions are welcome! If you find any issues or have suggestions for improvements, please open an issue or submit a pull request.

## License

This project is licensed under the [MIT License](LICENSE).
