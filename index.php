<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EC Wound Charting - AI-Powered Wound Care EMR</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <!-- Google Fonts (Inter) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        <?php
        session_start();
        if (isset($_SESSION['ec_user_id'])) {
            header('Location: dashboard.php');
            exit;
        }
        ?>
    </script>
    <style>
        /* Custom styles */
        body {
            font-family: 'Inter', sans-serif;
        }
        .hero-gradient {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 50%, #60a5fa 100%);
        }
        .cta-gradient {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
        }
        /* Hide scrollbar for demo */
        html {
            scroll-behavior: smooth;
        }
    </style>
</head>
<body class="bg-white text-gray-800 antialiased">

<!-- Header -->
<header class="absolute top-0 left-0 w-full z-50 py-5 px-4 sm:px-6 lg:px-8">
    <nav class="relative max-w-7xl mx-auto flex items-center justify-between">
        <a href="#" class="flex items-center space-x-2">
            <!-- Placeholder Logo - Replace with your logo.png -->
            <!-- <img src="logo.png" alt="EC Logo" class="h-10 w-auto"> -->
            <svg class="h-10 w-10 text-white" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM11 11V7H13V11H17V13H13V17H11V13H7V11H11Z" />
            </svg>
            <span class="font-bold text-2xl text-white">EC Wound Charting</span>
        </a>

        <!-- Desktop Nav -->
        <div class="hidden md:flex items-center space-x-6">
            <a href="#features" class="text-white/80 hover:text-white transition duration-300 font-medium">Features</a>
            <a href="#solution" class="text-white/80 hover:text-white transition duration-300 font-medium">Solution</a>
            <a href="#cta" class="text-white/80 hover:text-white transition duration-300 font-medium">Demo</a>
            <button onclick="openLoginModal()" class="text-white/80 hover:text-white transition duration-300 font-medium">Login</button>
        </div>

        <!-- CTA Button (Desktop) -->
        <div class="hidden md:block">
            <button onclick="openModal()" class="bg-white text-blue-700 font-semibold py-2 px-5 rounded-full shadow-lg hover:bg-gray-100 transition duration-300">
                Schedule Demo
            </button>
        </div>

        <!-- Mobile Menu Button -->
        <div class="md:hidden">
            <button id="mobile-menu-btn" class="text-white">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path></svg>
            </button>
        </div>
    </nav>
</header>

<!-- Mobile Menu (Hidden by default) -->
<div id="mobile-menu" class="fixed inset-0 bg-blue-900 z-50 p-6 flex-col items-center justify-center space-y-8 text-center text-white text-2xl font-semibold hidden">
    <button id="mobile-close-btn" class="absolute top-6 right-6 text-white">
        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
    </button>
    <a href="#features" class="mobile-nav-link block">Features</a>
    <a href="#solution" class="mobile-nav-link block">Solution</a>
    <a href="#cta" class="mobile-nav-link block">Demo</a>
    <button onclick="openLoginModal()" class="mobile-nav-link block w-full text-center">Login</button>
    <button onclick="openModal()" class="bg-white text-blue-700 font-semibold py-3 px-8 rounded-full shadow-lg hover:bg-gray-100 transition duration-300 text-xl">
        Schedule Demo
    </button>
</div>

<!-- Hero Section -->
<section class="hero-gradient relative text-white pt-32 pb-20 lg:pt-48 lg:pb-28 overflow-hidden">
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 z-10">
        <div class="text-center md:text-left">
            <h1 class="text-4xl md:text-5xl lg:text-6xl font-extrabold tracking-tight max-w-4xl mx-auto md:mx-0">
                The Future of Wound Care Documentation
            </h1>
            <p class="mt-6 text-lg lg:text-xl text-white/90 max-w-3xl mx-auto md:mx-0">
                EC Wound Charting combines generative AI, advanced visual tracking, and a full EMR to reduce clinician burnout and improve patient outcomes.
            </p>
            <div class="mt-10 flex flex-col sm:flex-row gap-4 justify-center md:justify-start">
                <button onclick="openModal()" class="bg-white text-blue-700 font-semibold py-3 px-8 rounded-full shadow-lg hover:bg-gray-100 transition duration-300 text-lg">
                    Schedule a Live Demo
                </button>
                <a href="#features" class="bg-blue-500/50 text-white font-semibold py-3 px-8 rounded-full shadow-lg hover:bg-blue-500/70 transition duration-300 text-lg text-center">
                    Learn More
                </a>
            </div>
        </div>
    </div>
    <!-- The header photo (img tag) has been completely removed to resolve overlap issues. -->
