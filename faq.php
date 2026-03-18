<?php
require_once 'includes/config.php';
$seo_title = $site_name . ' | FAQ';
$seo_description = 'Read frequently asked questions about eduroam Visitor Access, safe connection practices, and official eduroam resources.';
$seo_canonical = 'https://eva.nren.net.np/eduroam/faq.php';
$seo_type = 'website';
$seo_schema = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => [
        [
            '@type' => 'Question',
            'name' => 'What is eduroam?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => 'eduroam is a secure global Wi-Fi roaming service for the research and education community.'
            ]
        ],
        [
            '@type' => 'Question',
            'name' => 'Who can use eduroam?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => 'Eligible users from participating organisations can usually use eduroam when their home institution provides credentials and supports the service.'
            ]
        ],
        [
            '@type' => 'Question',
            'name' => 'Should I use a web login page for eduroam?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => 'No. Official eduroam guidance warns against browser-based login pages using eduroam branding.'
            ]
        ],
        [
            '@type' => 'Question',
            'name' => 'When should I use this visitor portal?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => 'Use this portal when the host institution asks you to request temporary visitor credentials here.'
            ]
        ]
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
            <span class="eyebrow">Help And Guidance</span>
            <h1>Frequently asked questions about eduroam Visitor Access</h1>
            <p>These answers are based on guidance from the official eduroam service. They explain what eduroam is, how it works, and how visitors should connect safely.</p>
            <div class="hero-actions">
                <a class="btn btn-primary btn-lg" href="/eduroam/request.php">Request Guest Access</a>
                <a class="btn btn-outline-light btn-lg" href="https://eduroam.org/faqs/" target="_blank" rel="noopener noreferrer">Official eduroam FAQ</a>
            </div>
        </div>
        <div class="feature-panel">
            <div class="feature-card">
                <h2>Before You Connect</h2>
                <ul class="feature-list">
                    <li>Use the official `eduroam` SSID.</li>
                    <li>Prefer properly configured device settings or the official geteduroam app where supported.</li>
                    <li>Do not enter eduroam credentials into a browser-based login portal.</li>
                    <li>Use this portal when your host institution has directed you to request temporary visitor credentials.</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="faq-shell">
        <div class="faq-grid">
            <article class="faq-card">
                <h2>What is eduroam?</h2>
                <p>eduroam is a secure global Wi-Fi roaming service for the research and education community. It lets eligible users connect at participating locations without needing a separate account for each site they visit.</p>
            </article>

            <article class="faq-card">
                <h2>Who can use eduroam?</h2>
                <p>Students, researchers, teachers, staff, and other eligible users from participating organisations can usually use eduroam when their home institution provides credentials and supports the service.</p>
            </article>

            <article class="faq-card">
                <h2>How does eduroam work?</h2>
                <p>Your device connects to the local eduroam network, but your credentials are validated by your home institution through the eduroam federation. The visited site provides network access after that authentication succeeds.</p>
            </article>

            <article class="faq-card">
                <h2>Is eduroam safe to use?</h2>
                <p>eduroam is designed around strong authentication and encryption standards and is generally more secure than typical public hotspot access. Safe setup still matters, so devices should be configured correctly.</p>
            </article>

            <article class="faq-card">
                <h2>Should I use a web login page for eduroam?</h2>
                <p>No. Official eduroam guidance warns against browser-based login pages using eduroam branding. A properly configured device should connect using 802.1X settings rather than a captive portal.</p>
            </article>

            <article class="faq-card">
                <h2>What is geteduroam?</h2>
                <p>geteduroam is the official app that helps supported users configure their devices with the correct settings. It is intended to reduce setup errors and make connections safer and easier.</p>
            </article>

            <article class="faq-card">
                <h2>What does eduroam cost?</h2>
                <p>eduroam is generally free for eligible end users. Access is offered by participating organisations for the benefit of the research and education community.</p>
            </article>

            <article class="faq-card">
                <h2>When should I use this visitor portal?</h2>
                <p>If the host institution asks you to request temporary visitor credentials here, use this portal to receive a time-limited account by email. If your own institution already provides working eduroam credentials, those should usually be used directly.</p>
            </article>
        </div>

        <section class="resource-section">
            <div class="section-heading">
                <span class="chart-eyebrow">Official Resources</span>
                <h2>Useful eduroam links</h2>
                <p>For deeper guidance, these official pages from eduroam.org are the best next step.</p>
            </div>
            <div class="resource-grid">
                <a class="resource-card" href="https://eduroam.org/what-is-eduroam/" target="_blank" rel="noopener noreferrer">
                    <h3>What is eduroam?</h3>
                    <p>Overview of the service, where it works, and why it is trusted worldwide.</p>
                </a>
                <a class="resource-card" href="https://eduroam.org/faqs/" target="_blank" rel="noopener noreferrer">
                    <h3>Official FAQ</h3>
                    <p>Answers to common questions for users, institutions, and network administrators.</p>
                </a>
                <a class="resource-card" href="https://eduroam.org/geteduroam-get-connected-quickly-and-safely/" target="_blank" rel="noopener noreferrer">
                    <h3>geteduroam App</h3>
                    <p>Official information about the app used to configure supported devices quickly and safely.</p>
                </a>
                <a class="resource-card" href="https://eduroam.org/warning-do-not-use-web-logins-for-eduroam/" target="_blank" rel="noopener noreferrer">
                    <h3>Security Advisory</h3>
                    <p>Why browser-based login pages should not be used for eduroam connections.</p>
                </a>
            </div>
        </section>
    </section>
</main>

<?php include_once 'template_parts/footer.php'; ?>
</body>
</html>
