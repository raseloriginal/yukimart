// Global Application State
let PRODUCTS = [];
let CATEGORIES = [];
let AREAS = [];
let SLOTS = [];

let state = {
    cart: (() => {
        try {
            return JSON.parse(localStorage.getItem('yukimart_cart')) || {};
        } catch (e) {
            return {};
        }
    })(), // { productId: quantity }
    selectedCategory: 'all',
    searchQuery: '',
    sortBy: 'default',
    deliveryAreaId: '',
    deliveryCharge: 0,
    isCartOpen: false,
    checkoutStep: 1,
    couponCode: null,
    couponDiscount: 0,
    couponDiscountType: null,
    couponDiscountValue: 0,
    couponMinOrder: 0,
    freeDeliveryThreshold: 0
};

// Initialize App on DOM Loaded
window.addEventListener('DOMContentLoaded', () => {
    fetchData();
});

async function fetchData() {
    try {
        const response = await fetch('api/products.php');
        const data = await response.json();
        
        if (data.success) {
            PRODUCTS = data.products.map(p => ({
                id: p.id,
                name: p.name,
                price: parseFloat(p.regular_price), // Can map to wholesale if authenticated
                image: p.image_url,
                category_id: p.category_id,
                category: data.categories.find(c => c.id == p.category_id)?.name || 'মুদি'
            }));
            
            CATEGORIES = [{ id: 'all', name: 'সব আইটেম', icon: 'fa-solid fa-list-ul' }];
            data.categories.forEach(c => {
                CATEGORIES.push({ id: c.id, name: c.name, icon: 'fa-solid fa-box' });
            });
            
            AREAS = data.areas;
            SLOTS = data.slots;
            state.freeDeliveryThreshold = parseFloat(data.free_delivery_amount || 0);
            
            renderCategories();
            renderAreaOptions();
            renderSlotOptions();
            
            // Remove skeleton loader
            document.getElementById('skeletonLoader').classList.add('hidden');
            document.getElementById('productGrid').classList.remove('hidden');
            
            renderProducts();
            updateCartUI();
        }
    } catch (error) {
        console.error("Error fetching data", error);
        showToast("পণ্য লোড করতে ব্যর্থ। অনুগ্রহ করে রিফ্রেশ করুন।");
    }
}

// UI Renders
function renderCategories() {
    const container = document.getElementById('categoriesContainer');
    if (!container) return;
    
    container.innerHTML = CATEGORIES.map(cat => {
        const isActive = state.selectedCategory == cat.id;
        return `
            <button onclick="filterCategory('${cat.id}')" class="category-btn shrink-0 snap-start flex items-center space-x-2 px-4 py-2.5 rounded border ${
                isActive 
                ? 'bg-brand-500 text-white border-brand-500 font-bold shadow-sm' 
                : 'bg-white text-gray-700 border-gray-200 hover:border-brand-300 hover:text-brand-600'
            } transition-all duration-200 text-xs">
                <i class="${cat.icon} text-sm"></i>
                <span>${cat.name}</span>
            </button>
        `;
    }).join('');
}

function renderAreaOptions() {
    const select = document.getElementById('deliveryArea');
    if (!select) return;
    
    let options = '<option value="">-- Choose Area --</option>';
    AREAS.forEach(a => {
        options += `<option value="${a.id}" data-charge="${a.delivery_charge}">${a.name} (Delivery: ৳${a.delivery_charge})</option>`;
    });
    select.innerHTML = options;
}

function renderSlotOptions() {
    const select = document.getElementById('deliverySlot');
    if (!select) return;
    
    let options = '<option value="">-- Select Time Slot --</option>';
    SLOTS.forEach(s => {
        options += `<option value="${s.id}">${s.slot_time}</option>`;
    });
    select.innerHTML = options;
}

// Handlers
function filterCategory(catId) {
    state.selectedCategory = catId;
    renderCategories();
    renderProducts();
}

function handleSearch(val) {
    state.searchQuery = val.trim().toLowerCase();
    
    const clearBtn = document.getElementById('clearSearchBtn');
    if (state.searchQuery.length > 0) {
        clearBtn?.classList.remove('hidden');
    } else {
        clearBtn?.classList.add('hidden');
    }
    
    document.getElementById('desktopSearchInput').value = val;
    document.getElementById('mobileSearchInput').value = val;
    
    renderProducts();
}

function clearSearch() {
    state.searchQuery = '';
    document.getElementById('desktopSearchInput').value = '';
    document.getElementById('mobileSearchInput').value = '';
    document.getElementById('clearSearchBtn')?.classList.add('hidden');
    renderProducts();
}

