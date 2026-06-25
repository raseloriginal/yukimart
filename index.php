<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ইউকিমার্ট - তাজা লোকাল মুদি পণ্য হোম ডেলিভারি</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="assets/js/tailwind.config.js"></script>
    
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }
        /* Custom smooth scrollbar hiding for horizontal category list */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        /* Bottom sheet slide-up state transition */
        .bottom-sheet {
            transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        /* Success checkmark bounce animation */
        @keyframes scaleIn {
            0% { transform: scale(0.9); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
        .animate-scale-in {
            animation: scaleIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased selection:bg-brand-100 selection:text-brand-900 min-h-screen flex flex-col pb-24">

    <!-- Sticky Header -->
    <header class="sticky top-0 z-30 bg-white border-b border-gray-100 shadow-sm">
        <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
            <!-- Brand Logo -->
            <a href="index.php" class="flex items-center space-x-2 group">
                <div class="flex items-center justify-center"><img src="assets/images/logo.png" alt="YukiMart" class="w-10 h-10 object-contain rounded"></div>
                <div>
                    <span class="text-lg font-extrabold tracking-tight text-gray-900 group-hover:text-brand-600 transition-colors">Yuki<span class="text-accent-500">Mart</span></span>
                    <p class="text-[10px] text-gray-400 font-medium tracking-wider uppercase">ডোরস্টেপ ডেলিভারি</p>
                </div>
            </a>

            <!-- Search Bar (Desktop inline, mobile icon / interactive) -->
            <div class="hidden md:flex flex-1 max-w-md mx-8 relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </span>
                <input type="text" id="desktopSearchInput" oninput="handleSearch(this.value)" placeholder="তাজা ফলমূল, দুগ্ধজাত পণ্য, স্ন্যাকস খুঁজুন..." class="w-full pl-10 pr-4 py-2 border border-gray-200 text-sm focus:outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500 rounded bg-gray-50 transition-all">
            </div>

            <!-- Profile & Active Cart Indicator -->
            <div class="flex items-center space-x-4">
                <button onclick="toggleCartDrawer()" class="relative p-2 text-gray-600 hover:text-brand-500 transition-colors md:flex items-center hidden" aria-label="Cart">
                    <i class="fa-solid fa-basket-shopping text-xl"></i>
                    <span id="desktopCartBadge" class="absolute -top-1 -right-1 bg-accent-500 text-gray-900 text-[10px] font-bold rounded-full w-5 h-5 flex items-center justify-center border-2 border-white hidden">0</span>
                </button>
                <a href="admin/login.php" class="flex items-center space-x-2 p-1 focus:outline-none" aria-label="Profile">
                    <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=100&h=100" onerror="this.src='https://via.placeholder.com/100'" alt="User Profile" class="w-8 h-8 rounded-full object-cover ring-2 ring-gray-100">
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="max-w-6xl w-full mx-auto px-4 pt-4 flex-1">
        
        <!-- Mobile Search Header (Sticky or top-most interactive element on phone) -->
        <div class="md:hidden mb-4 relative">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400">
                <i class="fa-solid fa-magnifying-glass"></i>
            </span>
            <input type="text" id="mobileSearchInput" oninput="handleSearch(this.value)" placeholder="পণ্য খুঁজুন..." class="w-full pl-10 pr-10 py-2.5 border border-gray-200 text-sm focus:outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500 rounded bg-white transition-all shadow-sm">
            <button onclick="clearSearch()" id="clearSearchBtn" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 hidden">
                <i class="fa-solid fa-circle-xmark"></i>
            </button>
        </div>

        <!-- Banner Alert / Promo -->
        <div id="delivery-promo-banner" class="bg-brand-50 border border-brand-100 text-brand-900 px-4 py-3 rounded mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-3 shadow-sm transition-all duration-300">
            <div class="flex items-center space-x-3">
                <div class="bg-brand-500 text-white p-1.5 rounded-sm flex items-center justify-center">
                    <i class="fa-solid fa-bolt text-xs"></i>
                </div>
                <div>
                    <p class="text-xs font-bold md:text-sm">সুপার-ফাস্ট ডোরস্টেপ ডেলিভারি!</p>
                    <p class="text-[11px] text-brand-700">৩০-৪৫ মিনিটের মধ্যে তাজা ডেলিভারি।</p>
                </div>
            </div>
            <span class="text-xs font-semibold bg-white border border-brand-200 px-2.5 py-1 text-brand-700 rounded-sm">সর্বনিম্ন অর্ডার ৳১০০</span>
        </div>

        <!-- Categories Section Header -->
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-extrabold text-gray-900 uppercase tracking-wider">ক্যাটাগরি ব্রাউজ করুন</h2>
            <span class="text-xs text-gray-400 hover:text-brand-500 cursor-pointer transition-colors" onclick="filterCategory('all')">সব দেখুন</span>
        </div>

        <!-- Categories Horizontal Scroller -->
        <div class="relative mb-6">
            <div id="categoriesContainer" class="flex overflow-x-auto space-x-3 pb-2 no-scrollbar scroll-smooth snap-x">
                <!-- Dynamically Generated Categories -->
            </div>
        </div>

        <!-- Product Listing Section Header -->
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 id="sectionTitle" class="text-lg font-extrabold text-gray-900 tracking-tight">তাজা পণ্য</h2>
                <p id="sectionCount" class="text-xs text-gray-400 font-medium">আইটেম লোড হচ্ছে...</p>
            </div>
            <div class="flex items-center space-x-2 text-xs">
                <span class="text-gray-400">সর্ট:</span>
                <select id="sortSelect" onchange="handleSort()" class="border-0 bg-transparent font-semibold text-brand-600 focus:ring-0 cursor-pointer">
                    <option value="default">জনপ্রিয়</option>
                    <option value="low-high">দাম: কম থেকে বেশি</option>
                    <option value="high-low">দাম: বেশি থেকে কম</option>
                </select>
            </div>
        </div>

        <!-- Product Grid Loaders (Skeleton Screen) -->
        <div id="skeletonLoader" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 md:gap-4 mb-12">
            <!-- 8 Skeleton cards generated via JS loop initially -->
            <script>
                for(let i=0; i<8; i++){
                    document.write(`
                    <div class="bg-white p-3 border border-gray-100 rounded shadow-sm animate-pulse flex flex-col justify-between h-72">
                        <div class="bg-gray-200 h-32 w-full rounded mb-3"></div>
                        <div class="space-y-2 flex-1">
                            <div class="bg-gray-200 h-4 w-3/4 rounded"></div>
                            <div class="bg-gray-200 h-3 w-1/2 rounded"></div>
                        </div>
                        <div class="flex items-center justify-between mt-4">
                            <div class="bg-gray-200 h-5 w-1/3 rounded"></div>
                            <div class="bg-gray-200 h-8 w-1/3 rounded"></div>
                        </div>
                    </div>
                    `);
                }
            </script>
        </div>

        <!-- Main Product Grid -->
        <div id="productGrid" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 md:gap-4 mb-16 hidden">
            <!-- Dynamically populated via JS -->
        </div>

        <!-- Empty Search / Filter State -->
        <div id="emptyState" class="hidden flex-col items-center justify-center py-16 text-center">
            <div class="bg-gray-100 text-gray-400 w-16 h-16 flex items-center justify-center rounded-full mb-4">
                <i class="fa-regular fa-face-frown text-2xl"></i>
            </div>
            <h3 class="font-bold text-gray-800">কোনো পণ্য পাওয়া যায়নি</h3>
            <p class="text-sm text-gray-400 max-w-xs mt-1">আপনার অনুসন্ধানের সাথে মেলে এমন কোনো পণ্য পাওয়া যায়নি। ক্যাটাগরি ব্রাউজ করার চেষ্টা করুন!</p>
            <button onclick="clearSearch(); filterCategory('all');" class="mt-4 px-4 py-2 bg-brand-500 text-white text-xs font-semibold rounded hover:bg-brand-600 transition-all">
                ফিল্টার রিসেট করুন
            </button>
        </div>

    </main>

    <!-- Floating Cart Button (At the bottom center, fixed) -->
    <div id="floatingCart" class="fixed bottom-0 inset-x-0 z-40 px-4 pb-4 transition-all duration-300 transform translate-y-24 pointer-events-none">
        <div class="max-w-md mx-auto pointer-events-auto">
            <button onclick="toggleCartDrawer()" class="w-full bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-white flex items-center justify-between p-4 pt-5 shadow-xl rounded hover:scale-[1.01] transition-all group duration-200 relative overflow-hidden">
                <!-- Progress Bar at top edge -->
                <div class="absolute top-0 left-0 right-0 h-1 bg-black/20">
                    <div id="floatingCartProgressBar" class="h-full bg-brand-100 transition-all duration-500 ease-out" style="width: 0%"></div>
                </div>
                <!-- Wave Pulse Effect -->
                <span class="absolute inset-0 bg-brand-500 opacity-0 group-active:opacity-20 transition-opacity"></span>
                
                <div class="flex items-center space-x-3">
                    <div class="bg-accent-500 text-gray-900 px-2.5 py-1.5 rounded-sm font-bold text-sm flex items-center space-x-1.5">
                        <i class="fa-solid fa-basket-shopping"></i>
                        <span id="floatingCartCount">0</span>
                    </div>
                    <div class="text-left">
                        <p class="text-xs font-bold text-brand-100 leading-tight">বাস্কেট দেখুন</p>
                        <p class="text-xs text-white opacity-80" id="floatingCartItemsText">০ টি আইটেম যোগ করা হয়েছে</p>
                    </div>
                </div>
                
                <div class="flex items-center space-x-2 font-extrabold text-sm tracking-tight">
                    <span id="floatingCartTotal">৳0</span>
                    <i class="fa-solid fa-chevron-up text-xs transition-transform group-hover:-translate-y-0.5"></i>
                </div>
            </button>
        </div>
    </div>

    <!-- Backdrop overlay for bottom sheet/modal -->
    <div id="backdrop" class="fixed inset-0 bg-black/50 z-40 opacity-0 pointer-events-none transition-opacity duration-300" onclick="toggleCartDrawer()"></div>

    <!-- Bottom Sheet / Cart Drawer -->
    <div id="cartDrawer" class="bottom-sheet fixed bottom-0 inset-x-0 z-50 bg-white shadow-2xl rounded-t-lg max-h-[85vh] md:max-h-[90vh] flex flex-col transform translate-y-full max-w-lg mx-auto">
        <!-- Drawer Handle & Header with Step Tracker -->
        <div class="flex flex-col items-center pt-2 pb-3 border-b border-gray-150 px-4 shrink-0 bg-white rounded-t-lg">
            <div class="w-12 h-1.5 bg-gray-200 rounded-full mb-3 cursor-pointer" onclick="toggleCartDrawer()"></div>
            
            <!-- Step Tracker Indicator -->
            <div class="w-full flex items-center justify-between px-2">
                <!-- Step 1 Indicator -->
                <div id="step-tab-1" onclick="goToStep(1)" class="flex items-center space-x-1.5 cursor-pointer group">
                    <span id="step-badge-1" class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-black bg-brand-500 text-white transition-all shadow-xs">1</span>
                    <span id="step-label-1" class="text-xs font-black text-gray-900 group-hover:text-brand-600 transition-colors">বাস্কেট</span>
                </div>
                <div id="step-divider-1" class="flex-1 h-0.5 bg-gray-250 mx-2 transition-all"></div>
                <!-- Step 2 Indicator -->
                <div id="step-tab-2" onclick="goToStep(2)" class="flex items-center space-x-1.5 cursor-pointer group">
                    <span id="step-badge-2" class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold bg-gray-100 text-gray-400 transition-all">2</span>
                    <span id="step-label-2" class="text-xs font-bold text-gray-400 group-hover:text-brand-600 transition-colors">ডেলিভারি</span>
                </div>
                <div id="step-divider-2" class="flex-1 h-0.5 bg-gray-250 mx-2 transition-all"></div>
                <!-- Step 3 Indicator -->
                <div id="step-tab-3" onclick="goToStep(3)" class="flex items-center space-x-1.5 cursor-pointer group">
                    <span id="step-badge-3" class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold bg-gray-100 text-gray-400 transition-all">3</span>
                    <span id="step-label-3" class="text-xs font-bold text-gray-400 group-hover:text-brand-600 transition-colors">রিভিউ</span>
                </div>
            </div>
        </div>

        <!-- Drawer Content (Scrollable Area) -->
        <div class="overflow-y-auto flex-1 p-4 space-y-6">
            
            <!-- Step 1: Basket Review Pane -->
            <div id="checkout-step-1" class="space-y-4">
                <!-- Dynamic Free Delivery Promo Banner inside Drawer -->
                <div id="drawer-delivery-promo"></div>
                
                <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                    <div class="flex items-center space-x-2">
                        <i class="fa-solid fa-basket-shopping text-brand-600"></i>
                        <h4 class="text-xs font-extrabold text-gray-400 uppercase tracking-wider">নির্বাচিত আইটেম (<span id="drawerCartCount">0</span>)</h4>
                    </div>
                    <button onclick="clearCart()" class="text-xs font-semibold text-red-500 hover:text-red-600 flex items-center space-x-1 p-1">
                        <i class="fa-regular fa-trash-can"></i>
                        <span>সব মুছুন</span>
                    </button>
                </div>
                <div id="drawerCartItems" class="space-y-3">
                    <!-- Cart items populated by JS -->
                </div>
                <div id="drawerEmptyState" class="text-center py-8 hidden">
                    <p class="text-sm text-gray-400">আপনার বাস্কেট খালি। শুরু করতে পণ্য যোগ করুন!</p>
                </div>
                
                <!-- Quick Summary in Step 1 -->
                <div class="bg-gray-50 p-3 rounded border border-gray-100 flex items-center justify-between mt-4">
                    <span class="text-xs font-bold text-gray-500">সাবটোটাল:</span>
                    <span id="step1Subtotal" class="text-sm font-black text-gray-900">৳0</span>
                </div>
            </div>

            <!-- Step 2: ডেলিভারির বিবরণ Form Pane -->
            <div id="checkout-step-2" class="space-y-4 hidden">
                <div class="bg-gray-50/50 p-4 md:p-5 rounded-lg border border-gray-200 shadow-sm">
                    <h4 class="text-base md:text-lg font-black text-gray-900 border-b border-gray-200 pb-3 mb-5 flex items-center space-x-2">
                        <div class="bg-brand-100 p-2 rounded-full text-brand-600 flex items-center justify-center">
                            <i class="fa-solid fa-truck"></i>
                        </div>
                        <span>ডেলিভারির বিবরণ</span>
                    </h4>
                    
                    <form id="checkoutForm" class="space-y-5" onsubmit="event.preventDefault();">
                        <!-- পুরো নাম -->
                        <div>
                            <label for="customerName" class="block text-sm font-bold text-gray-800 mb-1.5">পুরো নাম <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                                    <i class="fa-regular fa-user"></i>
                                </span>
                                <input type="text" id="customerName" placeholder="পুরো নাম লিখুন" required class="w-full pl-10 pr-4 py-3 border border-gray-300 text-sm focus:outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 rounded-md bg-white transition-all shadow-sm">
                            </div>
                        </div>

                        <!-- মোবাইল নম্বর -->
                        <div>
                            <label for="customerMobile" class="block text-sm font-bold text-gray-800 mb-1.5">মোবাইল নম্বর <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                                    <i class="fa-solid fa-phone"></i>
                                </span>
                                <input type="tel" id="customerMobile" placeholder="01XXXXXXXXX" pattern="01[3-9][0-9]{8}" required class="w-full pl-10 pr-4 py-3 border border-gray-300 text-sm focus:outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 rounded-md bg-white transition-all shadow-sm">
                            </div>
                            <p class="text-[11px] font-medium text-gray-500 mt-1.5"><i class="fa-solid fa-circle-info mr-1"></i>অবশ্যই ১১-ডিজিটের বৈধ নম্বর হতে হবে (যেমন: ০১৭১২৩৪৫৬৭৮)</p>
                        </div>

                        <!-- ডেলিভারি এরিয়া Selection -->
                        <div>
                            <label for="deliveryArea" class="block text-sm font-bold text-gray-800 mb-1.5">ডেলিভারি এরিয়া <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                                    <i class="fa-solid fa-map-location-dot"></i>
                                </span>
                                <select id="deliveryArea" required onchange="handleAreaChange(this.value)" class="w-full pl-10 pr-8 py-3 border border-gray-300 text-sm focus:outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 rounded-md bg-white appearance-none transition-all shadow-sm cursor-pointer">
                                    <option value="">-- এরিয়া নির্বাচন করুন --</option>
                                    <!-- Injected via JS -->
                                </select>
                                <span class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400 pointer-events-none">
                                    <i class="fa-solid fa-chevron-down text-xs"></i>
                                </span>
                            </div>
                        </div>

                        <!-- সঠিক ঠিকানা Textarea -->
                        <div>
                            <label for="deliveryAddress" class="block text-sm font-bold text-gray-800 mb-1.5">সঠিক ঠিকানা <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <textarea id="deliveryAddress" rows="3" placeholder="বাসা/ফ্ল্যাট নং, রাস্তা, ল্যান্ডমার্কের বিবরণ..." required class="w-full p-3 border border-gray-300 text-sm focus:outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 rounded-md bg-white transition-all shadow-sm resize-none"></textarea>
                            </div>
                        </div>

                        <!-- Delivery Time Slot Selection -->
                        <div>
                            <label for="deliverySlot" class="block text-sm font-bold text-gray-800 mb-1.5">পছন্দের ডেলিভারি স্লট <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                                    <i class="fa-regular fa-clock"></i>
                                </span>
                                <select id="deliverySlot" required class="w-full pl-10 pr-8 py-3 border border-gray-300 text-sm focus:outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 rounded-md bg-white appearance-none transition-all shadow-sm cursor-pointer">
                                    <option value="">-- সময়ের স্লট নির্বাচন করুন --</option>
                                    <!-- Injected via JS -->
                                </select>
                                <span class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400 pointer-events-none">
                                    <i class="fa-solid fa-chevron-down text-xs"></i>
                                </span>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Step 3: Order Review & Coupon Pane -->
            <div id="checkout-step-3" class="space-y-5 hidden">
                <!-- Delivery Info Summary Card -->
                <div class="bg-gray-50/80 rounded border border-gray-150 p-3.5 relative">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-2 mb-2">
                        <span class="text-[11px] font-extrabold text-gray-400 uppercase tracking-wider">ডেলিভারির সারসংক্ষেপ</span>
                        <button type="button" onclick="goToStep(2)" class="text-xs font-bold text-brand-600 hover:text-brand-700 flex items-center space-x-1">
                            <i class="fa-solid fa-pen text-[10px]"></i>
                            <span>এডিট</span>
                        </button>
                    </div>
                    <div class="text-xs space-y-1.5 text-gray-600">
                        <p class="text-gray-800"><strong class="text-gray-900 font-bold">প্রাপক:</strong> <span id="reviewName" class="font-medium"></span> (<span id="reviewPhone" class="font-medium"></span>)</p>
                        <p class="text-gray-800"><strong class="text-gray-900 font-bold">স্লট:</strong> <span id="reviewSlot" class="font-medium"></span></p>
                        <p class="text-gray-800 break-words"><strong class="text-gray-900 font-bold">ঠিকানা:</strong> <span id="reviewAddress" class="font-medium"></span></p>
                    </div>
                </div>

                <!-- Coupon Section -->
                <div class="bg-white border border-gray-150 rounded p-4 space-y-3 shadow-xs">
                    <div class="flex items-center space-x-2">
                        <i class="fa-solid fa-ticket text-brand-500"></i>
                        <label for="couponInput" class="text-xs font-bold text-gray-800">প্রোমো / কুপন কোড ব্যবহার করুন</label>
                    </div>
                    
                    <!-- Coupon Input Group -->
                    <div id="coupon-input-group" class="flex space-x-2">
                        <input type="text" id="couponInput" placeholder="কুপন কোড লিখুন" class="flex-1 px-3 py-2 border border-gray-200 text-xs focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/10 uppercase rounded bg-gray-50/50">
                        <button type="button" onclick="applyCoupon()" id="couponApplyBtn" class="bg-brand-500 hover:bg-brand-600 active:bg-brand-700 text-white text-xs font-extrabold px-4 py-2 rounded transition-all">
                            Apply
                        </button>
                    </div>

                    <!-- Applied Coupon Banner -->
                    <div id="applied-coupon-banner" class="hidden bg-brand-50 border border-brand-100 rounded-sm p-2.5 flex items-center justify-between text-xs text-emerald-800 animate-scale-in">
                        <div class="flex items-center space-x-2">
                            <i class="fa-solid fa-circle-check text-brand-600"></i>
                            <div>
                                <span class="font-extrabold" id="applied-coupon-code"></span>
                                <span class="text-[10px] text-brand-700 block" id="applied-coupon-desc"></span>
                            </div>
                        </div>
                        <button type="button" onclick="removeCoupon()" class="text-red-500 hover:text-red-600 hover:bg-red-50 p-1.5 rounded transition-all" title="কুপন মুছুন">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                    
                    <!-- Coupon Error Alert -->
                    <div id="coupon-error" class="hidden text-xs text-red-500 flex items-center space-x-1 px-1">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span id="coupon-error-msg"></span>
                    </div>
                </div>

                <!-- Order Financial Summary Section -->
                <div class="border-t border-gray-150 pt-4 space-y-2.5">
                    <h4 class="text-xs font-extrabold text-gray-400 uppercase tracking-wider mb-2 flex items-center space-x-1">
                        <i class="fa-solid fa-receipt text-gray-400"></i>
                        <span>অর্ডারের সারসংক্ষেপ</span>
                    </h4>
                    <div class="flex justify-between items-center text-xs text-gray-600">
                        <span>আইটেমের সাবটোটাল</span>
                        <span id="summarySubtotal" class="font-bold text-gray-900">৳0</span>
                    </div>
                    <div id="summaryDiscountRow" class="hidden flex justify-between items-center text-xs text-brand-700 font-semibold bg-brand-50/50 px-2 py-1.5 rounded border border-dashed border-emerald-200">
                        <span class="flex items-center space-x-1">
                            <i class="fa-solid fa-ticket text-brand-600"></i>
                            <span>কুপন ডিসকাউন্ট</span>
                        </span>
                        <span id="summaryDiscount">৳0</span>
                    </div>
                    <div class="flex justify-between items-center text-xs text-gray-600">
                        <span>ডেলিভারি চার্জ</span>
                        <span id="summaryDeliveryCharge" class="font-bold text-gray-900">৳0</span>
                    </div>
                    <div class="flex justify-between items-center text-sm pt-3 border-t border-dashed border-gray-200 font-extrabold text-gray-950">
                        <span>সর্বমোট</span>
                        <span id="summaryGrandTotal" class="text-lg text-brand-600 font-black">৳0</span>
                    </div>
                </div>
            </div>

        </div>

        <!-- Checkout Trigger Footer inside drawer -->
        <div class="p-4 bg-gray-50 border-t border-gray-100 shrink-0 flex space-x-2" id="drawerFooterButtons">
            <!-- Step 1 Footer Buttons -->
            <div id="footer-buttons-step-1" class="w-full flex space-x-2">
                <button type="button" onclick="goToStep(2)" id="step1NextBtn" class="flex-1 bg-accent-500 hover:bg-accent-600 active:bg-accent-700 text-gray-900 font-extrabold text-sm py-3.5 px-4 rounded shadow flex items-center justify-center space-x-2 transition-all">
                    <span>ডেলিভারি তথ্যে যান (৳<span id="step1TotalValue">0</span>)</span>
                    <i class="fa-solid fa-arrow-right text-xs"></i>
                </button>
                <button type="button" onclick="toggleCartDrawer()" class="bg-red-50 hover:bg-red-500 hover:text-white active:bg-red-600 text-red-600 font-bold text-sm py-3.5 px-4 rounded transition-all flex items-center justify-center space-x-1.5 border border-red-200">
                    <i class="fa-solid fa-xmark text-sm"></i>
                    <span>বন্ধ করুন</span>
                </button>
            </div>
            
            <!-- Step 2 Footer Buttons -->
            <div id="footer-buttons-step-2" class="w-full flex space-x-2 hidden">
                <button type="button" onclick="validateAndGoToReview()" class="flex-1 bg-accent-500 hover:bg-accent-600 active:bg-accent-700 text-gray-900 font-extrabold text-sm py-3.5 px-4 rounded shadow flex items-center justify-center space-x-2 transition-all">
                    <span>রিভিউতে যান (৳<span id="step2TotalValue">0</span>)</span>
                    <i class="fa-solid fa-arrow-right text-xs"></i>
                </button>
                <button type="button" onclick="goToStep(1)" class="bg-gray-100 hover:bg-gray-200 active:bg-gray-300 text-gray-700 font-bold text-sm py-3.5 px-4 rounded transition-all flex items-center justify-center space-x-1.5 border border-gray-200">
                    <i class="fa-solid fa-chevron-left text-xs"></i>
                    <span>পিছনে</span>
                </button>
            </div>

            <!-- Step 3 Footer Buttons -->
            <div id="footer-buttons-step-3" class="w-full flex space-x-2 hidden">
                <button type="button" onclick="handleCheckout()" id="checkoutSubmitBtn" class="flex-1 bg-accent-500 hover:bg-accent-600 active:bg-accent-700 text-gray-900 font-extrabold text-sm py-3.5 px-4 rounded shadow flex items-center justify-center space-x-2 transition-all">
                    <span>অর্ডার করুন (৳<span id="checkoutTotalValue">0</span>)</span>
                    <i class="fa-solid fa-arrow-right text-xs"></i>
                </button>
                <button type="button" onclick="goToStep(2)" class="bg-gray-100 hover:bg-gray-200 active:bg-gray-300 text-gray-700 font-bold text-sm py-3.5 px-4 rounded transition-all flex items-center justify-center space-x-1.5 border border-gray-200">
                    <i class="fa-solid fa-chevron-left text-xs"></i>
                    <span>পিছনে</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Success Confirmation Modal -->
    <div id="successModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 opacity-0 pointer-events-none transition-opacity duration-300">
        <!-- Modal Backdrop -->
        <div class="absolute inset-0 bg-black/60"></div>
        
        <!-- Modal Body Container -->
        <div class="bg-white rounded shadow-2xl w-full max-w-md p-6 relative z-10 animate-scale-in text-center max-h-[90vh] overflow-y-auto">
            <!-- Success Icon Animation -->
            <div class="mx-auto w-16 h-16 bg-brand-50 border border-brand-100 text-brand-600 flex items-center justify-center rounded-full mb-4">
                <i class="fa-solid fa-check-double text-2xl"></i>
            </div>
            
            <h3 class="text-xl font-black text-gray-900 tracking-tight">অর্ডার সফলভাবে গ্রহণ করা হয়েছে!</h3>
            <p class="text-sm text-gray-500 mt-2">আপনার অর্ডারের জন্য ধন্যবাদ! আমরা আপনার তাজা পণ্যগুলো প্রস্তুত করছি।</p>
            
            <!-- Complete Order ইনভয়েস ডিটেইল Area -->
            <div class="bg-slate-50 p-4 border border-slate-100 rounded text-left mt-5 space-y-3 text-xs">
                <div class="flex justify-between border-b border-slate-200/60 pb-2">
                    <span class="text-gray-400 font-bold uppercase">ইনভয়েস ডিটেইল</span>
                    <span id="invoiceNo" class="font-bold text-gray-800">#ORD-00000</span>
                </div>
                
                <div class="space-y-1.5 max-h-36 overflow-y-auto no-scrollbar" id="invoiceItems">
                    <!-- Items inserted in dynamic template -->
                </div>

                <div class="border-t border-dashed border-slate-200/60 pt-2 space-y-1">
                    <div class="flex justify-between text-gray-600">
                        <span>সাবটোটাল:</span>
                        <span id="invoiceSubtotal" class="font-bold text-gray-800">৳0</span>
                    </div>
                    <div id="invoiceDiscountRow" class="hidden flex justify-between text-brand-700 font-semibold">
                        <span>কুপন ডিসকাউন্ট:</span>
                        <span id="invoiceDiscount">-৳0</span>
                    </div>
                    <div class="flex justify-between text-gray-600">
                        <span>ডেলিভারি ফি:</span>
                        <span id="invoiceDelivery" class="font-bold text-gray-800">৳0</span>
                    </div>
                    <div class="flex justify-between text-base text-gray-900 font-extrabold pt-1">
                        <span>সর্বমোট পরিশোধ:</span>
                        <span id="invoiceGrandTotal" class="text-brand-600">৳0</span>
                    </div>
                </div>

                <div class="border-t border-slate-200/60 pt-2 space-y-1 text-gray-600">
                    <div><strong>কাস্টমার:</strong> <span id="invoiceName" class="text-gray-800"></span></div>
                    <div><strong>যোগাযোগ:</strong> <span id="invoicePhone" class="text-gray-800"></span></div>
                    <div><strong>ডেলিভারি স্লট:</strong> <span id="invoiceSlot" class="text-gray-800"></span></div>
                    <div><strong>ঠিকানা:</strong> <span id="invoiceAddress" class="text-gray-800 break-words"></span></div>
                </div>
            </div>

            <!-- OK / Track Button -->
            <div class="mt-6 flex flex-col space-y-2">
                <button onclick="closeSuccessModal()" class="w-full bg-brand-600 hover:bg-brand-700 text-white font-extrabold py-3 rounded text-sm transition-all shadow">
                    Awesome, Thank You!
                </button>
            </div>
        </div>
    </div>

    <!-- Script Execution -->
    <script src="assets/js/app.js?v=3"></script>
</body>
</html>
