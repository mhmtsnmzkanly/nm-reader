<?php
/** @var int $errorCode */
/** @var string $errorMessage */
/** @var callable $__t */
/** @var callable $url */

$title = $errorCode === 404 ? "404 - Page Not Found" : "Error - Something Went Wrong";
?>

<div class="container py-5 text-center">
    <div class="card py-5 shadow-lg border-0">
        <div class="mb-4">
            <h1 class="display-1 font-bold text-primary"><?= (int) $errorCode ?></h1>
            <h2 class="h4 text-muted uppercase tracking-widest"><?= htmlspecialchars($errorMessage) ?></h2>
        </div>
        
        <div class="py-4">
            <p class="text-lg opacity-75 mb-5">
                <?php if ($errorCode === 404): ?>
                    The page you are looking for might have been removed,<br>had its name changed or is temporarily unavailable.
                <?php else: ?>
                    An unexpected error occurred. Our team has been notified.<br>Please try again later.
                <?php endif; ?>
            </p>
            
            <div class="d-flex justify-center gap-3">
                <a href="<?= $url('/') ?>" class="btn btn-primary px-5 py-2">
                    Back to Home
                </a>
                <button onclick="history.back()" class="btn btn-outline px-5 py-2">
                    Go Back
                </button>
            </div>
        </div>
    </div>
</div>

<?php /* Styles moved to site.css */ ?>
