    <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet"> -->
<?php
    $seoTitle = $seo_title ?? ($site_name ?? 'eduroam Visitor Access');
    $seoDescription = $seo_description ?? 'eduroam Visitor Access provides simple, secure temporary guest Wi-Fi access for visitors at higher education and research institutions.';
    $seoCanonical = $seo_canonical ?? ('https://eva.nren.net.np' . strtok($_SERVER['REQUEST_URI'] ?? '/eduroam/', '?'));
    $seoRobots = $seo_robots ?? 'index,follow';
    $seoImage = $seo_image ?? 'https://eva.nren.net.np/eduroam/assets/images/eduroam-logo.png';
    $seoType = $seo_type ?? 'website';
?>
    <link rel="icon" type="image/svg+xml" href="/eduroam/assets/images/favicon.svg">
    <link rel="shortcut icon" href="/eduroam/assets/images/favicon.svg">
    <link rel="canonical" href="<?php echo htmlspecialchars($seoCanonical); ?>">
    <meta name="description" content="<?php echo htmlspecialchars($seoDescription); ?>">
    <meta name="robots" content="<?php echo htmlspecialchars($seoRobots); ?>">
    <meta property="og:site_name" content="<?php echo htmlspecialchars($site_name ?? 'eduroam Visitor Access'); ?>">
    <meta property="og:type" content="<?php echo htmlspecialchars($seoType); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($seoTitle); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($seoDescription); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($seoCanonical); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($seoImage); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($seoTitle); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($seoDescription); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($seoImage); ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<?php if (!empty($seo_schema)) : ?>
    <script type="application/ld+json"><?php echo $seo_schema; ?></script>
<?php endif; ?>
