<?php
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto p-6">
    <h1 class="text-3xl font-bold mb-6 text-brand-gray">Contact Us</h1>

    <div class="bg-white shadow-md rounded-lg p-8">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Contact Information -->
            <div>
                <h2 class="text-xl font-semibold mb-4 text-brand-gray">Get in Touch</h2>
                <div class="space-y-4">
                    <div class="flex items-center">
                        <i class="fa-solid fa-envelope text-brand-primary w-6"></i>
                        <a href="mailto:support@studentaccommodation.com" class="text-brand-gray hover:text-brand-primary ml-2">
                            cs301project2025@gmail.com
                        </a>
                    </div>
                    <div class="flex items-center">
                        <i class="fa-solid fa-phone text-brand-primary w-6"></i>
                        <a href="tel:+260764416021" class="text-brand-gray hover:text-brand-primary ml-2">
                            +260 764 416 021
                        </a>
                    </div>
                    <div class="flex items-center">
                        <i class="fa-solid fa-location-dot text-brand-primary w-6"></i>
                        <span class="text-brand-gray ml-2">
                            Kitwe, Zambia
                        </span>
                    </div>
                </div>

                <div class="mt-8">
                    <h3 class="text-lg font-semibold mb-3 text-brand-gray">Working Hours</h3>
                    <div class="space-y-2 text-brand-gray/70">
                        <p>Monday - Friday: 8:00 AM - 6:00 PM</p>
                        <p>Saturday: 9:00 AM - 2:00 PM</p>
                        <p>Sunday: Closed</p>
                    </div>
                </div>
            </div>

            
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