</section>

<!-- Problem/Solution Section -->
<section class="py-20 lg:py-24 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
            <div class="text-center lg:text-left">
                <h2 class="text-3xl lg:text-4xl font-extrabold text-blue-900">
                    Stop Charting. Start Healing.
                </h2>
                <p class="mt-6 text-lg text-gray-600">
                    Traditional EMRs are slow, subjective, and time-consuming. Clinicians spend hours on repetitive data entry instead of with their patients. It's difficult to track healing, measurements are inconsistent, and notes are hard to summarize.
                </p>
                <p class="mt-4 text-lg text-gray-600 font-semibold">
                    EC Wound Charting is a complete EMR built from the ground up to automate documentation, visualize progress, and give you back your time.
                </p>
            </div>
            <div class="flex justify-center">
                <img src="img/stress.png"
                     alt="Clinician transitioning from stressed to calm"
                     class="rounded-2xl shadow-2xl"
                     onerror="this.src='https://placehold.co/600x400/3b82f6/FFFFFF?text=Problem+vs+Solution'"/>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section id="features" class="py-20 lg:py-24 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <span class="text-blue-600 font-semibold">Our Features</span>
            <h2 class="text-3xl lg:text-4xl font-extrabold text-gray-900 mt-2">
                An All-in-One Platform, Powered by AI
            </h2>
            <p class="text-lg text-gray-600 max-w-3xl mx-auto mt-4">
                Your entire workflow, from patient intake to billing, streamlined and enhanced with intelligent tools.
            </p>
        </div>

        <!-- Feature 1: AI-Powered Efficiency -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center mb-20">
            <div class="flex justify-center">
                <img src="img/analyzing.png"
                     alt="AI analyzing wound on a tablet"
                     class="rounded-2xl shadow-2xl"
                     onerror="this.src='https://placehold.co/600x450/e0e7ff/3b82f6?text=AI+Feature'"/>
            </div>
            <div>
                    <span class="inline-flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 text-blue-600">
                        <i data-lucide="zap"></i>
                    </span>
                <h3 class="text-2xl lg:text-3xl font-bold text-gray-900 mt-6">
                    Chart in Seconds, Not Minutes
                </h3>
                <p class="text-lg text-gray-600 mt-4">
                    Let our generative AI do the heavy lifting. Our platform automates your most time-consuming documentation tasks, reducing burnout and ensuring accuracy.
                </p>
                <ul class="space-y-4 mt-6">
                    <li class="flex items-start space-x-3">
                        <i data-lucide="file-text" class="w-6 h-6 text-blue-600 flex-shrink-0 mt-1"></i>
                        <div>
                            <h4 class="font-semibold">AI-Generated Summaries & LMNs</h4>
                            <p class="text-gray-600">Instantly creates professional narrative summaries and Letters of Medical Necessity (LMN) with a single click.</p>
                        </div>
                    </li>
                    <li class="flex items-start space-x-3">
                        <i data-lucide="scan-line" class="w-6 h-6 text-blue-600 flex-shrink-0 mt-1"></i>
                        <div>
                            <h4 class="font-semibold">AI Photo Analysis</h4>
                            <p class="text-gray-600">Automatically calculates wound dimensions and identifies tissue types (granulation, slough) from a single photo.</p>
                        </div>
                    </li>
                    <li class="flex items-start space-x-3">
                        <i data-lucide="lightbulb" class="w-6 h-6 text-blue-600 flex-shrink-0 mt-1"></i>
                        <div>
                            <h4 class="font-semibold">AI Treatment Plans</h4>
                            <p class="text-gray-600">Generates evidence-based treatment plan suggestions based on the complete patient assessment.</p>
                        </div>
                    </li>
                    <li class="flex items-start space-x-3">
                        <i data-lucide="mic" class="w-6 h-6 text-blue-600 flex-shrink-0 mt-1"></i>
                        <div>
                            <h4 class="font-semibold">Smart Dictation & Autosave</h4>
                            <p class="text-gray-600">Dictate notes in real-time with AI correction. Never lose work with our intelligent autosave system.</p>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Feature 2: Advanced Visual Charting -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center mb-20">
            <div class="lg:order-2 flex justify-center">
                <img src="img/compare.png"
                     alt="Wound healing comparison slider"
                     class="rounded-2xl shadow-2xl"
                     onerror="this.src='https://placehold.co/600x450/e0e7ff/3b82f6?text=Visual+Feature'"/>
            </div>
            <div class="lg:order-1">
                    <span class="inline-flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 text-blue-600">
                        <i data-lucide="eye"></i>
                    </span>
                <h3 class="text-2xl lg:text-3xl font-bold text-gray-900 mt-6">
                    Visualize Healing Like Never Before
                </h3>
                <p class="text-lg text-gray-600 mt-4">
                    Go beyond static photos and simple text fields. Our platform gives you dynamic, interactive tools to track and show healing progress with unmatched clarity.
                </p>
                <ul class="space-y-4 mt-6">
                    <li class="flex items-start space-x-3">
                        <i data-lucide="camera" class="w-6 h-6 text-blue-600 flex-shrink-0 mt-1"></i>
                        <div>
                            <h4 class="font-semibold">Direct Camera Integration</h4>
                            <p class="text-gray-600">Capture wound photos directly from your mobile device or PC webcam without leaving the assessment workflow.</p>
                        </div>
                    </li>
                    <li class="flex items-start space-x-3">
                        <i data-lucide="git-compare" class="w-6 h-6 text-blue-600 flex-shrink-0 mt-1"></i>
                        <div>
                            <h4 class="font-semibold">Wound Comparison Slider</h4>
                            <p class="text-gray-600">The ultimate progress tool. Show patients and providers the healing trajectory with a stunning visual comparison slider.</p>
                        </div>
                    </li>
                    <li class="flex items-start space-x-3">
                        <i data-lucide="ruler" class="w-6 h-6 text-blue-600 flex-shrink-0 mt-1"></i>
                        <div>
                            <h4 class="font-semibold">On-Screen Photo Measurement</h4>
                            <p class="text-gray-600">Use our built-in tools to manually measure length, width, and area directly on any wound photo for total accuracy.</p>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Feature 3: Complete Clinical Solution -->
        <div id="solution" class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
            <div class="flex justify-center">
                <img src="img/dashboard.png"
                     alt="Clinical dashboard with map view"
                     class="rounded-2xl shadow-2xl"
                     onerror="this.src='https://placehold.co/600x450/e0e7ff/3b82f6?text=EMR+Feature'"/>
            </div>
            <div>
                    <span class="inline-flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 text-blue-600">
                        <i data-lucide="layout-grid"></i>
                    </span>
                <h3 class="text-2xl lg:text-3xl font-bold text-gray-900 mt-6">
                    A Full EMR, Built for Wound Care
                </h3>
                <p class="text-lg text-gray-600 mt-4">
                    Manage your entire workflow from a single, secure platform. EC Wound Charting is a complete EMR, not just an add-on.
                </p>
                <ul class="space-y-4 mt-6">
                    <li class="flex items-start space-x-3">
                        <i data-lucide="clipboard-check" class="w-6 h-6 text-blue-600 flex-shrink-0 mt-1"></i>
                        <div>
                            <h4 class="font-semibold">Skin Graft Workflows</h4>
                            <p class="text-gray-600">Dedicated checklists and tracking for skin graft eligibility, application, and post-op monitoring.</p>
                        </div>
                    </li>
                    <li class="flex items-start space-x-3">
                        <i data-lucide="message-square" class="w-6 h-6 text-blue-600 flex-shrink-0 mt-1"></i>
                        <div>
                            <h4 class="font-semibold">Secure Team Chat</h4>
                            <p class="text-gray-600">Real-time, HIPAA-compliant messaging to coordinate care between clinicians and staff instantly.</p>
                        </div>
                    </li>
                    <li class="flex items-start space-x-3">
                        <i data-lucide="map" class="w-6 h-6 text-blue-600 flex-shrink-0 mt-1"></i>
                        <div>
                            <h4 class="font-semibold">Clinician Visit Routing</h4>
                            <p class="text-gray-600">A unique map view optimizes routes for visiting clinicians, saving time and mileage.</p>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

    </div>
