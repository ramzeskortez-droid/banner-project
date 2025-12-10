<?php
/**
 * Banners (Sets) List page with SPA Constructor.
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
$allBanners = []; // To hold all blocks for all sets

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

// 3. Fetch all Blocks for all Banners on the page at once for the SPA constructor functionality.
if (!empty($setIds)) {
    $bannersRes = BannerTable::getList([
        'filter' => ['@SET_ID' => $setIds],
        'select' => [
            'ID', 'SET_ID', 'SLOT_INDEX', 'COLOR', 'IMAGE', 'IMG_SCALE', 'IMG_POS_X', 'IMG_POS_Y',
            'TEXT_ALIGN', 'TEXT_COLOR', 'TITLE', 'SUBTITLE', 'TITLE_FONT_SIZE', 'SUBTITLE_FONT_SIZE',
            'TITLE_BOLD', 'TITLE_ITALIC', 'TITLE_UNDERLINE',
            'SUBTITLE_BOLD', 'SUBTITLE_ITALIC', 'SUBTITLE_UNDERLINE',
            'LINK'
        ]
    ]);
    while ($banner = $bannersRes->fetch()) {
        if (isset($sets[$banner['SET_ID']])) {
            $allBanners[$banner['SET_ID']][] = $banner;
        }
    }
}

// 4. Final count for the stats panel
$totalSets = count($sets);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
?>

<style>
    /* Existing styles for the List View */
    .container * {
        box-sizing: border-box; /* Scope strictly to module container */
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


    /* Scoped CSS for #view-editor (SPA Constructor Popup) */
    #view-editor * {
        box-sizing: border-box; 
    }

    #view-editor {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5); /* Overlay background */
        z-index: 10100; /* High z-index to ensure it's on top */
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 20px;
    }

    #view-editor .popup-overlay {
        font-family: Arial, sans-serif; /* Moved from global body */
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        max-width: 1400px;
        width: 100%;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
    }

    #view-editor .popup-header {
        background: #2c3e50;
        color: white;
        padding: 15px 20px;
        border-radius: 8px 8px 0 0;
        font-size: 16px;
        font-weight: bold;
    }

    #view-editor .popup-content {
        display: flex;
        flex: 1;
        overflow: hidden;
    }

    #view-editor .left-panel {
        width: 450px;
        border-right: 1px solid #ddd;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    #view-editor .selected-block-preview {
        background: #f5f5f5;
        padding: 15px;
        border-bottom: 2px solid #ddd;
        min-height: 220px;
    }

    #view-editor .preview-title {
        font-size: 13px;
        font-weight: bold;
        margin-bottom: 10px;
        color: #333;
    }

    #view-editor .block-preview-container {
        background: white;
        border: 2px solid #3498db;
        border-radius: 4px;
        padding: 10px;
        height: 170px;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        overflow: hidden;
    }

    #view-editor .block-preview-content {
        text-align: center;
        max-width: 90%;
    }

    #view-editor .block-preview-image {
        width: 100%;
        height: 120px;
        object-fit: cover;
        border-radius: 4px;
        margin-bottom: 8px;
    }

    #view-editor .block-preview-title {
        font-size: 14px;
        font-weight: bold;
        color: #333;
        margin-bottom: 4px;
    }

    #view-editor .block-preview-text {
        font-size: 11px;
        color: #666;
        line-height: 1.3;
    }

    #view-editor .settings-panel {
        flex: 1;
        padding: 20px;
        overflow-y: auto;
        background: #f9f9f9;
    }

    #view-editor .right-panel {
        flex: 1;
        padding: 20px;
        display: flex;
        justify-content: center;
        align-items: center;
        background: #e8e8e8;
        overflow: auto;
    }

    #view-editor .banner-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
        max-width: 900px;
        width: 100%;
    }

    #view-editor .banner-block {
        background: white;
        border: 3px solid transparent;
        border-radius: 6px;
        padding: 12px;
        cursor: pointer;
        transition: all 0.2s;
        position: relative;
        min-height: 180px;
        display: flex;
        flex-direction: column;
    }

    #view-editor .banner-block:hover {
        border-color: #95a5a6;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    #view-editor .banner-block.selected {
        border-color: #3498db;
        box-shadow: 0 4px 16px rgba(52, 152, 219, 0.3);
    }

    #view-editor .block-image {
        width: 100%;
        height: 120px;
        object-fit: cover;
        border-radius: 4px;
        margin-bottom: 10px;
    }

    #view-editor .block-title {
        font-size: 14px;
        font-weight: bold;
        color: #333;
        margin-bottom: 6px;
        line-height: 1.3;
    }

    #view-editor .block-text {
        font-size: 11px;
        color: #666;
        line-height: 1.4;
        flex: 1;
    }

    #view-editor .section-title {
        font-size: 14px;
        font-weight: bold;
        margin-bottom: 15px;
        color: #333;
    }

    #view-editor .settings-group {
        background: white;
        padding: 15px;
        border-radius: 6px;
        margin-bottom: 15px;
        border: 1px solid #ddd;
    }

    #view-editor .control-row {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 12px;
    }

    #view-editor .control-row:last-child {
        margin-bottom: 0;
    }

    #view-editor .control-row label {
        font-size: 13px;
        color: #555;
        min-width: 120px;
    }

    #view-editor .control-row input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }

    #view-editor .slider-container {
        flex: 1;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    #view-editor input[type="range"] {
        flex: 1;
        height: 6px;
        border-radius: 3px;
        background: #ddd;
        outline: none;
        cursor: pointer;
    }

    #view-editor input[type="range"]::-webkit-slider-thumb {
        -webkit-appearance: none;
        appearance: none;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: #3498db;
        cursor: pointer;
    }

    #view-editor input[type="range"]::-moz-range-thumb {
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: #3498db;
        cursor: pointer;
        border: none;
    }

    #view-editor input[type="number"] {
        width: 60px;
        padding: 5px 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 13px;
    }

    #view-editor .quick-edit-section {
        background: #fff9e6;
        padding: 15px;
        border-radius: 6px;
        margin-bottom: 15px;
        border: 1px solid #ffd966;
    }

    #view-editor .quick-edit-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }

    #view-editor .quick-edit-title {
        font-size: 13px;
        font-weight: bold;
        color: #333;
    }

    #view-editor .format-buttons {
        display: flex;
        gap: 5px;
    }

    #view-editor .format-btn {
        padding: 4px 10px;
        border: 1px solid #ddd;
        background: white;
        border-radius: 3px;
        cursor: pointer;
        font-size: 12px;
        font-weight: bold;
    }

    #view-editor .format-btn:hover {
        background: #f0f0f0;
    }

    #view-editor .format-btn.active {
        background: #3498db;
        color: white;
        border-color: #3498db;
    }

    #view-editor .text-controls {
        display: flex;
        gap: 10px;
        margin-bottom: 10px;
        flex-wrap: wrap;
    }

    #view-editor .text-control-group {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
    }

    #view-editor .color-picker {
        width: 40px;
        height: 28px;
        border: 1px solid #ddd;
        border-radius: 3px;
        cursor: pointer;
    }

    #view-editor .apply-btn {
        padding: 6px 15px;
        background: #3498db;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
    }

    #view-editor .apply-btn:hover {
        background: #2980b9;
    }

    #view-editor .alignment-grid-9 {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 6px;
        max-width: 180px;
    }

    #view-editor .alignment-icon-btn {
        width: 56px;
        height: 56px;
        border: 2px solid #ddd;
        background: white;
        border-radius: 4px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        color: #666;
    }

    #view-editor .alignment-icon-btn:hover {
        background: #f0f0f0;
        border-color: #999;
    }

    #view-editor .alignment-icon-btn.active {
        background: #3498db;
        color: white;
        border-color: #3498db;
    }

    #view-editor .popup-footer {
        padding: 15px 20px;
        border-top: 1px solid #ddd;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    #view-editor .btn {
        padding: 8px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
    }

    #view-editor .btn-primary {
        background: #7cb342;
        color: white;
    }

    #view-editor .btn-primary:hover {
        background: #689f38;
    }

    #view-editor .btn-secondary {
        background: #e0e0e0;
        color: #333;
    }

    #view-editor .btn-secondary:hover {
        background: #d0d0d0;
    }

    #view-editor .scale-label {
        font-size: 12px;
        color: #666;
    }
