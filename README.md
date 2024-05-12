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

## Usage

Once the installation and configuration are complete, you can use the following URLs:

- **Eduroam Request URL:**  
  Visit `https://yoursite.edu.np/edutoam/request.php` to access the Eduroam request page.

- **Eduroam Management URL:**
  Visit `https://yoursite.edu.np/eduroam/management.php` to access the Eduroam Management page.

## Contributing

Contributions are welcome! If you find any issues or have suggestions for improvements, please open an issue or submit a pull request.

## License

This project is licensed under the [MIT License](LICENSE).
