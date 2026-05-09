<?php
require_once 'includes/config.php';
$seo_title = $site_name . ' | Secure Guest Wi-Fi Access';
$seo_description = 'eduroam Visitor Access provides simple and secure temporary guest Wi-Fi access for visitors at higher education and research institutions in Nepal.';
$seo_canonical = 'https://eva.nren.net.np/eduroam/';
$seo_type = 'website';
$seo_schema = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'WebSite',
    'name' => $site_name,
    'url' => 'https://eva.nren.net.np/eduroam/',
    'description' => $seo_description,
    'publisher' => [
        '@type' => 'Organization',
        'name' => 'Nepal Research and Education Network',
        'url' => 'https://nren.net.np/'
    ]
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($seo_title); ?></title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <?php include 'template_parts/head.php'; ?>
</head>
<body class="app-shell public-shell">
<?php include_once 'template_parts/nav.php'; ?>

<main id="content" class="page-shell">
    <section class="hero-section hero-section--home">
        <div class="hero-copy">
            <div class="hero-brand-lockup">
                <div class="hero-logo-pair">
                    <img src="/eduroam/assets/images/nren-logo.jpg" alt="NREN logo" class="hero-logo">
                    <img src="/eduroam/assets/images/eduroam-logo.png" alt="eduroam logo" class="hero-logo hero-logo--wide">
                </div>
            </div>
            <span class="eyebrow">Guest Wi-Fi Access</span>
            <h1><?php echo htmlspecialchars($site_name); ?></h1>
            <p>eduroam Visitor Access enables higher education and research institute visitors to access the secure and trusted eduroam Wi-Fi network. The service can provide temporary access to the eduroam network in a simple and secure manner.</p>
            <div class="hero-actions">
                <a class="btn btn-primary btn-lg" href="/eduroam/request.php">Request Guest Access</a>
            </div>
        </div>
        <div class="feature-panel">
            <div class="feature-card">
                <h2>How It Works</h2>
                <p>If your higher education or research institute uses eduroam Visitor Access you can sign in with your personal (educational) credentials and use this service.</p>
                <ul class="feature-list">
                    <li>Guests submit their name and delivery email.</li>
                    <li>Usernames are generated automatically in FreeRADIUS.</li>
                    <li>Credentials are emailed immediately after creation.</li>
                    <li>Accounts expire after <?php echo htmlspecialchars(guestAccountDurationLabel()); ?> and are purged automatically.</li>
                </ul>
            </div>
        </div>
    </section>
</main>

<?php include_once 'template_parts/footer.php'; ?>
</body>
</html>