</section>

<!-- CTA Section -->
<section id="cta" class="cta-gradient py-20 lg:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
        <div class="text-white text-center lg:text-left">
            <h2 class="text-3xl lg:text-4xl font-extrabold">
                Upgrade your practice today.
            </h2>
            <p class="text-lg text-white/90 mt-5 max-w-2xl">
                Reduce clinician burnout, improve accuracy, and visualize healing like never before. See for yourself why EC Wound Charting is the most advanced, AI-driven wound care EMR on the market.
            </p>
            <button onclick="openModal()" class="mt-8 bg-white text-blue-700 font-semibold py-3 px-8 rounded-full shadow-2xl hover:bg-gray-100 transition duration-300 text-lg">
                Schedule Your Free Demo
            </button>
        </div>
        <div class="flex justify-center">
            <img src="img/confident.png"
                 alt="Confident doctor with modern tech"
                 class="rounded-2xl shadow-2xl"
                 onerror="this.src='https://placehold.co/500x400/FFFFFF/3b82f6?text=Schedule+Demo'"/>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="bg-gray-900 text-gray-400 py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row justify-between items-center">
            <div class="flex items-center space-x-2 mb-6 md:mb-0">
                <!-- Placeholder Logo -->
                <svg class="h-8 w-8 text-white" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM11 11V7H13V11H17V13H13V17H11V13H7V11H11Z" />
                </svg>
                <span class="font-bold text-xl text-white">EC Wound Charting</span>
            </div>
            <nav class="flex flex-wrap justify-center md:justify-end gap-x-6 gap-y-2">
                <a href="#features" class="hover:text-white transition">Features</a>
                <a href="#solution" class="hover:text-white transition">Solution</a>
                <a href="#" class="hover:text-white transition">Pricing</a>
                <a href="#" class="hover:text-white transition">Contact</a>
                <a href="#" class="hover:text-white transition">Privacy Policy</a>
            </nav>
        </div>
        <div class="text-center text-gray-500 mt-10 pt-8 border-t border-gray-800">
            &copy; 2025 EC Wound Charting. All rights reserved.
        </div>
    </div>
