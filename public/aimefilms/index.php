<?php
$pageTitle = 'AIMEFILMS Originals — AIMEFILMS';
$activePlatform = 'aimefilms';
$base = '../';
require __DIR__ . '/../partials/header.php';
?>
<main>
<section class="subhero original-hero"><div class="subhero-copy"><span class="eyebrow"><span class="eyebrow-dot"></span>AIMEFILMS ORIGINALS</span><h1>Original ideas.<br><span>Stories without limits.</span></h1><p>A home for original productions, exclusive releases and bold creative voices from Rwanda and beyond.</p><div class="hero-actions"><a class="btn btn-primary" href="#catalog">Browse now <span>→</span></a><?php if (!$loggedInUser): ?><a class="btn btn-glass" href="../auth/register.php">Create free account</a><?php endif; ?></div></div><div class="subhero-symbol">✦</div></section>
<?php $platform = 'aimefilms'; require __DIR__ . '/../partials/catalog-section.php'; ?>
<?php if (!$loggedInUser): ?><section class="mini-cta"><div><span class="eyebrow">BE PART OF THE COMMUNITY</span><h2>Your account opens the door.</h2><p>Save favourites, follow creators and get closer to the stories you love.</p></div><a class="btn btn-primary" href="../auth/register.php">Create free account →</a></section><?php else: ?><section class="mini-cta"><div><span class="eyebrow">FOR CREATORS</span><h2>Share your work with Rwanda.</h2><p>Apply as an artist, upload your content and reach new audiences.</p></div><a class="btn btn-primary" href="../artist/index.php">Open artist studio →</a></section><?php endif; ?>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>