</style>

<!-- List View -->
<div id="view-list">
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
                <button class="adm-btn" onclick="showLogs()">ЛОГИ</button>
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
                    $previewStyle = $set['PREVIEW_IMAGE'] ? 'style="background-image: url(\''. htmlspecialcharsbx($set['PREVIEW_IMAGE']) .'\')"' : '';
                ?>
                <div class="set-card" data-set-id="<?= $set['ID'] ?>" data-set-name="<?= htmlspecialcharsbx($set['NAME']) ?>" <?= $previewStyle ?> onmouseenter="showPreview(<?=$set['ID']?>, this, event)" onmouseleave="hidePreview()">
                    <div class="card-preview-overlay"></div>
                    <div class="card-content" onclick="openEditor(<?=$set['ID']?>)">
                        <div class="card-header">
                            <div class="card-icon">🖼️</div>
                            <div>
                                <div class="card-name"><?=htmlspecialcharsbx($set['NAME'])?></div>
                                <div class="card-meta">ID: <?=$set['ID']?> | Создан: <?= $dateCreate ?></div>
                            </div>
                        </div>
                        <div class="set-static-img" style="height: 120px; background: #f0f0f0; margin: 10px 0; display: flex; align-items: center; justify-content: center; overflow: hidden; border-radius: 4px;">
                            <?php if($set['PREVIEW_IMAGE']): ?>
                                <img src="<?=htmlspecialcharsbx($set['PREVIEW_IMAGE'])?>" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <span style="color: #ccc; font-size: 12px;">Нет обложки</span>
                            <?php endif; ?>
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
</div>