</footer>


<!-- Login Modal -->
<div id="loginModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center opacity-0 transition-opacity duration-300">
    <div class="bg-white rounded-lg p-8 max-w-md w-full mx-4 transform scale-95 transition-transform duration-300 relative">
        <button onclick="closeLoginModal()" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700">
            <i data-lucide="x" class="w-6 h-6"></i>
        </button>
        
        <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Login to Your Account</h2>
        
        <form action="login.php" method="POST" class="space-y-4">
            <div>
                <label for="login_email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                <input type="email" id="login_email" name="email" required 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
            </div>
            
            <div>
                <label for="login_password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <input type="password" id="login_password" name="password" required 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
            </div>

            <div class="flex items-center justify-between text-sm">
                <label class="flex items-center">
                    <input type="checkbox" class="form-checkbox h-4 w-4 text-blue-600">
                    <span class="ml-2 text-gray-600">Remember me</span>
                </label>
                <a href="forgot_password.php" class="text-blue-600 hover:text-blue-800">Forgot Password?</a>
            </div>
            
            <button type="submit" class="w-full bg-blue-600 text-white font-semibold py-3 px-6 rounded-lg hover:bg-blue-700 transition duration-300 transform hover:scale-[1.02]">
                Sign In
            </button>
        </form>
    </div>