function handleSort() {
    state.sortBy = document.getElementById('sortSelect').value;
    renderProducts();
}

// Render Products Grid
function renderProducts() {
    const grid = document.getElementById('productGrid');
    const emptyState = document.getElementById('emptyState');
    if (!grid) return;
    
    let filtered = PRODUCTS.filter(prod => {
        const matchesCategory = state.selectedCategory === 'all' || prod.category_id == state.selectedCategory;
        const matchesSearch = prod.name.toLowerCase().includes(state.searchQuery);
        return matchesCategory && matchesSearch;
    });

    if (state.sortBy === 'low-high') {
        filtered.sort((a, b) => a.price - b.price);
    } else if (state.sortBy === 'high-low') {
        filtered.sort((a, b) => b.price - a.price);
    }

    const sectionTitle = document.getElementById('sectionTitle');
    const sectionCount = document.getElementById('sectionCount');
    const currentCatName = CATEGORIES.find(c => c.id == state.selectedCategory)?.name || "Products";
    if (sectionTitle) sectionTitle.textContent = currentCatName;
    if (sectionCount) sectionCount.textContent = `${filtered.length} টি আইটেম পাওয়া গেছে`;

    if (filtered.length === 0) {
        grid.classList.add('hidden');
        emptyState.classList.remove('hidden');
        return;
    }

    grid.classList.remove('hidden');
    emptyState.classList.add('hidden');

    grid.innerHTML = filtered.map(prod => {
        const qty = state.cart[prod.id] || 0;
        
        let actionBtnHtml = '';
        if (qty === 0) {
            actionBtnHtml = `
                <button onclick="changeQty(${prod.id}, 1)" class="bg-brand-50 hover:bg-brand-500 hover:text-white border border-brand-200 hover:border-brand-500 text-brand-600 font-extrabold w-10 h-9 rounded flex items-center justify-center transition-all focus:outline-none" aria-label="Add to cart">
                    <i class="fa-solid fa-plus text-sm"></i>
                </button>
            `;
        } else {
            actionBtnHtml = `
                <div class="flex items-center bg-brand-500 text-white rounded border border-brand-500 overflow-hidden h-9">
                    <button onclick="changeQty(${prod.id}, -1)" class="px-2.5 h-full hover:bg-brand-600 flex items-center justify-center transition-colors focus:outline-none" aria-label="Decrease quantity">
                        <i class="fa-solid fa-minus text-[10px]"></i>
                    </button>
                    <span class="px-2 text-xs font-black select-none">${qty}</span>
                    <button onclick="changeQty(${prod.id}, 1)" class="px-2.5 h-full hover:bg-brand-600 flex items-center justify-center transition-colors focus:outline-none" aria-label="Increase quantity">
                        <i class="fa-solid fa-plus text-[10px]"></i>
                    </button>
                </div>
            `;
        }

        return `
            <div class="bg-white border border-gray-150/80 rounded shadow-sm hover:shadow-md transition-all duration-200 flex flex-col justify-between group overflow-hidden">
                <div class="relative bg-gray-50 overflow-hidden pt-[75%]">
                    <img src="${prod.image}" alt="${prod.name}" loading="lazy" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                    <span class="absolute top-2 left-2 bg-white/90 backdrop-blur-xs text-[9px] font-extrabold uppercase px-2 py-0.5 rounded-sm tracking-wider text-gray-500 shadow-xs">
                        ${prod.category}
                    </span>
                </div>
                <div class="p-3 flex-1 flex flex-col justify-between">
                    <div class="mb-2">
                        <h3 class="text-xs font-bold text-gray-800 line-clamp-2 leading-tight min-h-[2rem] hover:text-brand-600 transition-colors cursor-pointer">
                            ${prod.name}
                        </h3>
                    </div>
                    <div class="flex items-center justify-between mt-1">
                        <div>
                            <span class="text-sm font-black text-gray-950">৳${prod.price}</span>
                        </div>
                        <div id="prod-action-${prod.id}">
                            ${actionBtnHtml}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function changeQty(prodId, amt) {
    const currentQty = state.cart[prodId] || 0;
    const newQty = Math.max(0, currentQty + amt);
    
    if (newQty === 0) {
        delete state.cart[prodId];
    } else {
        state.cart[prodId] = newQty;
    }

    updateCartUI();
    
    // Quick DOM update to avoid full re-render
    const actionContainer = document.getElementById(`prod-action-${prodId}`);
    if (actionContainer) {
        const prod = PRODUCTS.find(p => p.id == prodId);
        let html = '';
        if (newQty === 0) {
            html = `
                <button onclick="changeQty(${prod.id}, 1)" class="bg-brand-50 hover:bg-brand-500 hover:text-white border border-brand-200 hover:border-brand-500 text-brand-600 font-extrabold w-10 h-9 rounded flex items-center justify-center transition-all focus:outline-none" aria-label="Add to cart">
                    <i class="fa-solid fa-plus text-sm"></i>
                </button>
            `;
        } else {
            html = `
                <div class="flex items-center bg-brand-500 text-white rounded border border-brand-500 overflow-hidden h-9">
                    <button onclick="changeQty(${prod.id}, -1)" class="px-2.5 h-full hover:bg-brand-600 flex items-center justify-center transition-colors focus:outline-none" aria-label="Decrease quantity">
                        <i class="fa-solid fa-minus text-[10px]"></i>
                    </button>
                    <span class="px-2 text-xs font-black select-none">${newQty}</span>
                    <button onclick="changeQty(${prod.id}, 1)" class="px-2.5 h-full hover:bg-brand-600 flex items-center justify-center transition-colors focus:outline-none" aria-label="Increase quantity">
                        <i class="fa-solid fa-plus text-[10px]"></i>
                    </button>
                </div>
            `;
        }
        actionContainer.innerHTML = html;
    } else {
        renderProducts();
    }
}

function clearCart() {
    state.cart = {};
    updateCartUI();
    renderProducts();
}

function handleAreaChange(areaId) {
    state.deliveryAreaId = areaId;
    if (areaId && areaId !== "") {
        const area = AREAS.find(a => a.id == areaId);
        state.deliveryCharge = area ? parseFloat(area.delivery_charge) : 0;
    } else {
        state.deliveryCharge = 0;
    }
    updateCartUI();
}

function toggleCartDrawer() {
    state.isCartOpen = !state.isCartOpen;
    const drawer = document.getElementById('cartDrawer');
    const backdrop = document.getElementById('backdrop');

    if (state.isCartOpen) {
        goToStep(1); // Always start at Step 1 when opened
        drawer.classList.remove('translate-y-full');
        drawer.classList.add('translate-y-0');
        backdrop.classList.remove('opacity-0', 'pointer-events-none');
        backdrop.classList.add('opacity-100', 'pointer-events-auto');
    } else {
        drawer.classList.add('translate-y-full');
        drawer.classList.remove('translate-y-0');
        backdrop.classList.add('opacity-0', 'pointer-events-none');
        backdrop.classList.remove('opacity-100', 'pointer-events-auto');
    }
}

function updateCartUI() {
    try {
        localStorage.setItem('yukimart_cart', JSON.stringify(state.cart));
    } catch (e) {
        console.error("Failed to save cart to localStorage", e);
    }
    
    let totalItems = 0;
    let subtotal = 0;

    for (const [id, qty] of Object.entries(state.cart)) {
        const prod = PRODUCTS.find(p => p.id == id);
        if (prod) {
            totalItems += qty;
            subtotal += prod.price * qty;
        }
    }

    // Step 1 Subtotal
    const step1Subtotal = document.getElementById('step1Subtotal');
    if (step1Subtotal) step1Subtotal.textContent = `৳${subtotal}`;

    // Step 1 Footer total
    const step1TotalValue = document.getElementById('step1TotalValue');
    if (step1TotalValue) step1TotalValue.textContent = subtotal;

    // Recalculate Coupon Discount if coupon is active
    if (state.couponCode) {
        if (subtotal < state.couponMinOrder) {
            // Revoke coupon if subtotal falls below minimum order
            state.couponCode = null;
            state.couponDiscount = 0;
            state.couponDiscountType = null;
            state.couponDiscountValue = 0;
            state.couponMinOrder = 0;
            
            const appliedBanner = document.getElementById('applied-coupon-banner');
            if (appliedBanner) appliedBanner.classList.add('hidden');
            showToast("কুপন মুছে ফেলা হয়েছে: সাবটোটাল সর্বনিম্ন অর্ডার পরিমাণের নিচে।");
        } else {
            // Recalculate discount
            if (state.couponDiscountType === 'percentage') {
                state.couponDiscount = (subtotal * state.couponDiscountValue) / 100;
            } else {
                state.couponDiscount = state.couponDiscountValue;
            }
            if (state.couponDiscount > subtotal) {
                state.couponDiscount = subtotal;
            }
            
            // Update coupon banner description
            const desc = document.getElementById('applied-coupon-desc');
            if (desc) {
                desc.textContent = state.couponDiscountType === 'percentage'
                    ? `${state.couponDiscountValue}% ডিসকাউন্ট প্রয়োগ করা হয়েছে (-৳${state.couponDiscount.toFixed(2)})`
                    : `৳${state.couponDiscountValue} ফিক্সড ডিসকাউন্ট প্রয়োগ করা হয়েছে (-৳${state.couponDiscount.toFixed(2)})`;
            }
        }
    }

    const isFreeDelivery = state.freeDeliveryThreshold > 0 && (subtotal - state.couponDiscount) >= state.freeDeliveryThreshold;
    const activeDeliveryCharge = isFreeDelivery ? 0 : state.deliveryCharge;
    const grandTotal = Math.max(0, subtotal - state.couponDiscount + activeDeliveryCharge);

    // Step 2 Footer total
    const step2TotalValue = document.getElementById('step2TotalValue');
    if (step2TotalValue) step2TotalValue.textContent = grandTotal;

    const floatingCart = document.getElementById('floatingCart');
    if (floatingCart) {
        if (totalItems > 0) {
            floatingCart.classList.remove('translate-y-24');
            floatingCart.classList.add('translate-y-0');
        } else {
            floatingCart.classList.remove('translate-y-0');
            floatingCart.classList.add('translate-y-24');
            if (state.isCartOpen) toggleCartDrawer();
        }
    }

    const floatingCartCount = document.getElementById('floatingCartCount');
    if(floatingCartCount) floatingCartCount.textContent = totalItems;
    
    const floatingCartItemsText = document.getElementById('floatingCartItemsText');
    if(floatingCartItemsText) floatingCartItemsText.textContent = `${totalItems} টি আইটেম যোগ করা হয়েছে`;
    
    const floatingCartTotal = document.getElementById('floatingCartTotal');
    if(floatingCartTotal) floatingCartTotal.textContent = `৳${grandTotal}`;

    const desktopBadge = document.getElementById('desktopCartBadge');
    if (desktopBadge) {
        if (totalItems > 0) {
            desktopBadge.textContent = totalItems;
            desktopBadge.classList.remove('hidden');
        } else {
            desktopBadge.classList.add('hidden');
        }
    }

    const drawerCartCount = document.getElementById('drawerCartCount');
    if(drawerCartCount) drawerCartCount.textContent = totalItems;
    
    const summarySubtotal = document.getElementById('summarySubtotal');
    if(summarySubtotal) summarySubtotal.textContent = `৳${subtotal}`;
    
    // Coupon Discount Row
    const discountRow = document.getElementById('summaryDiscountRow');
    const discountVal = document.getElementById('summaryDiscount');
    if (discountRow && discountVal) {
        if (state.couponDiscount > 0) {
            discountVal.textContent = `-৳${state.couponDiscount.toFixed(2)}`;
            discountRow.classList.remove('hidden');
        } else {
            discountRow.classList.add('hidden');
        }
    }

    const summaryDeliveryCharge = document.getElementById('summaryDeliveryCharge');
    if (summaryDeliveryCharge) {
        if (isFreeDelivery && state.deliveryCharge > 0) {
            summaryDeliveryCharge.innerHTML = `<span class="line-through text-gray-400 mr-1.5">৳${state.deliveryCharge}</span><span class="text-emerald-600 font-extrabold animate-pulse">FREE</span>`;
        } else {
            summaryDeliveryCharge.textContent = `৳${state.deliveryCharge}`;
        }
    }
    
    const summaryGrandTotal = document.getElementById('summaryGrandTotal');
    if(summaryGrandTotal) summaryGrandTotal.textContent = `৳${grandTotal}`;
    
    const checkoutTotalValue = document.getElementById('checkoutTotalValue');
    if(checkoutTotalValue) checkoutTotalValue.textContent = grandTotal;

    updateDeliveryPromoBanner(subtotal, state.couponDiscount);
    renderCartDrawerItems();
}

function updateDeliveryPromoBanner(subtotal, discount = 0) {
    const banner = document.getElementById('delivery-promo-banner');
    const threshold = state.freeDeliveryThreshold;
    const spent = Math.max(0, subtotal - discount);
    const remaining = threshold - spent;
    const percent = threshold > 0 ? Math.min(100, (spent / threshold) * 100) : 0;

    // 1. Update Homepage Header Banner
    if (banner) {
        if (threshold <= 0) {
            // If free delivery is not configured, show default delivery speed banner
            banner.innerHTML = `
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
            `;
            banner.className = "bg-brand-50 border border-brand-100 text-brand-900 px-4 py-3 rounded mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-3 shadow-sm transition-all duration-300";
        } else if (remaining > 0) {
            banner.innerHTML = `
                <div class="flex-1 w-full">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-2">
                        <div class="flex items-center space-x-3 mb-1.5 sm:mb-0">
                            <div class="bg-brand-500 text-white p-1.5 rounded-sm flex items-center justify-center">
                                <i class="fa-solid fa-truck-fast text-xs"></i>
                            </div>
                            <div>
                                <p class="text-xs font-bold md:text-sm text-brand-900">
                                    আরো <span class="font-extrabold text-brand-600">৳${remaining.toFixed(0)}</span> যোগ করুন <span class="uppercase tracking-wide font-black text-brand-600">ফ্রি ডেলিভারি</span> এর জন্য!
                                </p>
                                <p class="text-[11px] text-brand-700">৳${threshold} এর বেশি অর্ডারে ফ্রি ডেলিভারি পান। আপনি ৳${spent.toFixed(0)} খরচ করেছেন।</p>
                            </div>
                        </div>
                        <span class="text-xs font-semibold bg-white border border-brand-200 px-2.5 py-1 text-brand-700 rounded-sm shrink-0 self-start sm:self-center">
                            লিমিট: ৳${threshold}
                        </span>
                    </div>
                    <!-- Progress Bar -->
                    <div class="w-full bg-brand-100/50 rounded-full h-2.5 overflow-hidden border border-brand-200">
                        <div class="bg-brand-500 h-full rounded-full transition-all duration-500 ease-out" style="width: ${percent}%"></div>
                    </div>
                </div>
            `;
            banner.className = "bg-brand-50 border border-brand-100 text-brand-900 px-4 py-3 rounded mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-3 shadow-sm transition-all duration-300";
        } else {
            banner.innerHTML = `
                <div class="flex-1 w-full">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-2">
                        <div class="flex items-center space-x-3 mb-1.5 sm:mb-0">
                            <div class="bg-emerald-500 text-white p-1.5 rounded-sm flex items-center justify-center animate-bounce">
                                <i class="fa-solid fa-gift text-xs"></i>
                            </div>
                            <div>
                                <p class="text-xs font-bold md:text-sm text-emerald-900">
                                    🎉 অভিনন্দন! আপনি <span class="uppercase tracking-wide font-black text-emerald-600">ফ্রি ডেলিভারি</span> আনলক করেছেন!
                                </p>
                                <p class="text-[11px] text-emerald-700">আপনার ৳${spent.toFixed(0)} টাকার অর্ডার ফ্রি শিপিং এর জন্য যোগ্য।</p>
                            </div>
                        </div>
                        <span class="text-xs font-semibold bg-white border border-emerald-200 px-2.5 py-1 text-emerald-700 rounded-sm shrink-0 self-start sm:self-center">
                            আনলকড!
                        </span>
                    </div>
                    <!-- Progress Bar at 100% -->
                    <div class="w-full bg-emerald-100 rounded-full h-2.5 overflow-hidden border border-emerald-200">
                        <div class="bg-emerald-500 h-full rounded-full w-full transition-all duration-500 ease-out"></div>
                    </div>
                </div>
            `;
            banner.className = "bg-emerald-50 border border-emerald-100 text-emerald-900 px-4 py-3 rounded mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-3 shadow-sm transition-all duration-300";
        }
    }

    // 2. Update Floating Basket Button Progress Bar
    const floatingProgressBar = document.getElementById('floatingCartProgressBar');
    if (floatingProgressBar) {
        if (threshold > 0) {
            floatingProgressBar.style.width = percent + '%';
            if (percent >= 100) {
                floatingProgressBar.className = "h-full bg-emerald-400 transition-all duration-500 ease-out";
            } else {
                floatingProgressBar.className = "h-full bg-brand-100 transition-all duration-500 ease-out";
            }
        } else {
            floatingProgressBar.style.width = '0%';
        }
    }

    // 3. Update Cart Drawer Top Promo Banner
    const drawerPromo = document.getElementById('drawer-delivery-promo');
    if (drawerPromo) {
        if (threshold > 0 && spent > 0) {
            drawerPromo.classList.remove('hidden');
            if (remaining > 0) {
                drawerPromo.innerHTML = `
                    <div class="bg-brand-50 border border-brand-100 text-brand-900 p-3 rounded mb-2 shadow-xs transition-all duration-300">
                        <div class="flex items-center justify-between mb-1.5">
                            <div class="flex items-center space-x-2">
                                <i class="fa-solid fa-truck-fast text-brand-500 text-xs"></i>
                                <span class="text-xs font-bold">ফ্রি ডেলিভারি প্রোগ্রেস</span>
                            </div>
                            <span class="text-[10px] font-semibold text-brand-700 bg-white border border-brand-200 px-1.5 py-0.5 rounded-sm">আরো ৳${remaining.toFixed(0)} যোগ করুন</span>
                        </div>
                        <div class="w-full bg-brand-100/50 rounded-full h-1.5 overflow-hidden">
                            <div class="bg-brand-505 h-full rounded-full bg-brand-500 transition-all duration-500 ease-out" style="width: ${percent}%"></div>
                        </div>
                    </div>
                `;
            } else {
                drawerPromo.innerHTML = `
                    <div class="bg-emerald-50 border border-emerald-100 text-emerald-900 p-3 rounded mb-2 shadow-xs transition-all duration-300">
                        <div class="flex items-center justify-between mb-1.5">
                            <div class="flex items-center space-x-2">
                                <i class="fa-solid fa-gift text-emerald-500 text-xs animate-bounce"></i>
                                <span class="text-xs font-bold text-emerald-850 text-emerald-800">🎉 ফ্রি ডেলিভারি আনলকড!</span>
                            </div>
                            <span class="text-[10px] font-bold text-emerald-700 bg-white border border-emerald-200 px-1.5 py-0.5 rounded-sm">ফ্রি</span>
                        </div>
                        <div class="w-full bg-emerald-100 rounded-full h-1.5 overflow-hidden">
                            <div class="bg-emerald-500 h-full rounded-full w-full transition-all duration-500 ease-out"></div>
                        </div>
                    </div>
                `;
            }
        } else {
            drawerPromo.innerHTML = "";
            drawerPromo.classList.add('hidden');
        }
    }
}

function renderCartDrawerItems() {
    const container = document.getElementById('drawerCartItems');
    const emptyState = document.getElementById('drawerEmptyState');
    if(!container || !emptyState) return;
    
    const cartEntries = Object.entries(state.cart);
    if (cartEntries.length === 0) {
        container.innerHTML = '';
        emptyState.classList.remove('hidden');
        return;
    }

    emptyState.classList.add('hidden');
    container.innerHTML = cartEntries.map(([id, qty]) => {
        const prod = PRODUCTS.find(p => p.id == id);
        if (!prod) return '';
        const itemTotal = prod.price * qty;
        
        return `
            <div class="flex items-center justify-between border-b border-gray-50 pb-3">
                <div class="flex items-center space-x-3 flex-1 min-w-0">
                    <img src="${prod.image}" alt="${prod.name}" class="w-12 h-12 rounded object-cover border border-gray-100 flex-shrink-0">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold text-gray-800 truncate">${prod.name}</p>
                        <p class="text-[10px] text-gray-400">৳${prod.price} &times; ${qty}</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center bg-gray-100 rounded border border-gray-200 overflow-hidden h-7">
                        <button onclick="changeQty(${prod.id}, -1)" class="px-2 h-full hover:bg-gray-200 flex items-center justify-center text-gray-600 transition-colors" aria-label="Decrease quantity">
                            <i class="fa-solid fa-minus text-[8px]"></i>
                        </button>
                        <span class="px-1.5 text-xs font-bold text-gray-800 select-none">${qty}</span>
                        <button onclick="changeQty(${prod.id}, 1)" class="px-2 h-full hover:bg-gray-200 flex items-center justify-center text-gray-600 transition-colors" aria-label="Increase quantity">
                            <i class="fa-solid fa-plus text-[8px]"></i>
                        </button>
                    </div>
                    <span class="text-xs font-extrabold text-gray-900 w-12 text-right">৳${itemTotal}</span>
                </div>
            </div>
        `;
    }).join('');
}

// Navigation & Stepped Flow
function goToStep(stepNum) {
    const totalItems = Object.values(state.cart).reduce((a, b) => a + b, 0);
    if (stepNum > 1 && totalItems === 0) {
        showToast("আপনার বাস্কেট খালি। শুরু করতে পণ্য যোগ করুন!");
        return;
    }
    
    if (stepNum === 3) {
        const form = document.getElementById('checkoutForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
    }
    
    state.checkoutStep = stepNum;
    
    // Toggle active classes on steps
    for (let i = 1; i <= 3; i++) {
        const pane = document.getElementById(`checkout-step-${i}`);
        const footer = document.getElementById(`footer-buttons-step-${i}`);
        const badge = document.getElementById(`step-badge-${i}`);
        const label = document.getElementById(`step-label-${i}`);
        
        if (pane) {
            if (i === stepNum) {
                pane.classList.remove('hidden');
            } else {
                pane.classList.add('hidden');
            }
        }
        
        if (footer) {
            if (i === stepNum) {
                footer.classList.remove('hidden');
            } else {
                footer.classList.add('hidden');
            }
        }
        
        // Progress badges styles
        if (badge && label) {
            if (i === stepNum) {
                badge.className = "w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-black bg-brand-500 text-white transition-all shadow-xs";
                badge.innerHTML = i;
                label.className = "text-xs font-black text-gray-900 transition-colors";
            } else if (i < stepNum) {
                badge.className = "w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-black bg-emerald-500 text-white transition-all shadow-xs";
                badge.innerHTML = `<i class="fa-solid fa-check text-[8px]"></i>`;
                label.className = "text-xs font-bold text-emerald-600 transition-colors";
            } else {
                badge.className = "w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold bg-gray-100 text-gray-400 transition-all";
                badge.innerHTML = i;
                label.className = "text-xs font-bold text-gray-400 transition-colors";
            }
        }
    }
    
    // Populate Review Step Summary
    if (stepNum === 3) {
        document.getElementById('reviewName').textContent = document.getElementById('customerName').value;
        document.getElementById('reviewPhone').textContent = document.getElementById('customerMobile').value;
        document.getElementById('reviewAddress').textContent = document.getElementById('deliveryAddress').value;
        
        const slotSelect = document.getElementById('deliverySlot');
        document.getElementById('reviewSlot').textContent = slotSelect.options[slotSelect.selectedIndex]?.text || '--';
    }
}

function validateAndGoToReview() {
    const form = document.getElementById('checkoutForm');
    if (form.checkValidity()) {
        goToStep(3);
    } else {
        form.reportValidity();
    }
}

// Coupon validation
async function applyCoupon() {
    const input = document.getElementById('couponInput');
    const code = input.value.trim();
    const errorDiv = document.getElementById('coupon-error');
    const errorMsg = document.getElementById('coupon-error-msg');
    const banner = document.getElementById('applied-coupon-banner');
    const applyBtn = document.getElementById('couponApplyBtn');
    
    errorDiv.classList.add('hidden');
    banner.classList.add('hidden');
    
    if (!code) {
        errorMsg.textContent = "অনুগ্রহ করে একটি কুপন কোড লিখুন।";
        errorDiv.classList.remove('hidden');
        return;
    }
    
    let subtotal = 0;
    for (const [id, qty] of Object.entries(state.cart)) {
        const prod = PRODUCTS.find(p => p.id == id);
        if (prod) {
            subtotal += prod.price * qty;
        }
    }
    
    applyBtn.disabled = true;
    applyBtn.innerHTML = `<i class="fa-solid fa-circle-notch animate-spin text-xs"></i>`;
    
    try {
        const res = await fetch('api/validate_coupon.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ coupon_code: code, subtotal: subtotal })
        });
        const data = await res.json();
        
        if (data.success) {
            state.couponCode = data.code;
            state.couponDiscount = parseFloat(data.discount);
            state.couponDiscountType = data.discount_type;
            state.couponDiscountValue = parseFloat(data.discount_value);
            state.couponMinOrder = parseFloat(data.min_order_amount || 0);
            
            document.getElementById('applied-coupon-code').textContent = data.code;
            document.getElementById('applied-coupon-desc').textContent = 
                data.discount_type === 'percentage' 
                ? `${data.discount_value}% ডিসকাউন্ট প্রয়োগ করা হয়েছে (-৳${state.couponDiscount.toFixed(2)})`
                : `৳${data.discount_value} ফিক্সড ডিসকাউন্ট প্রয়োগ করা হয়েছে (-৳${state.couponDiscount.toFixed(2)})`;
            
            banner.classList.remove('hidden');
            input.value = '';
            showToast("কুপন সফলভাবে প্রয়োগ করা হয়েছে!");
        } else {
            state.couponCode = null;
            state.couponDiscount = 0;
            state.couponDiscountType = null;
            state.couponDiscountValue = 0;
            state.couponMinOrder = 0;
            
            errorMsg.textContent = data.message;
            errorDiv.classList.remove('hidden');
        }
    } catch (err) {
        errorMsg.textContent = "কুপন যাচাই করা সম্ভব হয়নি। আবার চেষ্টা করুন।";
        errorDiv.classList.remove('hidden');
    } finally {
        applyBtn.disabled = false;
        applyBtn.textContent = "Apply";
        updateCartUI();
    }
}

function removeCoupon() {
    state.couponCode = null;
    state.couponDiscount = 0;
    state.couponDiscountType = null;
    state.couponDiscountValue = 0;
    state.couponMinOrder = 0;
    
    document.getElementById('couponInput').value = '';
    document.getElementById('applied-coupon-banner').classList.add('hidden');
    document.getElementById('coupon-error').classList.add('hidden');
    
    showToast("কুপন মুছে ফেলা হয়েছে।");
    updateCartUI();
}

async function handleCheckout() {
    const form = document.getElementById('checkoutForm');
    const submitBtn = document.getElementById('checkoutSubmitBtn');
    
    if (Object.keys(state.cart).length === 0) {
        showToast("আপনার বাস্কেট খালি। শুরু করতে পণ্য যোগ করুন!");
        return;
    }

    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    submitBtn.disabled = true;
    submitBtn.innerHTML = `
        <i class="fa-solid fa-circle-notch animate-spin text-sm"></i>
        <span>অর্ডার প্রসেস হচ্ছে...</span>
    `;

    const payload = {
        cart: state.cart,
        name: document.getElementById('customerName').value,
        phone: document.getElementById('customerMobile').value,
        address: document.getElementById('deliveryAddress').value,
        area_id: document.getElementById('deliveryArea').value,
        slot_id: document.getElementById('deliverySlot').value,
        coupon_code: state.couponCode
    };

    try {
        const res = await fetch('api/checkout.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        
        const data = await res.json();
        
        if (data.success) {
            document.getElementById('invoiceNo').textContent = `#ORD-${data.order_id}`;
            document.getElementById('invoiceName').textContent = payload.name;
            document.getElementById('invoicePhone').textContent = payload.phone;
            document.getElementById('invoiceAddress').textContent = payload.address;
            
            const selectSlot = document.getElementById('deliverySlot');
            document.getElementById('invoiceSlot').textContent = selectSlot.options[selectSlot.selectedIndex].text;

            let subtotal = 0;
            const itemsHtml = Object.entries(state.cart).map(([id, qty]) => {
                const prod = PRODUCTS.find(p => p.id == id);
                const cost = prod.price * qty;
                subtotal += cost;
                return `
                    <div class="flex justify-between text-gray-700">
                        <span>${prod.name} (x${qty})</span>
                        <span class="font-semibold">৳${cost}</span>
                    </div>
                `;
            }).join('');

            document.getElementById('invoiceItems').innerHTML = itemsHtml;
            document.getElementById('invoiceSubtotal').textContent = `৳${data.subtotal}`;
            
            const invoiceDiscountRow = document.getElementById('invoiceDiscountRow');
            if (invoiceDiscountRow) {
                if (data.discount > 0) {
                    document.getElementById('invoiceDiscount').textContent = `-৳${data.discount}`;
                    invoiceDiscountRow.classList.remove('hidden');
                } else {
                    invoiceDiscountRow.classList.add('hidden');
                }
            }

            document.getElementById('invoiceDelivery').textContent = `৳${data.delivery_charge}`;
            document.getElementById('invoiceGrandTotal').textContent = `৳${data.grand_total}`;

            toggleCartDrawer(); // Close Drawer
            const modal = document.getElementById('successModal');
            modal.classList.remove('opacity-0', 'pointer-events-none');
            modal.classList.add('opacity-100', 'pointer-events-auto');
        } else {
            showToast("Error: " + data.message);
        }
    } catch (err) {
        showToast("কিছু ভুল হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।");
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = `
            <span>Place Order (৳<span id="checkoutTotalValue">${document.getElementById('summaryGrandTotal').textContent.replace('৳','')}</span>)</span>
            <i class="fa-solid fa-arrow-right text-xs"></i>
        `;
    }
}

