(function () {
    const PRODUCTS_API_URL = '/api/products/get-all.php';
    const IMAGE_FALLBACK = 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=500';
    let allProductsCache = [];
    let searchBound = false;

    function formatCurrency(value) {
        return Number(value || 0).toLocaleString('vi-VN') + ' ₫';
    }

    function resolveImage(imageName) {
        if (!imageName) {
            return IMAGE_FALLBACK;
        }

        if (/^https?:\/\//i.test(imageName)) {
            return imageName;
        }

        return 'images/anh-san-pham/' + encodeURIComponent(String(imageName));
    }

    function normalizeProducts(payload) {
        if (!payload || !Array.isArray(payload.data)) {
            return [];
        }
        return payload.data;
    }

    function normalizeKeyword(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .trim();
    }

    async function fetchProducts() {
        const response = await fetch(PRODUCTS_API_URL);
        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }

        const json = await response.json();
        return normalizeProducts(json);
    }

    function clearRenderedProducts(container, template) {
        container.querySelectorAll('[data-product-item="1"]').forEach((node) => node.remove());

        if (template && template.parentElement !== container) {
            container.prepend(template);
        }
    }

    function renderProducts(products) {
        const container = document.getElementById('productList');
        const template = document.getElementById('productCardTemplate');

        if (!container || !template) {
            return;
        }

        clearRenderedProducts(container, template);

        const query = normalizeKeyword(document.getElementById('searchInput')?.value || '');
        const filteredProducts = query
            ? products.filter((item) => normalizeKeyword(item.product_name || '').includes(query))
            : products;

        if (filteredProducts.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'text-center text-muted w-100';
            empty.setAttribute('data-product-item', '1');
            empty.textContent = 'Tạm thời hết hàng.';
            container.appendChild(empty);
            return;
        }

        filteredProducts.forEach((product) => {
            const card = template.cloneNode(true);
            const productId = Number(product.product_id || 0);
            const detailId = Number(product.detail_id || 0);
            const stock = Number(product.total_stock || 0);
            const price = Number(product.price || 0);
            const image = resolveImage(product.image);
            const name = product.product_name || 'Sản phẩm';

            card.id = '';
            card.classList.remove('d-none');
            card.setAttribute('data-product-item', '1');

            const imgEl = card.querySelector('.js-product-img');
            const nameEl = card.querySelector('.js-product-name');
            const priceEl = card.querySelector('.js-product-price');
            const detailBtn = card.querySelector('.js-detail-btn');
            const buyBtn = card.querySelector('.js-buy-btn');

            if (imgEl) {
                imgEl.src = image;
                imgEl.alt = name;
                imgEl.onerror = function () {
                    if (this.dataset.fallbackTried !== '1') {
                        this.dataset.fallbackTried = '1';
                        this.src = 'images/' + encodeURIComponent(String(product.image || ''));
                        return;
                    }

                    this.onerror = null;
                    this.src = IMAGE_FALLBACK;
                };
                imgEl.onclick = function () {
                    if (typeof window.openDetailModal === 'function') {
                        window.openDetailModal(productId, detailId, name, price, image, stock);
                    }
                };
            }

            if (nameEl) {
                nameEl.textContent = name;
                nameEl.title = name;
            }

            if (priceEl) {
                priceEl.textContent = formatCurrency(price);
            }

            if (detailBtn) {
                detailBtn.onclick = function () {
                    if (typeof window.openDetailModal === 'function') {
                        window.openDetailModal(productId, detailId, name, price, image, stock);
                    }
                };
            }

            if (buyBtn) {
                buyBtn.onclick = function () {
                    if (typeof window.addToCart === 'function') {
                        window.addToCart(productId, detailId, name, price, image, 1, stock);
                    }
                };
            }

            container.appendChild(card);
        });
    }

    async function loadProducts() {
        const container = document.getElementById('productList');
        if (!container) {
            return;
        }

        try {
            allProductsCache = await fetchProducts();
            renderProducts(allProductsCache);
        } catch (error) {
            container.innerHTML = '<p class="text-danger text-center w-100">Lỗi kết nối API sản phẩm!</p>';
            console.error('Failed to load products:', error);
        }
    }

    function filterCachedProducts() {
        renderProducts(allProductsCache);
    }

    function bindSearchEvents() {
        if (searchBound) {
            return;
        }

        const searchInput = document.getElementById('searchInput');
        if (!searchInput) {
            return;
        }

        let timer = null;
        searchInput.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(filterCachedProducts, 180);
        });

        searchInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                clearTimeout(timer);
                filterCachedProducts();
            }
        });

        searchBound = true;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindSearchEvents);
    } else {
        bindSearchEvents();
    }

    window.ProductHome = {
        loadProducts,
    };
})();
