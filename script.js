let apksData = [];
let currentPage = 1;
const itemsPerPage = 9;

// Elementos DOM
const searchInput = document.getElementById('searchInput');
const searchBox = document.getElementById('searchBox');
const searchToggle = document.getElementById('searchToggle');
const sortSelect = document.getElementById('sortSelect');
const apkGrid = document.getElementById('apkGrid');
const resultsInfo = document.getElementById('resultsInfo');
const paginationControls = document.getElementById('paginationControls');
const modal = document.getElementById('modal');
const modalBody = document.getElementById('modalBody');
const closeModalBtn = document.getElementById('closeModal');

// Elementos del modal de imagen
const imageModal = document.getElementById('imageModal');
const imageModalContent = document.getElementById('imageModalContent');
const imageModalClose = document.getElementById('imageModalClose');

// Elementos de soporte
const supportModal = document.getElementById('supportModal');
const closeSupportModal = document.getElementById('closeSupportModal');
const supportForm = document.getElementById('supportForm');
const supportResponse = document.getElementById('supportResponse');

// Elementos de menú
const menuToggle = document.getElementById('menuToggle');
const sideMenu = document.getElementById('sideMenu');
const closeMenu = document.getElementById('closeMenu');
const overlay = document.getElementById('overlay');

// Cargar APKs desde JSON
fetch('data.json')
    .then(response => response.json())
    .then(data => {
        apksData = data;
        loadDownloadsFromFirebase();
    })
    .catch(error => {
        console.error('Error al cargar los datos:', error);
        apkGrid.innerHTML = `<div class="no-results">❌ Error al cargar las aplicaciones</div>`;
    });

// ============ FUNCIONES DE FIREBASE ============

function loadDownloadsFromFirebase() {
    db.collection('apks').get().then((snapshot) => {
        snapshot.forEach((doc) => {
            const data = doc.data();
            const apk = apksData.find(a => a.id === parseInt(doc.id));
            if (apk) {
                apk.downloads = data.downloads || 0;
            }
        });
        renderApks();
    }).catch((error) => {
        console.error('Error al cargar descargas:', error);
        renderApks();
    });
}

function saveDownloadToFirebase(id, downloads) {
    db.collection('apks').doc(id.toString()).set({
        downloads: downloads
    }).catch((error) => {
        console.error('Error al guardar descarga:', error);
    });
}

function saveMessageToFirebase(message) {
    return db.collection('messages').add(message);
}

// ============ FUNCIONES DE RENDERIZADO ============

function getFilteredAndSorted() {
    const query = searchInput.value.toLowerCase().trim();
    let filtered = apksData.filter(apk =>
        apk.name.toLowerCase().includes(query)
    );
    const sortBy = sortSelect.value;
    filtered.sort((a, b) => {
        switch (sortBy) {
            case 'name': return a.name.localeCompare(b.name);
            case 'date': return new Date(b.date) - new Date(a.date);
            case 'size': {
                const toBytes = (str) => parseFloat(str) * (str.includes('MB') ? 1 : 0.001);
                return toBytes(a.size) - toBytes(b.size);
            }
            case 'downloads': return (b.downloads || 0) - (a.downloads || 0);
            default: return 0;
        }
    });
    return filtered;
}

function renderApks() {
    const filtered = getFilteredAndSorted();
    const totalPages = Math.ceil(filtered.length / itemsPerPage) || 1;
    if (currentPage > totalPages) currentPage = totalPages;
    const start = (currentPage - 1) * itemsPerPage;
    const end = Math.min(start + itemsPerPage, filtered.length);
    const pageItems = filtered.slice(start, end);

    if (pageItems.length === 0) {
        apkGrid.innerHTML = `<div class="no-results">🔍 No hay APKs que coincidan con tu búsqueda</div>`;
        updateResultsInfo(0, 0, 0);
        updatePaginationControls(0);
        return;
    }

    apkGrid.innerHTML = pageItems.map(apk => createApkCard(apk)).join('');
    updateResultsInfo(filtered.length, start, end);
    updatePaginationControls(totalPages);
}

function createApkCard(apk) {
    const iconHtml = apk.icon
        ? `<img src="${apk.icon}" alt="${apk.name}" class="icon" onclick="openImageModal(event, '${apk.icon}')" style="cursor:pointer;" />`
        : `<div class="icon" onclick="openImageModal(event, '')">${apk.name.charAt(0).toUpperCase()}</div>`;
    return `
        <div class="apk-card" data-id="${apk.id}" onclick="openModal(${apk.id})">
            ${iconHtml}
            <h3>${apk.name}</h3>
            <div class="version">v${apk.version}</div>
            <div class="meta">
                <span>📦 ${apk.size}</span>
                <span>📅 ${formatDate(apk.date)}</span>
            </div>
            <div class="download-badge">⬇️ ${apk.downloads || 0} descargas</div>
        </div>
    `;
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('es-ES', { day: 'numeric', month: 'short', year: 'numeric' });
}

