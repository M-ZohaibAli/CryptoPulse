<?php
session_start();
session_regenerate_id(true);

try {

    $pdo = new PDO('mysql:host=sql312.infinityfree.com;dbname=if0_38439171_auth_db;charset=utf8', 'if0_38439171', 'hc1LW4rgspi6H9');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

function generateCsrfToken() {
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    return $token;
}

function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function isUserLoggedIn() {
    return isset($_SESSION['user_id']);
}

function userLogin($username, $password) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        return true;
    }
    return false;
}

function userRegister($username, $password) {
    global $pdo;

    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        return false; 
    }
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
    return $stmt->execute([$username, $hashedPassword]);
}

function userLogout() {
    session_unset();
    session_destroy();
}

function fetchAPI($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json']
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode === 200 ? json_decode($response, true) : null;
}

function fetchCryptoPrices() {
    $apiUrl = 'https://api.coingecko.com/api/v3/simple/price?ids=bitcoin,ethereum,dogecoin,binancecoin,ripple,solana,cardano,polkadot,chainlink,litecoin&vs_currencies=usd&include_24hr_change=true';
    return fetchAPI($apiUrl);
}

function fetchCryptoNews() {
    $newsUrl = 'https://api.coingecko.com/api/v3/news';
    $data = fetchAPI($newsUrl);
    return $data['news'] ?? [];
}

function getHistoricalPrices($coinId, $days = 30) {
    $url = "https://api.coingecko.com/api/v3/coins/$coinId/market_chart?vs_currency=usd&days=$days";
    return fetchAPI($url);
}

function searchCrypto($query) {
    $url = "https://api.coingecko.com/api/v3/search?query=$query";
    return fetchAPI($url);
}

function logError($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message . PHP_EOL, 3, 'error.log');
}

function formatPercentage($number) {
    return number_format($number, 2) . '%';
}

function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

function displayErrors() {
    if (!empty($_SESSION['errors'])) {
        echo '<div class="error-box">';
        foreach ($_SESSION['errors'] as $error) {
            echo '<p>' . sanitizeInput($error) . '</p>';
        }
        echo '</div>';
        unset($_SESSION['errors']);
    }
}

function displaySuccess() {
    if (!empty($_SESSION['success'])) {
        echo '<div class="success-box">' . sanitizeInput($_SESSION['success']) . '</div>';
        unset($_SESSION['success']);
    }
}

$_SESSION['favorites'] = $_SESSION['favorites'] ?? [];
$_SESSION['portfolio'] = $_SESSION['portfolio'] ?? [];
$_SESSION['alert_prefs'] = $_SESSION['alert_prefs'] ?? [];
$csrfToken = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['errors'][] = 'Invalid CSRF token';
    } else {

        if (isset($_POST['register'])) {
            $username = sanitizeInput($_POST['username']);
            $password = sanitizeInput($_POST['password']);
            if (userRegister($username, $password)) {
                $_SESSION['success'] = 'Registration successful! You can now log in.';
            } else {
                $_SESSION['errors'][] = 'Registration failed or username already exists.';
            }
        }

        if (isset($_POST['login'])) {
            $username = sanitizeInput($_POST['username']);
            $password = sanitizeInput($_POST['password']);
            if (userLogin($username, $password)) {
                $_SESSION['success'] = 'Logged in successfully!';
            } else {
                $_SESSION['errors'][] = 'Invalid credentials';
            }
        }

        if (isset($_POST['add_portfolio'])) {
            $coin = sanitizeInput($_POST['coin']);
            $amount = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT);

            if ($amount && $amount > 0) {
                $_SESSION['portfolio'][$coin] = [
                    'amount' => $amount,
                    'purchase_price' => $_SESSION['prices'][$coin]['usd'] ?? null,
                    'purchase_date' => date('Y-m-d H:i:s')
                ];
            }
        }

        if (isset($_POST['save_prefs'])) {
            $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
            $threshold = filter_var($_POST['threshold'], FILTER_VALIDATE_FLOAT);

            if ($email && $threshold) {
                $_SESSION['alert_prefs'] = [
                    'email' => $email,
                    'threshold' => $threshold,
                    'coin' => sanitizeInput($_POST['alert_coin'])
                ];
                $_SESSION['success'] = 'Alert preferences saved!';
            } else {
                $_SESSION['errors'][] = 'Invalid notification preferences';
            }
        }
    }
}

if (isset($_GET['logout'])) {
    userLogout();
    header('Location: ?page=home');
    exit;
}

