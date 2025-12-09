<?php
/**
 * Banners (Sets) List page.
 * Displays all created Banners in a grid view, provides search, creation, and deletion functionality.
 *
 * Terminology Mapping:
 * - UI "Баннер" (Banner)    <=> DB `mycompany_banner_set` (BannerSetTable)
 * - UI "Блок" (Block)      <=> DB `mycompany_banner` (BannerTable)
 */
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");

use Bitrix\Main\Loader;
use MyCompany\Banner\BannerSetTable;
use MyCompany\Banner\BannerTable;
use Bitrix\Main\Type\DateTime;

Loader::includeModule("mycompany.banner");
$APPLICATION->SetTitle("Управление баннерами");

// --- Data Fetching ---
// 1. Fetch all Banners (Sets)
$setsRaw = BannerSetTable::getList(['order' => ['ID' => 'DESC']]);
$setIds = [];
$sets = [];
$bannersBySet = []; // This will hold all blocks, grouped by banner ID, for the JS preview

while($row = $setsRaw->fetch()) {
    $row['BANNER_COUNT'] = 0; // Initialize block count
    
    // 2. For each Banner, fetch the preview image from its first block (slot)
    $firstBlock = BannerTable::getList([
        'filter' => ['SET_ID' => $row['ID'], 'SLOT_INDEX' => 1],
        'select' => ['IMAGE'],
        'limit' => 1
    ])->fetch();
    $row['PREVIEW_IMAGE'] = $firstBlock ? $firstBlock['IMAGE'] : null;

    $sets[$row['ID']] = $row;
    $setIds[] = $row['ID'];
}

// 3. Fetch all Blocks for all Banners on the page at once for the preview popup functionality.
if (!empty($setIds)) {
    $bannersRes = BannerTable::getList([
        'filter' => ['@SET_ID' => $setIds],
        'select' => ['ID', 'SET_ID', 'IMAGE', 'SORT', 'SLOT_INDEX', 'COLOR', 'IMG_SCALE', 'IMG_POS_X', 'IMG_POS_Y', 'TEXT_ALIGN', 'TEXT_COLOR', 'TITLE', 'SUBTITLE', 'TITLE_FONT_SIZE', 'SUBTITLE_FONT_SIZE', 'TITLE_BOLD', 'SUBTITLE_BOLD']
    ]);
    while ($banner = $bannersRes->fetch()) {
        if (isset($sets[$banner['SET_ID']])) {
            $bannersBySet[$banner['SET_ID']][] = $banner;
        }
    }
}

// 4. Final count for the stats panel
$totalSets = count($sets);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
?>

