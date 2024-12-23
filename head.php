<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Delivery</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Saira:wght@100..900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flag-icon-css/6.6.6/css/flag-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <?php if (!empty($cart_logo) && file_exists($cart_logo)): ?>
        <link rel="icon" type="image/png" href="<?= htmlspecialchars($cart_logo, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <style>
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .promo-banner .carousel-item img {
            height: 400px;
            object-fit: cover;
        }

        .offers-section .card-img-top {
            height: 200px;
            object-fit: cover;
        }

        .offers-section .card-title {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .offers-section .card-text {
            font-size: 0.95rem;
            color: #555;
        }

        @media (max-width: 768px) {
            .promo-banner .carousel-item img {
                height: 250px;
            }

            .offers-section .card-img-top {
                height: 150px;
            }
        }

        .btn.disabled,
        .btn:disabled {
            opacity: 0.65;
            cursor: not-allowed;
        }

        .language-switcher {
            position: absolute;
            top: 10px;
            right: 10px;
        }

        .order-summary {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            position: sticky;
            top: 20px;
        }

        .order-title {
            margin-bottom: 15px;
        }
    </style>
    <script src="https://js.stripe.com/v3/"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js" integrity="sha512-BwHfrr4c9kmRkLw6iXFdzcdWV/PGkVgiIyIWLLlTSXzWQzxuSg4DiQUCpauz/EWjgk5TYQqX/kvn9pG1NpYfqg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css" integrity="sha512-Zcn6bjR/8RZbLEpLIeOwNtzREBAJnUKESxces60Mpoj+2okopSAcSUIUOseddDm0cxnGQzxIR7vJgsLZbdLE3w==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>