</div>

<!-- Demo Modal -->
<div id="demoModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 hidden">
    <div id="modalContent" class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-8 transform transition-all -translate-y-20 opacity-0">
        <div class="flex justify-between items-center">
            <h3 class="text-2xl font-bold text-gray-900">Schedule a Live Demo</h3>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                <i data-lucide="x" class="w-8 h-8"></i>
            </button>
        </div>
        <p class="text-gray-600 mt-2">
            Fill out the form below and one of our specialists will get in touch to schedule a personalized demo.
        </p>
        <form class="mt-6 space-y-4">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Full Name</label>
                <input type="text" id="name" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Work Email</label>
                <input type="email" id="email" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="practice" class="block text-sm font-medium text-gray-700">Practice / Facility Name</label>
                <input type="text" id="practice" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="pt-2">
                <button type="submit" class="w-full bg-blue-600 text-white font-semibold py-3 px-6 rounded-lg shadow-lg hover:bg-blue-700 transition duration-300">
                    Request Demo
                </button>
            </div>
        </form>
    </div>
</div>


<!-- JavaScript -->
<script>
    // Activate Lucide Icons
    lucide.createIcons();

    // Modal Logic
    const demoModal = document.getElementById('demoModal');
    const modalContent = document.getElementById('modalContent');
    const mobileMenu = document.getElementById('mobile-menu');
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const mobileCloseBtn = document.getElementById('mobile-close-btn');
    const mobileNavLinks = document.querySelectorAll('.mobile-nav-link');

    function openModal() {
        demoModal.classList.remove('hidden');
        setTimeout(() => {
            modalContent.classList.remove('-translate-y-20', 'opacity-0');
            modalContent.classList.add('translate-y-0', 'opacity-100');
        }, 50); // Small delay for transition
        // Close mobile menu if open
        mobileMenu.classList.add('hidden');
    }

    function closeModal() {
        modalContent.classList.add('-translate-y-20', 'opacity-0');
        modalContent.classList.remove('translate-y-0', 'opacity-100');
        setTimeout(() => {
            demoModal.classList.add('hidden');
        }, 300); // Wait for transition to finish
    }

    // Close modal if clicking on the backdrop
    demoModal.addEventListener('click', (e) => {
        if (e.target === demoModal) {
            closeModal();
        }
    });

    // Login Modal Functions
    function openLoginModal() {
        const modal = document.getElementById('loginModal');
        const modalContent = modal.querySelector('div');
        
        modal.classList.remove('hidden');
        // Trigger reflow
        void modal.offsetWidth;
        
        modal.classList.remove('opacity-0');
        modalContent.classList.remove('scale-95');
        modalContent.classList.add('scale-100');
        
        // Initialize Lucide icons in the modal
        lucide.createIcons();
    }

    function closeLoginModal() {
        const modal = document.getElementById('loginModal');
        const modalContent = modal.querySelector('div');
        
        modal.classList.add('opacity-0');
        modalContent.classList.remove('scale-100');
        modalContent.classList.add('scale-95');
        
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    // Close login modal when clicking outside
    document.getElementById('loginModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeLoginModal();
        }
    });

    // Mobile Menu Logic
    mobileMenuBtn.addEventListener('click', () => {
        mobileMenu.classList.remove('hidden');
        mobileMenu.classList.add('flex');
    });

    mobileCloseBtn.addEventListener('click', () => {
        mobileMenu.classList.add('hidden');
        mobileMenu.classList.remove('flex');
    });

    // Close mobile menu when a link is clicked
    mobileNavLinks.forEach(link => {
        link.addEventListener('click', () => {
            mobileMenu.classList.add('hidden');
            mobileMenu.classList.remove('flex');
        });
    });

</script>
</body>
</html>