function updateResultsInfo(total, start, end) {
    if (total === 0) {
        resultsInfo.textContent = 'No se encontraron resultados';
        return;
    }
    const shown = Math.min(end, total);
    resultsInfo.textContent = `Mostrando ${start + 1}-${shown} de ${total} resultados`;
}

function updatePaginationControls(totalPages) {
    if (totalPages <= 1) {
        paginationControls.innerHTML = '';
        return;
    }
    let html = '';
    html += `<button class="nav-btn" onclick="goToPage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>&#8249; Anterior</button>`;
    const maxVisible = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
    let endPage = Math.min(totalPages, startPage + maxVisible - 1);
    if (endPage - startPage < maxVisible - 1) {
        startPage = Math.max(1, endPage - maxVisible + 1);
    }
    for (let i = startPage; i <= endPage; i++) {
        html += `<button class="${i === currentPage ? 'active' : ''}" onclick="goToPage(${i})">${i}</button>`;
    }
    html += `<button class="nav-btn" onclick="goToPage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>Siguiente &#8250;</button>`;
    paginationControls.innerHTML = html;
}

function goToPage(page) {
    const filtered = getFilteredAndSorted();
    const totalPages = Math.ceil(filtered.length / itemsPerPage) || 1;
    if (page < 1 || page > totalPages) return;
    currentPage = page;
    renderApks();
    apkGrid.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function resetFilters() {
    currentPage = 1;
    renderApks();
}

// Event listeners
searchInput.addEventListener('input', resetFilters);
sortSelect.addEventListener('change', resetFilters);

// Toggle búsqueda
searchToggle.addEventListener('click', function(e) {
    e.stopPropagation();
    searchBox.classList.toggle('active');
    if (searchBox.classList.contains('active')) {
        searchInput.focus();
    }
});

document.addEventListener('click', function(e) {
    if (!searchBox.contains(e.target) && e.target !== searchToggle) {
        searchBox.classList.remove('active');
    }
});

// Menú lateral
function openMenu() {
    sideMenu.classList.add('open');
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeMenuFunc() {
    sideMenu.classList.remove('open');
    overlay.classList.remove('active');
    document.body.style.overflow = 'auto';
}

menuToggle.addEventListener('click', openMenu);
closeMenu.addEventListener('click', closeMenuFunc);
overlay.addEventListener('click', closeMenuFunc);

document.getElementById('menuSearch').addEventListener('click', function(e) {
    e.preventDefault();
    closeMenuFunc();
    searchBox.classList.add('active');
    searchInput.focus();
});

document.getElementById('menuSupport').addEventListener('click', function(e) {
    e.preventDefault();
    closeMenuFunc();
    openSupportModal();
});

document.getElementById('menuInfo').addEventListener('click', function(e) {
    e.preventDefault();
    closeMenuFunc();
    alert('YWEBAPK - Tu web de descargas de aplicaciones Android.\nVersión 1.0\nDesarrollada con ❤️');
});

document.getElementById('menuContact').addEventListener('click', function(e) {
    e.preventDefault();
    closeMenuFunc();
    alert('Contáctanos: ywebapk@example.com');
});

// ============ MODAL DE IMAGEN A PANTALLA COMPLETA ============

function openImageModal(event, imageUrl) {
    event.stopPropagation();
    if (!imageUrl) {
        alert('No hay imagen disponible');
        return;
    }
    imageModalContent.src = imageUrl;
    imageModal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeImageModal() {
    imageModal.style.display = 'none';
    document.body.style.overflow = 'auto';
}

imageModalClose.addEventListener('click', closeImageModal);
imageModal.addEventListener('click', function(e) {
    if (e.target === imageModal) closeImageModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeImageModal();
});

// ============ MODAL DE APK ============

function openModal(id) {
    const apk = apksData.find(item => item.id === id);
    if (!apk) return;

    const iconHtml = apk.icon
        ? `<img src="${apk.icon}" alt="${apk.name}" class="modal-icon" onclick="openImageModal(event, '${apk.icon}')" style="cursor:pointer;" />`
        : `<div class="modal-icon">${apk.name.charAt(0).toUpperCase()}</div>`;

    let screenshotsHtml = '';
    if (apk.screenshots && apk.screenshots.length > 0) {
        screenshotsHtml = `<div class="screenshots-container">`;
        apk.screenshots.forEach(url => {
            screenshotsHtml += `<img src="${url}" alt="Captura de ${apk.name}" loading="lazy" onclick="openImageModal(event, '${url}')" style="cursor:pointer;" />`;
        });
        screenshotsHtml += `</div>`;
    }

    modalBody.innerHTML = `
        ${iconHtml}
        <h2>${apk.name}</h2>
        <div class="version">Versión ${apk.version} · ${apk.size}</div>
        ${screenshotsHtml}
        <div class="details">
            <p><strong>📝 Descripción:</strong><br>${apk.description || 'Sin descripción'}</p>
            <p><strong>📱 Requisitos:</strong> ${apk.requirements || 'No especificados'}</p>
            <p><strong>🔄 Novedades:</strong><br>${apk.changes || 'Sin información'}</p>
            <p><strong>📅 Fecha:</strong> ${formatDate(apk.date)}</p>
            <p><strong>⬇️ Descargas:</strong> <span id="downloadCountDisplay">${apk.downloads || 0}</span></p>
        </div>
        <button class="download-btn" id="downloadBtn" data-id="${apk.id}">Descargar APK</button>
    `;

    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    const downloadBtn = document.getElementById('downloadBtn');
    downloadBtn.addEventListener('click', function(e) {
        e.preventDefault();
        const id = parseInt(this.dataset.id);
        incrementDownload(id);
        const apkItem = apksData.find(a => a.id === id);
        if (apkItem && apkItem.downloadUrl && apkItem.downloadUrl !== '#') {
            window.open(apkItem.downloadUrl, '_blank');
        } else {
            alert('Enlace de descarga no disponible.');
        }
    });
}

function closeModal() {
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
}

closeModalBtn.addEventListener('click', closeModal);
modal.addEventListener('click', (e) => {
    if (e.target === modal) closeModal();
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeModal();
});

// ============ DESCARGA REAL CON FIREBASE ============

function incrementDownload(id) {
    const apk = apksData.find(a => a.id === id);
    if (!apk) return;
    apk.downloads = (apk.downloads || 0) + 1;
    
    saveDownloadToFirebase(id, apk.downloads);

    const display = document.getElementById('downloadCountDisplay');
    if (display) {
        display.textContent = apk.downloads;
    }

    const card = document.querySelector(`.apk-card[data-id="${id}"]`);
    if (card) {
        const badge = card.querySelector('.download-badge');
        if (badge) {
            badge.textContent = `⬇️ ${apk.downloads} descargas`;
        }
    }

    if (sortSelect.value === 'downloads') {
        renderApks();
    }
}

// ============ SOPORTE CON FIREBASE ============

function openSupportModal() {
    supportModal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    supportResponse.style.display = 'none';
    supportForm.reset();
}

closeSupportModal.addEventListener('click', function() {
    supportModal.style.display = 'none';
    document.body.style.overflow = 'auto';
});

supportModal.addEventListener('click', (e) => {
    if (e.target === supportModal) {
        supportModal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
});

supportForm.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const name = document.getElementById('userName').value.trim();
    const email = document.getElementById('userEmail').value.trim();
    const message = document.getElementById('userMessage').value.trim();

    if (!name || !email || !message) {
        supportResponse.style.display = 'block';
        supportResponse.innerHTML = '<div style="color: #ef4444;">⚠️ Por favor, completa todos los campos obligatorios.</div>';
        return;
    }

    const newMessage = {
        name: name,
        email: email,
        message: message,
        date: new Date().toISOString(),
        read: false,
        replies: []
    };

    saveMessageToFirebase(newMessage)
        .then(() => {
            supportResponse.style.display = 'block';
            supportResponse.innerHTML = `
                <div style="color: #10b981; padding: 1rem; background: rgba(16, 185, 129, 0.1); border-radius: 8px;">
                    ✅ ¡Mensaje enviado con éxito! Te responderemos lo antes posible.
                </div>
            `;
            supportForm.reset();
            setTimeout(() => {
                supportModal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }, 3000);
        })
        .catch((error) => {
            console.error('Error al enviar mensaje:', error);
            supportResponse.style.display = 'block';
            supportResponse.innerHTML = '<div style="color: #ef4444;">❌ Error al enviar el mensaje. Intenta de nuevo.</div>';
        });
});