<style>
    /* Copied from user prompt */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        background: #f5f7fa; /* Simpler background for admin */
    }

    .container {
        max-width: 1600px;
        margin: 0 auto;
        padding: 20px;
    }

    /* Header */
    .page-header {
        background: white;
        border-radius: 12px;
        padding: 25px 30px;
        margin-bottom: 25px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
    }

    .page-title-section h1 {
        font-size: 24px;
        font-weight: 700;
        color: #333;
        margin: 0 0 5px 0;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .page-subtitle {
        color: #64748b;
        font-size: 14px;
        margin: 0;
    }

    .stats-bar {
        display: flex;
        gap: 20px;
        margin-top: 15px;
    }

    .stat-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        background: #f1f5f9;
        border-radius: 8px;
        font-size: 13px;
        color: #475569;
    }

    .stat-number {
        font-weight: 600;
        color: #3b82f6;
        font-size: 16px;
    }

    .header-actions {
        display: flex;
        gap: 12px;
    }

    /* Filter Bar */
    .filter-bar {
        display: flex;
        gap: 16px;
        align-items: center;
        margin-bottom: 24px;
    }

    .search-box {
        flex: 1;
        min-width: 300px;
        position: relative;
    }

    #searchSet {
        width: 100%;
        padding-left: 35px !important; /* Force padding */
    }

    .search-box::before {
        content: "🔍";
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 16px;
        opacity: 0.6;
    }

    /* Banner Grid */
    .sets-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 24px;
    }

    .set-card {
        background-color: white;
        background-size: cover;
        background-position: center;
        position: relative;
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid #e2e8f0;
        display: flex;
        flex-direction: column;
    }
    .card-preview-overlay {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: linear-gradient(180deg, rgba(255,255,255,0.6) 0%, rgba(255,255,255,0.9) 50%, white 100%);
        border-radius: 11px; /* slightly smaller to not cover the border */
    }
    .set-card:hover .card-preview-overlay {
        background: linear-gradient(180deg, rgba(255,255,255,0.4) 0%, rgba(255,255,255,0.8) 50%, white 100%);
    }

    .card-content {
        position: relative;
        z-index: 2;
        background: transparent !important;
        padding: 20px;
        flex-grow: 1;
        cursor: pointer;
    }

    .set-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        border-color: #3b82f6;
    }
    
    .card-content {
        padding: 20px;
        flex-grow: 1;
        cursor: pointer;
    }

    .card-header {
        display: flex;
        align-items: flex-start;
        gap: 15px;
        margin-bottom: 15px;
    }

    .card-icon {
        flex-shrink: 0;
        width: 44px;
        height: 44px;
        border-radius: 8px;
        background: #eef5ff;
        color: #3b82f6;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
    }
    
    .card-name {
        font-size: 17px;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 4px;
    }

    .card-meta {
        color: #94a3b8;
        font-size: 12px;
        font-weight: 500;
    }

    .card-stats {
        display: flex;
        gap: 16px;
        padding-top: 15px;
        border-top: 1px solid #f1f5f9;
    }

    .card-stat-item {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 13px;
        color: #64748b;
    }

    .card-actions {
        padding: 10px 15px;
        background: #f8fafc;
        border-top: 1px solid #eef2f9;
        display:flex;
        justify-content: flex-end;
    }
    
    .delete-btn {
        width: 32px;
        height: 32px;
        border: none;
        border-radius: 6px;
        background: #fef2f2;
        color: #ef4444;
        cursor: pointer;
        transition: all 0.2s;
        font-size: 16px;
    }
    .delete-btn:hover {
        background: #ef4444;
        color: white;
    }

    /* Preview Popup */
    #preview-popup {
        width: 500px; /* fixed width */
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.25);
        padding: 15px;
        z-index: 10050; /* High z-index for Bitrix */
        position: fixed;
        display: none;
        pointer-events: none; /* important */
        transition: opacity 0.15s ease-in-out;
    }

    #preview-crop {
        width: 100%;
        height: 320px;
        overflow: hidden;
        position: relative;
        background-color: #f0f2f5;
        border-radius: 8px;
    }

    #preview-grid {
        width: 1420px;
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        transform: scale(0.32); /* Scale down the large grid */
        transform-origin: top left;
        pointer-events: none;
    }

    #preview-grid .slot { background-color: #e9ecef; background-size: cover; background-position: center; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #adb5bd; font-size: 28px; font-weight: bold; position: relative; overflow: hidden; }
    #preview-grid .slot[data-i="1"], #preview-grid .slot[data-i="2"], #preview-grid .slot[data-i="3"], #preview-grid .slot[data-i="4"] { grid-column: span 2; height: 300px; }
    #preview-grid .slot[data-i="5"], #preview-grid .slot[data-i="6"], #preview-grid .slot[data-i="7"], #preview-grid .slot[data-i="8"] { grid-column: span 1; height: 200px; }
    
    #preview-grid .slot-content { display: flex; flex-direction: column; justify-content: center; width: 100%; height: 100%; padding: 25px; box-sizing: border-box; }
    #preview-grid .text-left { align-items: flex-start; text-align: left; }
    #preview-grid .text-center { align-items: center; text-align: center; }
    #preview-grid .text-right { align-items: flex-end; text-align: right; }
    #preview-grid .b-text-wrapper { padding: 10px 15px; border-radius: 4px; display: inline-block; }
    #preview-grid .b-title { font-weight: bold; }
    
    /* Create new set popup */
    #create-popup { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); z-index: 10100; align-items: center; justify-content: center; }
    #create-popup-content { background: #fff; padding: 25px; border-radius: 8px; width: 400px; box-shadow: 0 5px 20px rgba(0,0,0,0.2); }