<!-- Editor View (SPA Constructor Popup) -->
<div id="view-editor" style="display:none;">
    <div class="popup-overlay">
        <div class="popup-header">
            Конструктор баннера: <span id="popup-set-id"></span>
        </div>

        <div class="popup-content">
            <!-- Левая панель -->
            <div class="left-panel">
                <!-- Превью выбранного блока -->
                <div class="selected-block-preview">
                    <div class="preview-title">Выбранный блок: <span id="preview-block-title"></span></div>
                    <div class="block-preview-container">
                        <div class="block-preview-content">
                            <img src="" alt="" class="block-preview-image" id="preview-block-image">
                            <div class="block-preview-title" id="preview-block-main-title"></div>
                            <div class="block-preview-text" id="preview-block-main-text"></div>
                        </div>
                    </div>
                </div>

                <!-- Настройки -->
                <div class="settings-panel">
                    <div class="section-title">Настройка отображения (Тяните мышкой для сдвига)</div>

                    <!-- Быстрое редактирование -->
                    <div class="quick-edit-section">
                        <div class="quick-edit-header">
                            <span class="quick-edit-title">БЫСТРОЕ РЕДАКТИРОВАНИЕ (КО ВСЕМ)</span>
                        </div>
                        
                        <div class="text-controls">
                            <div class="text-control-group">
                                <label>Цвет текста:</label>
                                <input type="color" class="color-picker" value="#000000" id="textColor">
                                <button class="apply-btn" onclick="applyToAll('color')">Применить ко всем</button>
                            </div>
                        </div>

                        <div class="text-controls">
                            <div class="text-control-group" style="width: 100%;">
                                <label>Размер шрифта:</label>
                                <label style="min-width: auto;">Заголовок:</label>
                                <input type="number" value="22" min="8" max="72" id="headerSize">
                                <label style="min-width: auto;">Анонс:</label>
                                <input type="number" value="14" min="8" max="72" id="announcementSize">
                                <button class="apply-btn" onclick="applyToAll('font')">Применить</button>
                            </div>
                        </div>

                        <div class="text-controls">
                            <div class="text-control-group" style="width: 100%; justify-content: flex-start;">
                                <label></label>
                                <label style="min-width: auto;">Заголовок:</label>
                                <button class="format-btn" data-format-type="header" data-format-style="bold"><b>B</b></button>
                                <button class="format-btn" data-format-type="header" data-format-style="italic"><i>I</i></button>
                                <button class="format-btn" data-format-type="header" data-format-style="underline"><u>U</u></button>
                                <label style="min-width: auto; margin-left: 10px;">Анонс:</label>
                                <button class="format-btn" data-format-type="announcement" data-format-style="bold"><b>B</b></button>
                                <button class="format-btn" data-format-type="announcement" data-format-style="italic"><i>I</i></button>
                                <button class="format-btn" data-format-type="announcement" data-format-style="underline"><u>U</u></button>
                            </div>
                        </div>
                    </div>

                    <!-- Основные настройки блока -->
                    <div class="settings-group">
                        <div class="control-row">
                            <input type="checkbox" id="textBg" checked>
                            <label for="textBg">Фон под текстом</label>
                        </div>

                        <div class="control-row">
                            <label>Прозрачность:</label>
                            <div class="slider-container">
                                <input type="range" min="0" max="100" value="90" id="transparency">
                                <input type="number" min="0" max="100" value="90" id="transparencyValue">
                                <span>%</span>
                            </div>
                        </div>

                        <div class="control-row">
                            <input type="checkbox" id="autoCategory" checked>
                            <label for="autoCategory">Режим категорий (Авто)</label>
                        </div>
                    </div>

                    <!-- Выравнивание (как в Word) -->
                    <div class="settings-group">
                        <div style="font-size: 13px; margin-bottom: 10px; font-weight: 600;">Выравнивание:</div>
                        <div class="alignment-grid-9">
                            <button class="alignment-icon-btn active" data-pos-x="left" data-pos-y="top" title="Верх слева">
                                <svg width="20" height="20" viewBox="0 0 20 20"><rect x="2" y="2" width="7" height="5" fill="currentColor"/><rect x="2" y="2" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1"/></svg>
                            </button>
                            <button class="alignment-icon-btn" data-pos-x="center" data-pos-y="top" title="Верх по центру">
                                <svg width="20" height="20" viewBox="0 0 20 20"><rect x="6.5" y="2" width="7" height="5" fill="currentColor"/><rect x="2" y="2" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1"/></svg>
                            </button>
                            <button class="alignment-icon-btn" data-pos-x="right" data-pos-y="top" title="Верх справа">
                                <svg width="20" height="20" viewBox="0 0 20 20"><rect x="11" y="2" width="7" height="5" fill="currentColor"/><rect x="2" y="2" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1"/></svg>
                            </button>
                            <button class="alignment-icon-btn" data-pos-x="left" data-pos-y="center" title="Центр слева">
                                <svg width="20" height="20" viewBox="0 0 20 20"><rect x="2" y="7.5" width="7" height="5" fill="currentColor"/><rect x="2" y="2" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1"/></svg>
                            </button>
                            <button class="alignment-icon-btn" data-pos-x="center" data-pos-y="center" title="Центр">
                                <svg width="20" height="20" viewBox="0 0 20 20"><rect x="6.5" y="7.5" width="7" height="5" fill="currentColor"/><rect x="2" y="2" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1"/></svg>
                            </button>
                            <button class="alignment-icon-btn" data-pos-x="right" data-pos-y="center" title="Центр справа">
                                <svg width="20" height="20" viewBox="0 0 20 20"><rect x="11" y="7.5" width="7" height="5" fill="currentColor"/><rect x="2" y="2" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1"/></svg>
                            </button>
                            <button class="alignment-icon-btn" data-pos-x="left" data-pos-y="bottom" title="Низ слева">
                                <svg width="20" height="20" viewBox="0 0 20 20"><rect x="2" y="13" width="7" height="5" fill="currentColor"/><rect x="2" y="2" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1"/></svg>
                            </button>
                            <button class="alignment-icon-btn" data-pos-x="center" data-pos-y="bottom" title="Низ по центру">
                                <svg width="20" height="20" viewBox="0 0 20 20"><rect x="6.5" y="13" width="7" height="5" fill="currentColor"/><rect x="2" y="2" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1"/></svg>
                            </button>
                            <button class="alignment-icon-btn" data-pos-x="right" data-pos-y="bottom" title="Низ справа">
                                <svg width="20" height="20" viewBox="0 0 20 20"><rect x="11" y="13" width="7" height="5" fill="currentColor"/><rect x="2" y="2" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1"/></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Масштаб -->
                    <div class="settings-group">
                        <div style="font-size: 13px; margin-bottom: 10px; font-weight: 600;">Масштаб: <span class="scale-label" id="scaleValue">101%</span></div>
                        <input type="range" min="10" max="200" value="101" id="scale" style="width: 100%;">
                    </div>
                </div>
            </div>

            <!-- Правая панель с сеткой баннера -->
            <div class="right-panel">
                <div class="banner-grid" id="popup-grid-container">
                    <!-- Blocks will be rendered by JS -->
                </div>
            </div>
        </div>

        <div class="popup-footer">
            <button class="btn btn-primary" onclick="saveCurrentBlock()">Применить</button>
            <button class="btn btn-secondary" onclick="closeEditor()">Закрыть</button>
        </div>
    </div>
