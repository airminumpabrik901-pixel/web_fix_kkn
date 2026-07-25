<?php

try {
    $stmtUMKM = $koneksi->query("SELECT * FROM umkm ORDER BY id_produk DESC");
    $produk_umkm = $stmtUMKM->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $produk_umkm = [];
}
?>

<div class="container mt-2">
    <!-- Header Hero Banner -->
    <div class="bg-success text-white py-4 px-4 rounded-4 shadow-sm mb-4" style="background: linear-gradient(135deg, var(--emerald-primary) 0%, #115c3a 100%);">
        <h2 class="fw-bold mb-1"><i class="bi bi-shop me-2"></i>Dapur Lokal UMKM Bluto</h2>
        <p class="mb-0 text-white-50 small">Dukung Usaha Mikro Kecil dan Menengah (UMKM) Desa Bluto dengan membeli produk lokal berkualitas langsung dari pembuatnya.</p>
    </div>

    <!-- Filter & Search Controls -->
    <div class="row mb-4 g-3 align-items-center">
        <!-- Kategori Filter Pills -->
        <div class="col-md-7">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="fw-semibold text-muted small me-1"><i class="bi bi-funnel me-1"></i>Kategori:</span>
                <button type="button" class="btn btn-sm rounded-pill px-3 py-2 btn-success category-filter active" data-filter="all">
                    Semua Produk
                </button>
                <button type="button" class="btn btn-sm rounded-pill px-3 py-2 btn-outline-secondary bg-white text-dark category-filter" data-filter="makanan">
                    <i class="bi bi-egg-fried me-1 text-warning"></i> Makanan & Minuman
                </button>
                <button type="button" class="btn btn-sm rounded-pill px-3 py-2 btn-outline-secondary bg-white text-dark category-filter" data-filter="kerajinan">
                    <i class="bi bi-palette me-1 text-primary"></i> Kerajinan & Kriya
                </button>
            </div>
        </div>

        <!-- Search Input Bar -->
        <div class="col-md-5">
            <div class="input-group input-group-sm">
                <input type="text" id="search-input" class="form-control form-control-sm rounded-start-pill px-3 py-2" placeholder="Cari produk lokal UMKM...">
                <button class="btn btn-success rounded-end-pill px-3" id="search-btn" type="button">
                    <i class="bi bi-search me-1"></i> Cari
                </button>
            </div>
        </div>
    </div>

    <!-- Search Feedback Alert -->
    <div id="search-feedback" class="alert alert-light border border-success bg-white py-2 px-3 small rounded-3 mb-4 d-none">
        <i class="bi bi-info-circle text-success me-2"></i><span id="search-feedback-text"></span>
        <button type="button" id="reset-search-btn" class="btn btn-link text-danger text-decoration-none p-0 ms-2 small fw-bold">Reset Pencarian</button>
    </div>

    <!-- Empty State Alert -->
    <div id="no-products-alert" class="alert alert-light border text-center py-5 rounded-4 d-none">
        <i class="bi bi-basket3 text-muted fs-1 d-block mb-2"></i>
        <h6 class="fw-bold text-dark mb-1">Produk Tidak Ditemukan</h6>
        <p class="text-muted small mb-0">Tidak ada produk UMKM yang cocok dengan pencarian atau filter pilihan Anda.</p>
    </div>

    <!-- Product Grid List -->
    <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3 g-md-4" id="product-list">
        <?php foreach ($produk_umkm as $item): ?>
        <?php 
            $cat_class = 'makanan';
            if (stripos($item['kategori'], 'kriya') !== false || stripos($item['kategori'], 'kerajinan') !== false) {
                $cat_class = 'kerajinan';
            }
        ?>
        <div class="col product-card-col" data-name="<?= strtolower(htmlspecialchars($item['nama'])) ?>" data-category="<?= $cat_class ?>">
            <div class="card h-100 shadow-sm border-0 position-relative rounded-4 overflow-hidden">
                <span class="badge bg-warning text-dark position-absolute top-0 start-0 m-2 shadow-sm rounded-pill px-2 py-1">
                    <?= htmlspecialchars($item['kategori']) ?>
                </span>
                
                <?php
                    $gambarProduk = '';
                    if (!empty($item['gambar'])) {
                        $gambarProduk = preg_match('#^https?://#i', $item['gambar']) ? $item['gambar'] : 'assets/img/' . $item['gambar'];
                    } else {
                        $gambarProduk = 'assets/img/stickman-placeholder.svg';
                    }
                ?>
                <img src="<?= htmlspecialchars($gambarProduk) ?>" class="card-img-top object-fit-cover" alt="<?= htmlspecialchars($item['nama']) ?>" style="height: 180px;" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='assets/img/stickman-placeholder.svg';">
                
                <div class="card-body d-flex flex-column p-3">
                    <h6 class="card-title fw-bold mb-1 lh-sm text-dark" style="font-size: 14px; min-height: 34px;">
                        <?= htmlspecialchars($item['nama']) ?>
                    </h6>
                    <p class="text-success fw-bold mb-2">Rp <?= number_format($item['harga'], 0, ',', '.') ?></p>
                    
                    <p class="card-text text-muted small mb-3 mt-auto">
                        <i class="bi bi-person-fill text-secondary me-1"></i><?= htmlspecialchars($item['penjual']) ?>
                    </p>
                    
                    <?php 
                        $pesan_wa = urlencode("Halo {$item['penjual']}, saya ingin memesan produk: {$item['nama']} yang ada di Website Desa.");
                    ?>
                    <a href="https://wa.me/<?= htmlspecialchars($item['no_wa']) ?>?text=<?= $pesan_wa ?>" target="_blank" class="btn btn-success btn-sm w-100 rounded-pill shadow-sm">
                        <i class="bi bi-whatsapp me-1"></i> Pesan
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination Container -->
    <div id="pagination-container" class="row mt-4 mb-5"></div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const searchInput = document.getElementById('search-input');
    const searchBtn = document.getElementById('search-btn');
    const resetSearchBtn = document.getElementById('reset-search-btn');
    const searchFeedback = document.getElementById('search-feedback');
    const searchFeedbackText = document.getElementById('search-feedback-text');
    const noProductsAlert = document.getElementById('no-products-alert');

    const filters = document.querySelectorAll('.category-filter');
    const products = document.querySelectorAll('.product-card-col');

    let activeFilter = 'all';
    let searchQuery = '';
    let currentPage = 1;
    const itemsPerPage = 10;

    function filterProducts() {
        const matchingProducts = [];
        
        products.forEach(p => {
            const name = p.getAttribute('data-name');
            const category = p.getAttribute('data-category');

            const matchesSearch = !searchQuery || name.includes(searchQuery);
            const matchesFilter = activeFilter === 'all' || category === activeFilter;

            if (matchesSearch && matchesFilter) {
                matchingProducts.push(p);
            } else {
                p.style.display = 'none';
            }
        });

        // Toggle search feedback banner
        if (searchQuery !== '') {
            searchFeedbackText.innerHTML = `Menampilkan hasil pencarian untuk kata kunci: <strong>"${escapeHtml(searchQuery)}"</strong>`;
            searchFeedback.classList.remove('d-none');
        } else {
            searchFeedback.classList.add('d-none');
        }

        // Toggle empty state alert
        if (matchingProducts.length === 0) {
            noProductsAlert.classList.remove('d-none');
        } else {
            noProductsAlert.classList.add('d-none');
        }

        const totalItems = matchingProducts.length;
        const totalPages = Math.ceil(totalItems / itemsPerPage);
        
        if (currentPage > totalPages) currentPage = 1;
        if (currentPage < 1) currentPage = 1;

        matchingProducts.forEach((p, idx) => {
            const startIdx = (currentPage - 1) * itemsPerPage;
            const endIdx = startIdx + itemsPerPage;
            if (idx >= startIdx && idx < endIdx) {
                p.style.display = 'block';
            } else {
                p.style.display = 'none';
            }
        });

        renderPagination(totalPages);
    }

    function renderPagination(totalPages) {
        const container = document.getElementById('pagination-container');
        if (!container) return;
        container.innerHTML = '';

        if (totalPages <= 1) return;

        const nav = document.createElement('nav');
        nav.className = 'w-100';
        const ul = document.createElement('ul');
        ul.className = 'pagination justify-content-center mb-0';

        const prevLi = document.createElement('li');
        prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
        prevLi.innerHTML = `<a class="page-link rounded-start-pill px-3 text-success border-success-subtle" href="#" aria-label="Previous"><i class="bi bi-chevron-left"></i></a>`;
        if (currentPage > 1) {
            prevLi.addEventListener('click', function(e) {
                e.preventDefault();
                currentPage--;
                filterProducts();
            });
        }
        ul.appendChild(prevLi);

        for (let i = 1; i <= totalPages; i++) {
            const li = document.createElement('li');
            li.className = `page-item ${currentPage === i ? 'active' : ''}`;
            const a = document.createElement('a');
            a.className = `page-link px-3 ${currentPage === i ? 'bg-success border-success text-white' : 'text-success border-success-subtle'}`;
            a.href = '#';
            a.textContent = i;
            a.addEventListener('click', function(e) {
                e.preventDefault();
                currentPage = i;
                filterProducts();
            });
            li.appendChild(a);
            ul.appendChild(li);
        }

        const nextLi = document.createElement('li');
        nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
        nextLi.innerHTML = `<a class="page-link rounded-end-pill px-3 text-success border-success-subtle" href="#" aria-label="Next"><i class="bi bi-chevron-right"></i></a>`;
        if (currentPage < totalPages) {
            nextLi.addEventListener('click', function(e) {
                e.preventDefault();
                currentPage++;
                filterProducts();
            });
        }
        ul.appendChild(nextLi);

        nav.appendChild(ul);
        container.appendChild(nav);
    }

    function escapeHtml(text) {
        return text.replace(/[&<>"']/g, function(m) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[m];
        });
    }

    searchInput.addEventListener('input', function(e) {
        searchQuery = e.target.value.toLowerCase().trim();
        currentPage = 1;
        filterProducts();
    });

    searchBtn.addEventListener('click', function() {
        searchQuery = searchInput.value.toLowerCase().trim();
        currentPage = 1;
        filterProducts();
    });

    if (resetSearchBtn) {
        resetSearchBtn.addEventListener('click', function() {
            searchInput.value = '';
            searchQuery = '';
            currentPage = 1;
            filterProducts();
        });
    }

    filters.forEach(btn => {
        btn.addEventListener('click', function() {
            filters.forEach(f => {
                f.classList.remove('btn-success', 'active');
                f.classList.add('btn-outline-secondary', 'bg-white', 'text-dark');
            });
            this.classList.add('btn-success', 'active');
            this.classList.remove('btn-outline-secondary', 'bg-white', 'text-dark');

            activeFilter = this.getAttribute('data-filter');
            currentPage = 1;
            filterProducts();
        });
    });

    // Initialize list with pagination
    filterProducts();
});
</script>