</style>

<div class="container">
    <!-- Header -->
    <div class="page-header">
        <div class="page-title-section">
            <h1>🎨 Управление баннерами</h1>
            <p class="page-subtitle">Создавайте и редактируйте рекламные сетки для вашего сайта</p>
            <div class="stats-bar">
                <div class="stat-item">
                    <span>Всего баннеров:</span>
                    <span class="stat-number"><?= $totalSets ?></span>
                </div>
                <div class="stat-item">
                    <span>Общая конверсия:</span>
                    <span class="stat-number">?%</span>
                </div>
            </div>
        </div>
        <div class="header-actions">
            <button class="adm-btn" onclick="showLogs()">Показать/Очистить логи</button>
            <button class="adm-btn adm-btn-save" onclick="createSet()">➕ Создать баннер</button>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <div class="search-box">
            <input type="text" id="searchSet" class="adm-input" placeholder="Поиск по названию баннера...">
        </div>
    </div>

    <!-- Banners Grid -->
    <div class="sets-grid" id="setsGrid">
        <?php if (empty($sets)): ?>
            <p>Еще не создано ни одного баннера.</p>
        <?php else: ?>
            <?php foreach($sets as $set):
                $dateCreate = ($set['DATE_CREATE'] instanceof DateTime) ? $set['DATE_CREATE']->format('d.m.Y') : 'N/A';
                $previewStyle = $set['PREVIEW_IMAGE'] ? 'style="background-image: url(\\' . htmlspecialcharsbx($set['PREVIEW_IMAGE']) . '\\")"' : '';
            ?>
            <div class="set-card" data-set-id="<?= $set['ID'] ?>" data-set-name="<?= htmlspecialcharsbx($set['NAME']) ?>" <?= $previewStyle ?> onmouseenter="showPreview(<?=$set['ID']?>, this, event)" onmouseleave="hidePreview()">
                <div class="card-preview-overlay"></div>
                <div class="card-content" onclick="window.location='mycompany_banner_constructor.php?set_id=<?=$set['ID']?>&lang=<?=LANG?>'">
                    <div class="card-header">
                        <div class="card-icon">🖼️</div>
                        <div>
                            <div class="card-name"><?=htmlspecialcharsbx($set['NAME'])?></div>
                            <div class="card-meta">ID: <?=$set['ID']?> | Создан: <?= $dateCreate ?></div>
                        </div>
                    </div>
                    <div class="card-stats">
                        <div class="card-stat-item">👁️ <span>0</span> <small>(показы)</small></div>
                        <div class="card-stat-item">🎯 <span>0%</span> <small>(конверсия)</small></div>
                    </div>
                </div>
                <div class="card-actions">
                     <button title="Удалить баннер" class="delete-btn" onclick="deleteSet(<?= $set['ID'] ?>, '<?= CUtil::JSEscape($set['NAME']) ?>', event)">🗑️</button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Preview Popup -->
<div id="preview-popup"><div id="preview-crop"></div></div>

<!-- Create Popup -->
<div id="create-popup">
    <div id="create-popup-content">
        <h3 style="margin-top:0; margin-bottom:15px;">Новый баннер</h3>
        <div style="margin-bottom:15px;">
            <label style="display:block; margin-bottom:5px; font-weight:bold;">Название:</label>
            <input type="text" id="newSetName" class="adm-input" style="width:100%;" placeholder="Например: Акции на главной">
        </div>
        <div style="margin-bottom:20px;">
            <label><input type="checkbox" id="newSetAuto" checked> Заполнить демо-данными из категорий</label>
        </div>
        <div style="text-align:right; display:flex; gap:10px; justify-content:flex-end;">
            <button class="adm-btn" onclick="document.getElementById('create-popup').style.display='none'">Отмена</button>
            <button class="adm-btn adm-btn-save" id="doCreateBtn" onclick="doCreate()">Создать</button>
        </div>
    </div>