$prices = fetchCryptoPrices() ?? [];
$news = fetchCryptoNews();
$page = isset($_GET['page']) ? sanitizeInput($_GET['page']) : 'home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crypto Tracker</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #007bff;
            --secondary: #6c757d;
            --success: #28a745;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #343a40;
        }

        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0; 

            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            background: linear-gradient(45deg, #f3f4f6, #e0e7ff);
            margin: 0; 
            padding: 0;

        }

        .nav { 
            background: var(--primary);
            padding: 1rem;
            margin-bottom: 2rem;
            border-radius: 12px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .nav a { 
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav a:hover { 
            background: rgba(255,255,255,0.15);
            transform: translateY(-2px);
        }

        .nav form {
            margin-left: auto;
            display: flex;
            gap: 0.5rem;
            background: rgba(255,255,255,0.15);
            padding: 0.5rem;
            border-radius: 8px;
        }

        .nav input[type="text"] {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            background: rgba(255,255,255,0.9);
            min-width: 200px;
        }

        .crypto-container { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); 
            gap: 1.5rem; 
        }

        .crypto-box { 
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .crypto-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }

        .crypto-box::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--primary);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .crypto-box:hover::after {
            transform: scaleX(1);
        }

        .chart-container {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            margin: 2rem 0;
            position: relative;
        }

        .loader {
            border: 4px solid rgba(0,0,0,0.1);
            border-top: 4px solid var(--primary);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1000;
            display: none;
        }

        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.9);
            z-index: 999;
            display: none;
        }

        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }

        .login-form {
            max-width: 400px;
            margin: 2rem auto;
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        input, select, button {
            padding: 0.75rem 1rem;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        input:focus, select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0,123,255,0.25);
            outline: none;
        }

        button {
            background: var(--primary);
            color: white;
            border: none;
            cursor: pointer;
            padding: 0.75rem 1.5rem;
        }

        button:hover {
            background: #0069d9;
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .nav {
                gap: 1rem;
                padding: 1rem;
            }

            .nav form {
                width: 100%;
                order: 1;
                margin-top: 1rem;
            }
        }

        .error-box {
            background: #ffe3e6;
            color: #dc3545;
            padding: 1rem;
            margin: 1.5rem 0;
            border-radius: 8px;
            border: 2px solid #dc3545;
        }

        .success-box {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            margin: 1.5rem 0;
            border-radius: 8px;
            border: 2px solid #155724;
        }
    </style>