</div>

<script>
    // Global PHP data
    const bannersData = <?=json_encode($allBanners)?>; // allBanners should contain all blocks for all sets
    const currentSets = <?=json_encode($sets)?>; // For the main list view operations

    let currentEditedSetId = null;
    let currentSelectedSlotIndex = null;
    let currentSelectedBlockData = null; // Data for the currently selected block within the editor

    // DOM Elements
    const viewList = document.getElementById('view-list');
    const viewEditor = document.getElementById('view-editor');

    const popupSetIdSpan = document.getElementById('popup-set-id');
    const popupGridContainer = document.getElementById('popup-grid-container');
    
    // Preview elements
    const previewBlockTitle = document.getElementById('preview-block-title');
    const previewBlockImage = document.getElementById('preview-block-image');
    const previewBlockMainTitle = document.getElementById('preview-block-main-title');
    const previewBlockMainText = document.getElementById('preview-block-main-text');

    // Settings panel inputs
    const textColorInput = document.getElementById('textColor');
    const headerSizeInput = document.getElementById('headerSize');
    const announcementSizeInput = document.getElementById('announcementSize');
    const textBgCheckbox = document.getElementById('textBg');
    const transparencySlider = document.getElementById('transparency');
    const transparencyValueInput = document.getElementById('transparencyValue');
    const autoCategoryCheckbox = document.getElementById('autoCategory');
    const scaleSlider = document.getElementById('scale');
    const scaleValueLabel = document.getElementById('scaleValue');
    const alignmentButtons = document.querySelectorAll('#view-editor .alignment-icon-btn');
    const formatButtons = document.querySelectorAll('#view-editor .format-btn');

    // --- Main View Management ---
    function openEditor(setId) {
        currentEditedSetId = setId;
        viewList.style.display = 'none';
        viewEditor.style.display = 'flex'; // Assuming flex for centered popup

        const set = currentSets[setId];
        popupSetIdSpan.textContent = `ID: ${setId} - ${set.NAME || ''}`;

        // Populate global settings from the set object
        textBgCheckbox.checked = set.TEXT_BG_SHOW === 'Y';
        transparencySlider.value = set.TEXT_BG_OPACITY || 90;
        transparencyValueInput.value = set.TEXT_BG_OPACITY || 90;
        autoCategoryCheckbox.checked = set.CATEGORY_MODE === 'Y';
        // (Добавить остальные глобальные поля если есть)
        
        renderEditorGrid();
        selectBlock(currentEditedSetId, 1); // Select the first block by default
    }

    function closeEditor() {
        viewEditor.style.display = 'none';
        viewList.style.display = 'block';
        currentEditedSetId = null;
        currentSelectedSlotIndex = null;
        currentSelectedBlockData = null;
    }

    // --- Constructor (Editor) Logic ---
    function renderEditorGrid() {
        popupGridContainer.innerHTML = '';
        const setBanners = bannersData[currentEditedSetId] || [];

        // Create a map of existing blocks by slot_index for easy lookup
        const blockMap = setBanners.reduce((acc, block) => {
            acc[block.SLOT_INDEX] = block;
            return acc;
        }, {});

        for (let i = 1; i <= 9; i++) { // Assuming 9 slots based on the HTML reference
            const block = blockMap[i] || {};
            const blockEl = document.createElement('div');
            blockEl.className = 'banner-block';
            blockEl.dataset.slotIndex = i;
            blockEl.onclick = () => selectBlock(currentEditedSetId, i);

            if (Object.keys(block).length > 0) { // If block data exists
                if (block.IMAGE) {
                    blockEl.innerHTML = `
                        <img src="${block.IMAGE}" alt="${block.TITLE || ''}" class="block-image">
                        <div class="block-title">${block.TITLE || ''}</div>
                        <div class="block-text">${block.SUBTITLE || ''}</div>
                    `;
                } else {
                    blockEl.innerHTML = `
                        <div class="block-title">${block.TITLE || 'Настроить блок'}</div>
                        <div class="block-text">${block.SUBTITLE || `Слот #${i}`}</div>
                    `;
                    blockEl.style.backgroundColor = block.COLOR || '#f0f0f0';
                }
            } else { // Empty slot
                blockEl.innerHTML = `
                    <div class="block-title">Пустой слот #${i}</div>
                    <div class="block-text">Нажмите для настройки</div>
                `;
            }
            popupGridContainer.appendChild(blockEl);
        }
    }

    function selectBlock(setId, slotIndex) {
        currentEditedSetId = setId;
        currentSelectedSlotIndex = slotIndex;
        const setBanners = bannersData[currentEditedSetId] || [];
        currentSelectedBlockData = setBanners.find(b => b.SLOT_INDEX == slotIndex) || {};

        // Highlight selected block
        document.querySelectorAll('#view-editor .banner-block').forEach(el => {
            el.classList.remove('selected');
        });
        const selectedEl = document.querySelector(`#view-editor .banner-block[data-slot-index='${slotIndex}']`);
        if (selectedEl) {
            selectedEl.classList.add('selected');
        }
        
        // Populate form fields (block-specific settings)
        textColorInput.value = currentSelectedBlockData.TEXT_COLOR || '#000000';
        headerSizeInput.value = parseInt(currentSelectedBlockData.TITLE_FONT_SIZE) || 22;
        announcementSizeInput.value = parseInt(currentSelectedBlockData.SUBTITLE_FONT_SIZE) || 14;
        scaleSlider.value = currentSelectedBlockData.IMG_SCALE || 100;
        scaleValueLabel.textContent = (currentSelectedBlockData.IMG_SCALE || 100) + '%';

        // Update alignment buttons
        alignmentButtons.forEach(btn => btn.classList.remove('active'));
        const currentAlignX = currentSelectedBlockData.IMG_POS_X_ALIGN || 'center'; // Assuming default 'center'
        const currentAlignY = currentSelectedBlockData.IMG_POS_Y_ALIGN || 'center'; // Assuming default 'center'
        const activeAlignBtn = document.querySelector(`#view-editor .alignment-icon-btn[data-pos-x='${currentAlignX}'][data-pos-y='${currentAlignY}']`);
        if(activeAlignBtn) activeAlignBtn.classList.add('active');

        // Update format buttons (Bold, Italic, Underline)
        formatButtons.forEach(btn => {
            const type = btn.dataset.formatType;
            const style = btn.dataset.formatStyle;
            let field = '';
            if (type === 'header' && style === 'bold') field = 'TITLE_BOLD';
            if (type === 'header' && style === 'italic') field = 'TITLE_ITALIC';
            if (type === 'header' && style === 'underline') field = 'TITLE_UNDERLINE';
            if (type === 'announcement' && style === 'bold') field = 'SUBTITLE_BOLD';
            if (type === 'announcement' && style === 'italic') field = 'SUBTITLE_ITALIC';
            if (type === 'announcement' && style === 'underline') field = 'SUBTITLE_UNDERLINE';
            
            if (currentSelectedBlockData[field] === 'Y') {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });

        // Update selected block preview
        updateSelectedBlockPreview();
    }

    function updateSelectedBlockPreview() {
        previewBlockTitle.textContent = currentSelectedBlockData.TITLE || `Блок #${currentSelectedSlotIndex}`;
        previewBlockImage.src = currentSelectedBlockData.IMAGE || '';
        previewBlockMainTitle.innerHTML = getFormattedText(currentSelectedBlockData.TITLE || '', 'title');
        previewBlockMainText.innerHTML = getFormattedText(currentSelectedBlockData.SUBTITLE || '', 'subtitle');
    }

    // Helper to get formatted text for preview
    function getFormattedText(text, type) {
        let style = '';
        // Use currentSelectedBlockData for dynamic styling
        if (type === 'title') {
            if (currentSelectedBlockData.TITLE_BOLD === 'Y') style += 'font-weight:bold;';
            if (currentSelectedBlockData.TITLE_ITALIC === 'Y') style += 'font-style:italic;';
            if (currentSelectedBlockData.TITLE_UNDERLINE === 'Y') style += 'text-decoration:underline;';
            style += `font-size:${parseInt(currentSelectedBlockData.TITLE_FONT_SIZE) || 22}px;`;
        } else if (type === 'subtitle') {
            if (currentSelectedBlockData.SUBTITLE_BOLD === 'Y') style += 'font-weight:bold;';
            if (currentSelectedBlockData.SUBTITLE_ITALIC === 'Y') style += 'font-style:italic;';
            if (currentSelectedBlockData.SUBTITLE_UNDERLINE === 'Y') style += 'text-decoration:underline;';
            style += `font-size:${parseInt(currentSelectedBlockData.SUBTITLE_FONT_SIZE) || 14}px;`;
        }
        return `<span style="${style}">${text}</span>`;
    }

    function saveCurrentBlock() {
        const formData = new FormData();
        formData.append('action', 'save_slot');
        formData.append('set_id', currentEditedSetId);
        formData.append('slot_index', currentSelectedSlotIndex);
        formData.append('sessid', '<?=bitrix_sessid()?>');

        // Collect data from form fields
        formData.append('TEXT_COLOR', textColorInput.value);
        formData.append('TITLE_FONT_SIZE', headerSizeInput.value + 'px');
        formData.append('SUBTITLE_FONT_SIZE', announcementSizeInput.value + 'px');
        formData.append('TEXT_BG_SHOW', textBgCheckbox.checked ? 'Y' : 'N');
        formData.append('TEXT_BG_OPACITY', transparencyValueInput.value);
        formData.append('CATEGORY_MODE', autoCategoryCheckbox.checked ? 'Y' : 'N');
        formData.append('IMG_SCALE', scaleSlider.value);
        
        // Alignment
        const activeAlignBtn = document.querySelector('#view-editor .alignment-icon-btn.active');
        if (activeAlignBtn) {
            formData.append('IMG_POS_X_ALIGN', activeAlignBtn.dataset.posX);
            formData.append('IMG_POS_Y_ALIGN', activeAlignBtn.dataset.posY);
        }

        // Format buttons
        formatButtons.forEach(btn => {
            const type = btn.dataset.formatType;
            const style = btn.dataset.formatStyle;
            let field = '';
            if (type === 'header' && style === 'bold') field = 'TITLE_BOLD';
            if (type === 'header' && style === 'italic') field = 'TITLE_ITALIC';
            if (type === 'header' && style === 'underline') field = 'TITLE_UNDERLINE';
            if (type === 'announcement' && style === 'bold') field = 'SUBTITLE_BOLD';
            if (type === 'announcement' && style === 'italic') field = 'SUBTITLE_ITALIC';
            if (type === 'announcement' && style === 'underline') field = 'SUBTITLE_UNDERLINE';
            
            if (field) {
                formData.append(field, btn.classList.contains('active') ? 'Y' : 'N');
            }
        });


        fetch('mycompany_banner_ajax_save_banner.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update bannersData with new data
                    if (!bannersData[currentEditedSetId]) {
                        bannersData[currentEditedSetId] = [];
                    }
                    const existingIndex = bannersData[currentEditedSetId].findIndex(b => b.SLOT_INDEX == currentSelectedSlotIndex);
                    if (existingIndex > -1) {
                        bannersData[currentEditedSetId][existingIndex] = data.data;
                    } else {
                        bannersData[currentEditedSetId].push(data.data);
                    }
                    currentSelectedBlockData = data.data; // Update current block data with fresh data
                    renderEditorGrid(); // Re-render the grid in the popup
                    updateSelectedBlockPreview(); // Update the preview in the left panel
                    // Optionally, re-render the main grid on the settings page if visible, to reflect changes immediately
                    // This part would require a function in the main script to update the grid of set cards.
                    alert('Изменения сохранены!');
                } else {
                    alert('Ошибка при сохранении: ' + (data.errors ? data.errors.join('\n') : 'Неизвестная ошибка.'));
                }
            })
            .catch(error => {
                console.error('Error saving block:', error);
                alert('Произошла ошибка при сохранении блока.');
            });
    }

    // Event listeners for settings panel
    transparencySlider.addEventListener('input', (e) => {
        transparencyValueInput.value = e.target.value;
    });
    transparencyValueInput.addEventListener('input', (e) => {
        transparencySlider.value = e.target.value;
    });

    scaleSlider.addEventListener('input', (e) => {
        scaleValueLabel.textContent = e.target.value + '%';
        // TODO: Update image preview with new scale
    });

    alignmentButtons.forEach(button => {
        button.addEventListener('click', (e) => {
            alignmentButtons.forEach(btn => btn.classList.remove('active'));
            e.currentTarget.classList.add('active');
            // TODO: Update image preview with new alignment
        });
    });

    formatButtons.forEach(button => {
        button.addEventListener('click', (e) => {
            e.currentTarget.classList.toggle('active');
            // TODO: Update text preview with new formatting
        });
    });

    // Dummy applyToAll functions for now - these would need to interact with the backend
    function applyToAll(type) {
        if (type === 'color') {
            const color = textColorInput.value;
            console.log('Применить цвет ко всем:', color);
            // This would involve another AJAX call to save global setting for all blocks
        } else if (type === 'font') {
            const headerSize = headerSizeInput.value;
            const announcementSize = announcementSizeInput.value;
            console.log('Применить размеры шрифтов:', headerSize, announcementSize);
            // This would involve another AJAX call to save global setting for all blocks
        }
        alert('Функционал "Применить ко всем" пока не реализован.');
    }


    // --- List View Specific JS (existing functions, possibly modified) ---
    // This object holds all blocks for all banners, passed from PHP.
    // It's used to build the on-hover preview without extra AJAX calls.
    const bannersBySet = bannersData; // Renamed for clarity with openEditor logic

    const previewPopup = document.getElementById('preview-popup');
    const previewPopupCrop = document.getElementById('preview-crop');

    /**
     * Live search functionality. Filters banners by name.
     */
    function showLogs() {
        // Удаляем старое окно если есть
        const existing = document.getElementById('logModalOverlay');
        if (existing) existing.remove();

        // Создаем оверлей
        const overlay = document.createElement('div');
        overlay.id = 'logModalOverlay';
        overlay.style.cssText = 'position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:10000; display:flex; align-items:center; justify-content:center;';

        // Создаем окно
        const modal = document.createElement('div');
        modal.style.cssText = 'background:#fff; width:900px; height:600px; border-radius:8px; display:flex; flex-direction:column; box-shadow:0 10px 30px rgba(0,0,0,0.3); overflow:hidden; font-family: sans-serif;';

        // Шапка
        modal.innerHTML = `
            <div style="padding:15px 20px; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center; background:#f8f9fa;">
                <h3 style="margin:0; font-size:18px; color:#333;">Логи отладки</h3>
                <button onclick="document.getElementById('logModalOverlay').remove()" style="border:none; background:none; font-size:24px; cursor:pointer; color:#999;">&times;</button>
            </div>
            <div style="flex:1; position:relative; background:#2d2d2d;">
                <textarea id="logTextarea" readonly style="width:100%; height:100%; border:none; background:transparent; color:#0f0; padding:15px; font-family:monospace; font-size:13px; resize:none; box-sizing:border-box; outline:none;"></textarea>
            </div>
            <div style="padding:15px 20px; border-top:1px solid #eee; background:#fff; display:flex; justify-content:flex-end; gap:10px;">
                <button class="adm-btn" onclick="copyLogContent(this)">📋 Скопировать</button>
                <button class="adm-btn" onclick="clearLogContent()">🗑 Очистить</button>
                <button class="adm-btn" onclick="document.getElementById('logModalOverlay').remove()">Закрыть</button>
            </div>
        `;

        overlay.appendChild(modal);
        document.body.appendChild(overlay);

        // Загрузка данных
        fetch('mycompany_banner_ajax_save_banner.php?action=get_log&sessid=<?=bitrix_sessid()?>')
            .then(r => r.text())
            .then(text => {
                document.getElementById('logTextarea').value = text || 'Лог файл пуст.';
            });
    }

    function copyLogContent(btn) {
        const text = document.getElementById('logTextarea');
        text.select();
        document.execCommand('copy');
        const original = btn.innerHTML;
        btn.innerHTML = '✅ Скопировано!';
        setTimeout(() => btn.innerHTML = original, 2000);
    }

    function clearLogContent() {
        if(!confirm('Очистить файл логов?')) return;
        fetch('mycompany_banner_ajax_save_banner.php', {
            method: 'POST',
            body: new URLSearchParams({action: 'clear_log', sessid: '<?=bitrix_sessid()?>'})
        }).then(() => {
            document.getElementById('logTextarea').value = 'Лог очищен.';
        });
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

        if (!confirm(`Вы уверены, что хотите удалить баннер "${name}" (ID: ${id})?`)) {
            return;
        }

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
                alert('Баннер успешно удален.');
            } else {
                alert('Ошибка удаления: ' + (res.errors ? res.errors.join('\n') : 'Неизвестная ошибка.'));
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
            if (!previewPopupCrop) return;
            previewPopupCrop.innerHTML = ''; // Clear previous content

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
                    let titleStyle = `font-size:${parseInt(b.TITLE_FONT_SIZE) || '22'}px; font-weight:${b.TITLE_BOLD === 'Y' ? 'bold' : 'normal'};`;
                    let subStyle = `font-size:${parseInt(b.SUBTITLE_FONT_SIZE) || '14'}px; font-weight:${b.SUBTITLE_BOLD === 'Y' ? 'bold' : 'normal'};`;
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
            previewPopupCrop.appendChild(grid);
            
            previewPopup.style.display = 'block';
            previewPopup.style.opacity = '0';

            // "Smart" positioning logic
            const popupWidth = 500;
            const isRightSide = (window.innerWidth - event.clientX) < (popupWidth + 20);

            let top = event.clientY + 15;
            let left = event.clientX + 15;

            if (isRightSide) {
                // Show on the left if not enough space on the right
                left = event.clientX - popupWidth - 15;
            }

            if (top + previewPopup.offsetHeight > window.innerHeight) {
                // Adjust vertically if it overflows the bottom
                top = window.innerHeight - previewPopup.offsetHeight - 10;
            }
            
            previewPopup.style.top = top + 'px';
            previewPopup.style.left = left + 'px';
            
            requestAnimationFrame(() => {
                previewPopup.style.opacity = '1';
            });

        }, 100); 
    }

    /**
     * Hides the preview popup.
     */
    function hidePreview() {
        clearTimeout(previewTimeout);
        previewPopup.style.opacity = '0';
        setTimeout(() => {
            if (previewPopup.style.opacity === '0') {
                previewPopup.style.display = 'none';
            }
        }, 150);
    }

    /**
     * Shows the "Create new banner" popup.
     */
    // function createSet() is now replaced by the new implementation above


    // Initial setup: ensure list view is visible and editor is hidden
    document.addEventListener('DOMContentLoaded', () => {
        viewList.style.display = 'block';
        viewEditor.style.display = 'none';
    });
</script>

<?php require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php"); ?>