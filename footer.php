<footer class="bg-light text-muted pt-5 pb-4">
    <div class="container text-md-left">
        <div class="row text-md-left">
            <div class="col-md-3 mx-auto mt-3">
                <h5 class="text-uppercase mb-4 font-weight-bold text-dark"><img src="https://images.unsplash.com/photo-1550547660-d9450f859349?crop=entropy&cs=tinysrgb&fit=crop&fm=jpg&h=40&w=40" alt="Restaurant Logo" width="40" height="40" class="me-2">Restaurant</h5>
                <p>Experience the finest dining with us. We offer a variety of dishes crafted from the freshest ingredients to delight your palate.</p>
            </div>
            <div class="col-md-2 mx-auto mt-3">
                <h5 class="text-uppercase mb-4 font-weight-bold text-dark">Quick Links</h5>
                <ul class="list-unstyled">
                    <li class="mb-2"><a href="index.php" class="text-reset text-decoration-none">Home</a></li>
                    <li class="mb-2"><a href="#menu" class="text-reset text-decoration-none">Menu</a></li>
                    <li class="mb-2"><a href="#about" class="text-reset text-decoration-none">About Us</a></li>
                    <li class="mb-2"><a href="#contact" class="text-reset text-decoration-none">Contact</a></li>
                </ul>
            </div>
            <div class="col-md-3 mx-auto mt-3">
                <h5 class="text-uppercase mb-4 font-weight-bold text-dark">Legal</h5>
                <ul class="list-unstyled">
                    <li class="mb-2"><button type="button" class="btn btn-link text-reset text-decoration-none p-0" data-bs-toggle="modal" data-bs-target="#agbModal"><i class="bi bi-file-earmark-text-fill me-2"></i> AGB</button></li>
                    <li class="mb-2"><button type="button" class="btn btn-link text-reset text-decoration-none p-0" data-bs-toggle="modal" data-bs-target="#impressumModal"><i class="bi bi-file-earmark-text-fill me-2"></i> Impressum</button></li>
                    <li class="mb-2"><button type="button" class="btn btn-link text-reset text-decoration-none p-0" data-bs-toggle="modal" data-bs-target="#datenschutzModal"><i class="bi bi-file-earmark-text-fill me-2"></i> Datenschutzerklärung</button></li>
                </ul>
            </div>
            <div class="col-md-4 mx-auto mt-3">
                <h5 class="text-uppercase mb-4 font-weight-bold text-dark">Contact Us</h5>
                <p><i class="bi bi-geo-alt-fill me-2"></i> 123 Main Street, City, Country</p>
                <p><i class="bi bi-envelope-fill me-2"></i> info@restaurant.com</p>
                <p><i class="bi bi-telephone-fill me-2"></i> +1 234 567 890</p>
                <p><i class="bi bi-clock-fill me-2"></i> Mon - Sun: 10:00 AM - 10:00 PM</p>
            </div>
        </div>
        <hr class="mb-4">
        <div class="row align-items-center">
            <div class="col-md-7">
                <p>© <?= date('Y') ?> <strong>Restaurant</strong>. All rights reserved.</p>
            </div>
            <div class="col-md-5">
                <div class="text-center text-md-end">
                    <div class="social-media">
                        <?= !empty($social_links['facebook_link']) ? "<a href='" . htmlspecialchars($social_links['facebook_link'], ENT_QUOTES, 'UTF-8') . "' target='_blank' rel='noopener noreferrer'><i class='bi bi-facebook'></i></a>" : '' ?>
                        <?= !empty($social_links['twitter_link']) ? "<a href='" . htmlspecialchars($social_links['twitter_link'], ENT_QUOTES, 'UTF-8') . "' target='_blank' rel='noopener noreferrer'><i class='bi bi-twitter'></i></a>" : '' ?>
                        <?= !empty($social_links['instagram_link']) ? "<a href='" . htmlspecialchars($social_links['instagram_link'], ENT_QUOTES, 'UTF-8') . "' target='_blank' rel='noopener noreferrer'><i class='bi bi-instagram'></i></a>" : '' ?>
                        <?= !empty($social_links['linkedin_link']) ? "<a href='" . htmlspecialchars($social_links['linkedin_link'], ENT_QUOTES, 'UTF-8') . "' target='_blank' rel='noopener noreferrer'><i class='bi bi-linkedin'></i></a>" : '' ?>
                        <?= !empty($social_links['youtube_link']) ? "<a href='" . htmlspecialchars($social_links['youtube_link'], ENT_QUOTES, 'UTF-8') . "' target='_blank' rel='noopener noreferrer'><i class='bi bi-youtube'></i></a>" : '' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>