</div>

<script>
// This object holds all blocks for all banners, passed from PHP.
// It's used to build the on-hover preview without extra AJAX calls.
const bannersBySet = <?= CUtil::PhpToJSObject($bannersBySet) ?>;

const popup = document.getElementById('preview-popup');
const popupCrop = document.getElementById('preview-crop');

/**
 * Live search functionality. Filters banners by name.
 */
/**
 * Placeholder for showing debug logs.
 */
function showLogs() {
    alert('Логирование пока не реализовано.');
    // TODO: Implement actual log fetching and display logic
}

/**
 * Placeholder for copying logs.
 */
function copyLogs() {
    alert('Копирование логов пока не реализовано.');
    // TODO: Implement actual log copying logic
}

/**
 * Placeholder for clearing logs.
 */
function clearLogs() {
    alert('Очистка логов пока не реализована.');
    // TODO: Implement actual log clearing logic
}

document.getElementById('searchSet').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    document.querySelectorAll('.set-card').forEach(card => {
        const name = card.dataset.setName.toLowerCase();
        card.style.display = name.includes(searchTerm) ? 'flex' : 'none';
    });
});

/**
 * Deletes a banner (set) and all its blocks.
 * Uses a direct fetch call to the AJAX handler.
 * @param {number} id - The ID of the banner (set) to delete.
 * @param {string} name - The name for the confirmation message.
 * @param {Event} event - The click event.
 */
function deleteSet(id, name, event) {
    event.stopPropagation(); // Prevent card click

    const card = document.querySelector(`.set-card[data-set-id="${id}"]`);
    if (card) {
        card.style.opacity = '0.5'; // Visually indicate that an action is in progress
    }

    const fd = new FormData();
    fd.append('action', 'delete_set');
    fd.append('set_id', id);
    fd.append('sessid', '<?=bitrix_sessid()?>');

    fetch('mycompany_banner_ajax_save_banner.php', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            if (card) {
                // Animate card removal
                card.style.transition = 'opacity 0.3s ease, transform 0.3s ease, height 0.3s ease';
                card.style.opacity = '0';
                card.style.transform = 'scale(0.95)';
                card.style.minHeight = '0';
                card.style.height = card.offsetHeight + 'px';
                requestAnimationFrame(() => {
                    card.style.height = '0px';
                    card.style.margin = '0';
                    card.style.padding = '0';
                });

                setTimeout(() => card.remove(), 300);
            }
        } else {
            alert('Ошибка удаления: ' + (res.errors ? res.errors.join('\\n') : 'Неизвестная ошибка.'));
            if (card) {
                card.style.opacity = '1'; // Restore card on error
            }
        }
    }).catch(err => {
        alert('Сетевая ошибка при удалении.');
        console.error(err);
        if (card) {
            card.style.opacity = '1'; // Restore card on error
        }
    });
}

/**
 * Displays a popup with a miniature grid preview of a banner.
 * @param {number} setId - The ID of the banner to preview.
 * @param {HTMLElement} el - The card element being hovered.
 * @param {MouseEvent} event - The mouse event.
 */