function closeSuccessModal() {
    const modal = document.getElementById('successModal');
    modal.classList.add('opacity-0', 'pointer-events-none');
    modal.classList.remove('opacity-100', 'pointer-events-auto');
    
    document.getElementById('checkoutForm').reset();
    state.deliveryAreaId = '';
    state.deliveryCharge = 0;
    
    state.couponCode = null;
    state.couponDiscount = 0;
    state.couponDiscountType = null;
    state.couponDiscountValue = 0;
    state.couponMinOrder = 0;
    
    const banner = document.getElementById('applied-coupon-banner');
    if (banner) banner.classList.add('hidden');
    const couponInput = document.getElementById('couponInput');
    if (couponInput) couponInput.value = '';
    const couponError = document.getElementById('coupon-error');
    if (couponError) couponError.classList.add('hidden');

    clearCart();
}

function showToast(message) {
    const toast = document.createElement('div');
    toast.className = "fixed bottom-24 left-1/2 -translate-x-1/2 z-50 bg-slate-900 text-white text-xs font-bold px-4 py-2.5 rounded shadow-lg flex items-center space-x-2 animate-scale-in";
    toast.innerHTML = `
        <i class="fa-solid fa-triangle-exclamation text-yellow-500"></i>
        <span>${message}</span>
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('opacity-0', 'transition-opacity', 'duration-300');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}