</head>
<body>
    <div class="overlay"></div>
    <div class="loader"></div>

    <div class="nav">
        <a href="?page=home">🏠 Home</a>
        <a href="?page=portfolio">📁 Portfolio</a>
        <a href="?page=news">📰 News</a>
        <a href="?page=history">📈 Charts</a>
        <?php if (isUserLoggedIn()): ?>
            <a href="?page=profile">👤 Profile</a>
            <a href="?logout=1">🔒 Logout</a>
        <?php else: ?>
            <a href="?page=login">🔑 Login</a>
            <a href="?page=register">📝 Register</a>
        <?php endif; ?>
        <form method="get">
            <input type="hidden" name="page" value="search">
            <input type="text" name="query" placeholder="Search Crypto...">
            <button type="submit">🔍</button>
        </form>
    </div>

    <?php displayErrors(); ?>
    <?php displaySuccess(); ?>

    <script>
        function showLoader() {
            document.querySelector('.loader').style.display = 'block';
            document.querySelector('.overlay').style.display = 'block';
        }

        function hideLoader() {
            document.querySelector('.loader').style.display = 'none';
            document.querySelector('.overlay').style.display = 'none';
        }

        document.addEventListener('DOMContentLoaded', () => {

            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', () => showLoader());
            });

            const originalUpdateChart = window.updateChart;
            window.updateChart = async function(coinId) {
                showLoader();
                try {
                    await originalUpdateChart(coinId);
                } finally {
                    hideLoader();
                }
            }

            document.querySelectorAll('a').forEach(link => {
                if (link.href.includes('?')) {
                    link.addEventListener('click', (e) => {
                        e.preventDefault();
                        showLoader();
                        setTimeout(() => window.location.href = link.href, 300);
                    });
                }
            });
        });

        setTimeout(() => {
            document.querySelectorAll('.error-box, .success-box').forEach(el => {
                el.style.display = 'none';
            });
        }, 5000);
    </script>

    <?php switch($page):
        case 'login': ?>
            <div class="login-form">
                <h2>Login</h2>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="text" name="username" placeholder="Username" required>
                    <input type="password" name="password" placeholder="Password" required>
                    <button type="submit" name="login">Login</button>
                </form>
            </div>
        <?php break; ?>

        <?php case 'register': ?>
            <div class="login-form">
                <h2>Register</h2>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="text" name="username" placeholder="Username" required>
                    <input type="password" name="password" placeholder="Password" required>
                    <button type="submit" name="register">Register</button>
                </form>
            </div>
        <?php break; ?>

        <?php case 'profile': ?>
            <?php if (isUserLoggedIn()): ?>
                <div class="profile-settings">
                    <h2>Notification Preferences</h2>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="email" name="email" placeholder="Email" required 
                            value="<?= $_SESSION['alert_prefs']['email'] ?? '' ?>">
                        <select name="alert_coin">
                            <?php foreach ($prices as $coin => $data): ?>
                                <option value="<?= $coin ?>" <?= ($_SESSION['alert_prefs']['coin'] ?? '') === $coin ? 'selected' : '' ?>>
                                    <?= ucfirst($coin) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" step="0.0001" name="threshold" placeholder="Price threshold" required
                            value="<?= $_SESSION['alert_prefs']['threshold'] ?? '' ?>">
                        <button type="submit" name="save_prefs">Save Preferences</button>
                    </form>
                </div>
            <?php endif; 
            break; ?>

        <?php case 'history': ?>
            <div class="chart-container">
                <h2>Historical Prices</h2>
                <select id="coinSelector" onchange="updateChart(this.value)">
                    <?php foreach ($prices as $coin => $data): ?>
                        <option value="<?= $coin ?>"><?= ucfirst($coin) ?></option>
                    <?php endforeach; ?>
                </select>
                <canvas id="priceChart"></canvas>
            </div>
            <script>
                let chart;
                async function updateChart(coinId) {
                    const response = await fetch(`?page=chart_data&coin=${coinId}`);
                    const data = await response.json();

                    if (chart) chart.destroy();

                    chart = new Chart(document.getElementById('priceChart'), {
                        type: 'line',
                        data: {
                            labels: data.dates,
                            datasets: [{
                                label: `${coinId} Price History`,
                                data: data.prices,
                                borderColor: '#007bff',
                                tension: 0.1
                            }]
                        }
                    });
                }
                updateChart(document.getElementById('coinSelector').value);
            </script>
            <?php break; ?>

        <?php case 'chart_data': 
            header('Content-Type: application/json');
            $coinId = sanitizeInput($_GET['coin'] ?? '');
            $history = getHistoricalPrices($coinId);

            if ($history) {
                $pricesArr = array_column($history['prices'], 1);
                $dates = array_map(function($point) {
                    return date('Y-m-d', $point[0]/1000);
                }, $history['prices']);

                echo json_encode(['prices' => $pricesArr, 'dates' => $dates]);
            } else {
                echo json_encode(['error' => 'Invalid coin ID']);
            }
            exit;
            break; ?>

        <?php case 'search': ?>
            <div class="search-results">
                <h2>Search Results</h2>
                <?php 
                $query = sanitizeInput($_GET['query'] ?? '');
                $results = searchCrypto($query)['coins'] ?? [];
                ?>
                <div class="crypto-container">
                    <?php foreach ($results as $result): ?>
                        <div class="crypto-box">
                            <h3><?= sanitizeInput($result['name']) ?></h3>
                            <p>Symbol: <?= sanitizeInput($result['symbol']) ?></p>
                            <p>Rank: #<?= sanitizeInput($result['market_cap_rank'] ?? 'N/A') ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php break; ?>

        <?php case 'portfolio': ?>
            <h2>Your Portfolio</h2>
            <div class="crypto-container">
                <?php $totalValue = 0; ?>
                <?php foreach ($_SESSION['portfolio'] as $coin => $data): ?>
                    <?php if (isset($prices[$coin]) && $data['amount'] > 0): ?>
                        <div class="crypto-box">
                            <h3><?= ucfirst($coin) ?></h3>
                            <p>Amount: <?= number_format($data['amount'], 6) ?></p>
                            <p>Value: <?= formatCurrency($data['amount'] * $prices[$coin]['usd']) ?></p>
                        </div>
                        <?php $totalValue += $data['amount'] * $prices[$coin]['usd']; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <h3>Total Value: <?= formatCurrency($totalValue) ?></h3>
            <?php break; ?>

        <?php case 'news': ?>
            <h2>Latest Crypto News</h2>
            <div class="crypto-container">
                <?php foreach ($news as $article): ?>
                    <div class="crypto-box">
                        <h3><?= sanitizeInput($article['title']) ?></h3>
                        <p><?= sanitizeInput(substr($article['description'], 0, 100)) ?>...</p>
                        <a href="<?= sanitizeInput($article['url']) ?>" target="_blank">Read more</a>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php break; ?>

        <?php default: ?>
           <h2 style="font-size: 32px; font-weight: bold; text-align: center; color: #ff9800; text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3); font-family: Arial, sans-serif; margin-top: 20px;">CryptoPulse</h2>

            <div class="crypto-container">
                <?php foreach ($prices as $coin => $data): ?>
                    <div class="crypto-box">
                        <h3><?= ucfirst($coin) ?></h3>
                        <p>Price: <?= formatCurrency($data['usd']) ?></p>
                        <p class="<?= $data['usd_24h_change'] >= 0 ? 'change-up' : 'change-down' ?>">
                            24h Change: <?= formatPercentage($data['usd_24h_change']) ?>
                        </p>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="coin" value="<?= $coin ?>">
                            <input type="number" step="0.0001" name="amount" placeholder="Amount" required>
                            <button type="submit" name="add_portfolio">Add to Portfolio</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
    <?php endswitch; ?>
</body>
</html>