let previewTimeout;
function showPreview(setId, el, event) {
    clearTimeout(previewTimeout);
    previewTimeout = setTimeout(() => {
        const blocks = bannersBySet[setId] || [];
        if (!popupCrop) return;
        popupCrop.innerHTML = ''; // Clear previous content

        const grid = document.createElement('div');
        grid.id = 'preview-grid';

        for (let i = 1; i <= 8; i++) {
            const b = blocks.find(block => block.SLOT_INDEX == i);
            const slot = document.createElement('div');
            slot.dataset.i = i;
            slot.className = 'slot';

            if (b) {
                slot.style.backgroundColor = b.COLOR || '#fff';
                if (b.IMAGE) {
                    slot.style.backgroundImage = `url(${b.IMAGE})`;
                    slot.style.backgroundSize = `${b.IMG_SCALE || 100}%`;
                    slot.style.backgroundPosition = `${b.IMG_POS_X || 50}% ${b.IMG_POS_Y || 50}%`;
                }
                
                const content = document.createElement('div');
                content.className = `slot-content text-${b.TEXT_ALIGN || 'center'}`;
                content.style.color = b.TEXT_COLOR || '#000';
            
                const wrapper = document.createElement('div');
                wrapper.className = 'b-text-wrapper';
                let innerHTML = '';
                let titleStyle = `font-size:${b.TITLE_FONT_SIZE || '22px'}; font-weight:${b.TITLE_BOLD === 'Y' ? 'bold' : 'normal'};`;
                let subStyle = `font-size:${b.SUBTITLE_FONT_SIZE || '14px'}; font-weight:${b.SUBTITLE_BOLD === 'Y' ? 'bold' : 'normal'};`;
                if(b.TITLE) innerHTML += `<div class="b-title" style="${titleStyle}">${b.TITLE}</div>`;
                if(b.SUBTITLE) innerHTML += `<div class="b-sub" style="${subStyle}">${b.SUBTITLE}</div>`;
                wrapper.innerHTML = innerHTML;
                content.appendChild(wrapper);
                slot.appendChild(content);

            } else {
                slot.innerHTML = `<span>${i}</span>`;
            }
            grid.appendChild(slot);
        }
        popupCrop.appendChild(grid);
        
        popup.style.display = 'block';
        popup.style.opacity = '0';

        // "Smart" positioning logic
        const popupWidth = 500;
        const isRightSide = (window.innerWidth - event.clientX) < (popupWidth + 20);

        let top = event.clientY + 15;
        let left = event.clientX + 15;

        if (isRightSide) {
            // Show on the left if not enough space on the right
            left = event.clientX - popupWidth - 15;
        }

        if (top + popup.offsetHeight > window.innerHeight) {
            // Adjust vertically if it overflows the bottom
            top = window.innerHeight - popup.offsetHeight - 10;
        }
        
        popup.style.top = top + 'px';
        popup.style.left = left + 'px';
        
        requestAnimationFrame(() => {
            popup.style.opacity = '1';
        });

    }, 100); 
}

/**
 * Hides the preview popup.
 */
function hidePreview() {
    clearTimeout(previewTimeout);
    popup.style.opacity = '0';
    setTimeout(() => {
        if (popup.style.opacity === '0') {
            popup.style.display = 'none';
        }
    }, 150);
}

/**
 * Shows the "Create new banner" popup.
 */
function createSet() {
    document.getElementById('create-popup').style.display = 'flex';
    document.getElementById('newSetName').focus();
}

/**
 * Handles the creation of a new banner via AJAX.
 */
function doCreate() {
    const btn = document.getElementById('doCreateBtn');
    const nameInput = document.getElementById('newSetName');
    if (!nameInput.value.trim()) {
        nameInput.style.borderColor = 'red';
        return;
    }
    nameInput.style.borderColor = '';

    btn.disabled = true;
    btn.textContent = 'Создание...';

    const fd = new FormData();
    fd.append('action', 'create_set');
    fd.append('name', nameInput.value);
    fd.append('category_mode', document.getElementById('newSetAuto').checked ? 'Y' : 'N');
    fd.append('sessid', '<?=bitrix_sessid()?>');

    fetch('mycompany_banner_ajax_save_banner.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if(res.success) {
                // Redirect to the constructor for the new banner
                window.location = 'mycompany_banner_constructor.php?set_id=' + res.id + '&lang=<?=LANG?>';
            } else {
                alert('Ошибка: ' + (res.errors ? res.errors.join('\\n') : 'Неизвестная ошибка.'));
                btn.disabled = false;
                btn.textContent = 'Создать';
            }
        }).catch(() => {
            alert('Сетевая ошибка при создании баннера.');
            btn.disabled = false;
            btn.textContent = 'Создать';
        });
}
</script>

<?php require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php"); ?>