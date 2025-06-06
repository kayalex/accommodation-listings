<?php
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto p-6">
    <h1 class="text-3xl font-bold mb-6 text-brand-gray">About Us</h1>

    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <!-- Hero Section -->
        <div class="relative h-64 bg-brand-primary">
            <div class="absolute inset-0 bg-black/50 flex items-center justify-center">
                <p class="text-white text-xl md:text-2xl font-semibold text-center px-4">
                    Connecting Students with Quality Accommodation Near Universities
                </p>
            </div>
        </div>

        <div class="p-8">
            <!-- Mission Statement -->
            <section class="mb-12">
                <h2 class="text-2xl font-semibold mb-4 text-brand-gray">Our Mission</h2>
                <p class="text-brand-gray/70 leading-relaxed">
                    We are dedicated to simplifying the process of finding suitable accommodation for students across Zambia. 
                    Our platform connects students with verified landlords, ensuring safe, convenient, and affordable housing 
                    options near their universities.
                </p>
            </section>

            <!-- What We Offer -->
            <section class="mb-12">
                <h2 class="text-2xl font-semibold mb-6 text-brand-gray">What We Offer</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="text-center p-4">
                        <div class="text-3xl text-brand-primary mb-4">
                            <i class="fa-solid fa-shield-check"></i>
                        </div>
                        <h3 class="text-lg font-semibold mb-2 text-brand-gray">Verified Landlords</h3>
                        <p class="text-brand-gray/70">
                            All landlords undergo a thorough verification process to ensure security and trust.
                        </p>
                    </div>

                    <div class="text-center p-4">
                        <div class="text-3xl text-brand-primary mb-4">
                            <i class="fa-solid fa-map-location-dot"></i>
                        </div>
                        <h3 class="text-lg font-semibold mb-2 text-brand-gray">Strategic Locations</h3>
                        <p class="text-brand-gray/70">
                            Properties carefully selected based on proximity to major universities.
                        </p>
                    </div>

                    <div class="text-center p-4">
                        <div class="text-3xl text-brand-primary mb-4">
                            <i class="fa-solid fa-hand-holding-dollar"></i>
                        </div>
                        <h3 class="text-lg font-semibold mb-2 text-brand-gray">Affordable Options</h3>
                        <p class="text-brand-gray/70">
                            Wide range of accommodations to suit different budgets and preferences.
                        </p>
                    </div>
                </div>
            </section>

            <!-- Universities We Serve -->
            <section class="mb-12">
                <h2 class="text-2xl font-semibold mb-6 text-brand-gray">Universities We Serve</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex items-center space-x-3 p-3 rounded bg-brand-light/10">
                        <i class="fa-solid fa-graduation-cap text-brand-primary"></i>
                        <span class="text-brand-gray">Copperbelt University (CBU)</span>
                    </div>
                    <div class="flex items-center space-x-3 p-3 rounded bg-brand-light/10">
                        <i class="fa-solid fa-graduation-cap text-brand-primary"></i>
                        <span class="text-brand-gray">University of Zambia (UNZA)</span>
                    </div>
                    <div class="flex items-center space-x-3 p-3 rounded bg-brand-light/10">
                        <i class="fa-solid fa-graduation-cap text-brand-primary"></i>
                        <span class="text-brand-gray">Mulungushi University</span>
                    </div>
                    <div class="flex items-center space-x-3 p-3 rounded bg-brand-light/10">
                        <i class="fa-solid fa-graduation-cap text-brand-primary"></i>
                        <span class="text-brand-gray">University of Lusaka (UNILUS)</span>
                    </div>
                </div>
            </section>

            <!-- Why Choose Us -->
            <section>
                <h2 class="text-2xl font-semibold mb-6 text-brand-gray">Why Choose Us?</h2>
                <div class="space-y-4">
                    <div class="flex items-start space-x-4">
                        <div class="text-brand-primary mt-1">
                            <i class="fa-solid fa-check-circle"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-brand-gray">Easy and Secure</h3>
                            <p class="text-brand-gray/70">Simple process to find and contact verified landlords.</p>
                        </div>
                    </div>

                    <div class="flex items-start space-x-4">
                        <div class="text-brand-primary mt-1">
                            <i class="fa-solid fa-check-circle"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-brand-gray">Diverse Options</h3>
                            <p class="text-brand-gray/70">From shared rooms to entire apartments, find what suits you best.</p>
                        </div>
                    </div>

                    <div class="flex items-start space-x-4">
                        <div class="text-brand-primary mt-1">
                            <i class="fa-solid fa-check-circle"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-brand-gray">Local Support</h3>
                            <p class="text-brand-gray/70">Dedicated team to assist you throughout your accommodation search.</p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
