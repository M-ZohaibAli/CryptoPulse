# CryptoPulse

CryptoPulse is a powerful and user-friendly cryptocurrency tracking platform built with PHP. It allows users to stay updated with real-time crypto prices, manage their portfolios, and analyze historical price trends using interactive charts. Whether you're a trader or an enthusiast, CryptoPulse keeps you connected to the crypto market anytime, anywhere.

## Features

- **Real-time Price Tracking** – Stay updated with live cryptocurrency prices.
- **Portfolio Management** – Track and manage your crypto holdings efficiently.
- **Interactive Charts** – Analyze historical price trends with intuitive charts.
- **Secure Authentication** – User-friendly login and registration for a personalized experience.
- **Crypto News** – Stay informed with the latest crypto market news.
- **Responsive Design** – Access your crypto data on any device.

## Installation

### Prerequisites
- PHP (>=7.4)
- MySQL Database
- Web Server (Apache or Nginx)
- Composer (for dependency management)

### Steps to Install
1. **Clone the Repository**
   ```sh
   git clone https://github.com/your-username/cryptopulse.git
   cd cryptopulse
   ```
2. **Install Dependencies**
   ```sh
   composer install
   ```
3. **Configure Environment Variables**
   - Rename `.env.example` to `.env`
   - Update database credentials and API keys

4. **Setup Database**
   ```sh
   php artisan migrate
   ```
5. **Start the Server**
   ```sh
   php -S localhost:8000
   ```
6. **Access CryptoPulse**
   Open your browser and visit `http://localhost:8000`

## Usage
- Register/Login to personalize your experience.
- Add cryptocurrencies to your portfolio.
- View real-time price updates and trends.
- Stay updated with the latest crypto news.

## Technologies Used
- PHP
- MySQL
- JavaScript (Chart.js for interactive charts)
- Bootstrap (for responsive UI)
- CoinGecko API (for real-time price data)

## License
CryptoPulse is open-source and available under the [MIT License](LICENSE).

## Contributing
We welcome contributions! Feel free to submit issues or pull requests to improve CryptoPulse.

## Contact
For any questions or suggestions, reach out at [your-email@example.com](mailto:your-email@example.com).
