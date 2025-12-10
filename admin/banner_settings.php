<?php
// Включаем отображение ошибок для отладки 500
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");

use Bitrix\Main\Loader;
use MyCompany\Banner\BannerSetTable;
use MyCompany\Banner\BannerTable;
use Bitrix\Main\Type\DateTime;

$module_id = "mycompany.banner";

try {
    if (!Loader::includeModule($module_id)) {
        throw new \Exception("Модуль {$module_id} не установлен.");
    }

    $APPLICATION->SetTitle("Управление баннерами");

    // --- Data Fetching ---
    $sets = [];
    $allBanners = [];
    $setIds = [];

    // 1. Получаем наборы
    $setsRaw = BannerSetTable::getList(['order' => ['ID' => 'DESC']]);
    while($row = $setsRaw->fetch()) {
        $firstBlock = BannerTable::getList([
            'filter' => ['SET_ID' => $row['ID'], '!IMAGE' => false],
            'select' => ['IMAGE'], 'limit' => 1
        ])->fetch();
        
        if ($firstBlock && is_numeric($firstBlock['IMAGE'])) {
             $row['PREVIEW_IMAGE'] = \CFile::GetPath($firstBlock['IMAGE']);
        } elseif ($firstBlock) {
             $row['PREVIEW_IMAGE'] = $firstBlock['IMAGE'];
        } else {
             $row['PREVIEW_IMAGE'] = null;
        }

        $sets[$row['ID']] = $row;
        $setIds[] = $row['ID'];
    }

    // 2. Получаем блоки для попапа
    if (!empty($setIds)) {
        $bannersRes = BannerTable::getList([
            'filter' => ['@SET_ID' => $setIds],
            'select' => ['*'], // Select all fields after ORM update
            'order' => ['SORT' => 'ASC']
        ]);
        while ($banner = $bannersRes->fetch()) {
            if (isset($sets[$banner['SET_ID']])) {
                if ($banner['IMAGE'] && is_numeric($banner['IMAGE'])) {
                    $banner['IMAGE'] = \CFile::GetPath($banner['IMAGE']);
                }
                $allBanners[$banner['SET_ID']][] = $banner;
            }
        }
    }
    $totalSets = count($sets);

} catch (\Throwable $e) {
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
    CAdminMessage::ShowMessage([
        "MESSAGE" => "Ошибка при загрузке страницы",
        "DETAILS" => $e->getMessage() . "<br>File: " . $e->getFile() . "<br>Line: " . $e->getLine(),
        "TYPE" => "ERROR",
        "HTML" => true
    ]);
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
    die();
}

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
?>

<style>
    :root { --primary-color: #3b82f6; --danger-color: #ef4444; }
    .container * { box-sizing: border-box; }
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background: #f5f7fa; }
    .container { max-width: 1600px; margin: 0 auto; padding: 20px; }
    .page-header { background: white; border-radius: 12px; padding: 25px 30px; margin-bottom: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; }
    .page-title-section h1 { font-size: 24px; font-weight: 700; color: #333; margin: 0 0 5px 0; display: flex; align-items: center; gap: 12px; }
    .page-subtitle { color: #64748b; font-size: 14px; margin: 0; }
    .header-actions { display: flex; gap: 12px; }
    .filter-bar { display: flex; gap: 16px; align-items: center; margin-bottom: 24px; }
    .search-box { flex: 1; min-width: 300px; position: relative; }
    #searchSet { width: 100%; padding-left: 35px !important; }
    .search-box::before { content: "🔍"; position: absolute; left: 10px; top: 50%; transform: translateY(-50%); font-size: 16px; opacity: 0.6; }
    .sets-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px; }
    .set-card { background-color: white; background-size: cover; background-position: center; position: relative; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); transition: all 0.3s; border: 1px solid #e2e8f0; display: flex; flex-direction: column; }
    .card-content { position: relative; z-index: 2; padding: 20px; flex-grow: 1; cursor: pointer; }
    .set-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); border-color: var(--primary-color); }
    .card-header { display: flex; align-items: flex-start; gap: 15px; margin-bottom: 15px; }
    .card-icon { flex-shrink: 0; width: 44px; height: 44px; border-radius: 8px; background: #eef5ff; color: var(--primary-color); display: flex; align-items: center; justify-content: center; font-size: 22px; }
    .card-name { font-size: 17px; font-weight: 600; color: #1e293b; margin-bottom: 4px; }
    .card-meta { color: #94a3b8; font-size: 12px; font-weight: 500; }
    .set-static-img { height: 120px; background: #f0f0f0; margin: 10px 0; display: flex; align-items: center; justify-content: center; overflow: hidden; border-radius: 4px; }
    .set-static-img img { width: 100%; height: 100%; object-fit: cover; }
    .card-actions { padding: 10px 15px; background: #f8fafc; border-top: 1px solid #eef2f9; display:flex; justify-content: flex-end; }
    .delete-btn { width: 32px; height: 32px; border: none; border-radius: 6px; background: #fef2f2; color: var(--danger-color); cursor: pointer; transition: all 0.2s; font-size: 16px; }
    .delete-btn:hover { background: var(--danger-color); color: white; }
    #create-popup { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); z-index: 10100; align-items: center; justify-content: center; }
    #create-popup-content { background: #fff; padding: 25px; border-radius: 8px; width: 400px; box-shadow: 0 5px 20px rgba(0,0,0,0.2); }

    /* Editor Popup */
    #view-editor { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 10100; display: none; justify-content: center; align-items: center; padding: 20px; }
    #view-editor .popup-overlay { font-family: Arial, sans-serif; background: white; border-radius: 8px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3); max-width: 1500px; width: 100%; height: 95vh; display: flex; flex-direction: column; }
    #view-editor .popup-header { background: #2c3e50; color: white; padding: 15px 20px; border-radius: 8px 8px 0 0; font-size: 16px; font-weight: bold; }
    #view-editor .popup-content { display: flex; flex: 1; overflow: hidden; }
    #view-editor .left-panel { width: 550px; border-right: 1px solid #ddd; display: flex; flex-direction: column; overflow: hidden; }
    #view-editor .settings-panel { flex: 1; padding: 20px; overflow-y: auto; background: #f9f9f9; }
    #view-editor .right-panel { flex: 1; padding: 20px; display: flex; justify-content: center; align-items: flex-start; background: #e8e8e8; overflow: auto; }
    #view-editor .banner-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; width: 100%; max-width: 1000px; }
    #view-editor .banner-block { background-size: cover; background-position: center; border: 3px solid transparent; border-radius: 6px; padding: 12px; cursor: pointer; transition: all 0.2s; position: relative; display: flex; flex-direction: column; min-height: 150px; color: white; text-shadow: 1px 1px 2px rgba(0,0,0,0.5); justify-content: center; align-items: center; }
    #view-editor .banner-block.large { grid-column: span 2; min-height: 280px; }
    #view-editor .banner-block.small { grid-column: span 1; min-height: 200px; }
    #view-editor .banner-block.dragging { opacity: 0.5; transform: scale(0.95); }
    #view-editor .banner-block.drag-over { border-color: #27ae60; box-shadow: 0 0 20px rgba(39, 174, 96, 0.5); }
    #view-editor .banner-block:hover { border-color: #95a5a6; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); }
    #view-editor .banner-block.selected { border-color: var(--primary-color); box-shadow: 0 4px 16px rgba(52, 152, 219, 0.3); }
    #view-editor .block-title-wrapper { padding: 10px; border-radius: 8px; text-align: center; }
    #view-editor .block-title { font-size: 18px; font-weight: bold; line-height: 1.3; }
    #view-editor .block-text { font-size: 13px; line-height: 1.4; }
    #view-editor .section-title { font-size: 14px; font-weight: bold; margin: 0 0 15px 0; padding-bottom: 10px; border-bottom: 1px solid #e0e0e0; }
    #view-editor .settings-group { background: white; padding: 15px; border-radius: 6px; margin-bottom: 15px; border: 1px solid #ddd; }
    #view-editor .control-row { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
    #view-editor .control-row:last-child { margin-bottom: 0; }
    #view-editor .control-row label { font-size: 13px; color: #555; min-width: 120px; }
    #view-editor .control-row input[type="text"], #view-editor .control-row select { width: 100%; }
    #view-editor .quick-edit-section { background: #fff9e6; padding: 15px; border-radius: 6px; margin-bottom: 15px; border: 1px solid #ffd966; }
    #view-editor .quick-edit-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
    #view-editor .quick-edit-title { font-size: 13px; font-weight: bold; color: #333; }
    .toggle-switch-container { display: flex; align-items: center; gap: 8px; font-size: 12px; }
    .switch { position: relative; display: inline-block; width: 44px; height: 24px; }
    .switch input { opacity: 0; width: 0; height: 0; }
    .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 24px; }
    .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
    input:checked + .slider { background-color: var(--primary-color); }
    input:focus + .slider { box-shadow: 0 0 1px var(--primary-color); }
    input:checked + .slider:before { transform: translateX(20px); }
    #view-editor .format-buttons { display: flex; gap: 5px; }
    #view-editor .format-btn { padding: 4px 10px; border: 1px solid #ddd; background: white; border-radius: 3px; cursor: pointer; font-size: 12px; font-weight: bold; }
    #view-editor .format-btn:hover { background: #f0f0f0; }
    #view-editor .format-btn.active { background: var(--primary-color); color: white; border-color: var(--primary-color); }
    #view-editor .text-controls { display: flex; gap: 10px; margin-bottom: 10px; flex-wrap: wrap; }
    #view-editor .text-control-group { display: flex; align-items: center; gap: 8px; font-size: 12px; }
    #view-editor .color-picker { width: 40px; height: 28px; border: 1px solid #ddd; border-radius: 3px; cursor: pointer; padding: 2px; }
    #view-editor .apply-btn { padding: 6px 15px; background: var(--primary-color); color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; }
    #view-editor .apply-btn:hover { background: #2980b9; }
    #view-editor .popup-footer { padding: 15px 20px; border-top: 1px solid #ddd; display: flex; justify-content: flex-end; gap: 10px; background: #f8f9fa; }
    #view-editor .btn { padding: 8px 20px; border-radius: 4px; cursor: pointer; font-size: 14px; border: 1px solid #ccc; }
    #view-editor .btn-primary { background: #28a745; color: white; border-color: #28a745; }
    #view-editor .btn-primary:hover { background: #218838; }
    #view-editor .btn-secondary { background: #6c757d; color: white; border-color: #6c757d;}
    #view-editor .btn-secondary:hover { background: #5a6268; }
</style>

<div id="view-list">
    <div class="container">
        <div class="page-header"><div class="page-title-section"><h1>🎨 Управление баннерами</h1><p class="page-subtitle">Создавайте и редактируйте рекламные сетки для вашего сайта</p></div><div class="header-actions"><button class="adm-btn adm-btn-save" onclick="createSet()">➕ Создать баннер</button></div></div>
        <div class="filter-bar"><div class="search-box"><input type="text" id="searchSet" class="adm-input" placeholder="Поиск по названию баннера..."></div></div>
        <div class="sets-grid" id="setsGrid">
            <?php if (empty($sets)): ?>
                <p>Еще не создано ни одного баннера.</p>
            <?php else: ?>
                <?php foreach($sets as $set):
                    $dateCreate = ($set['DATE_CREATE'] instanceof DateTime) ? $set['DATE_CREATE']->format('d.m.Y') : 'N/A';
                ?>
                <div class="set-card" data-set-id="<?= $set['ID'] ?>" data-set-name="<?= htmlspecialcharsbx($set['NAME']) ?>">
                    <div class="card-content" onclick="openEditor(<?=$set['ID']?>)">
                        <div class="card-header">
                            <div class="card-icon">🖼️</div>
                            <div>
                                <div class="card-name"><?=htmlspecialcharsbx($set['NAME'])?></div>
                                <div class="card-meta">ID: <?=$set['ID']?> | Создан: <?= $dateCreate ?></div>
                            </div>
                        </div>
                        <div class="set-static-img">
                            <?php if($set['PREVIEW_IMAGE']): ?><img src="<?=htmlspecialcharsbx($set['PREVIEW_IMAGE'])?>"><?php else: ?><span style="color: #ccc; font-size: 12px;">Нет обложки</span><?php endif; ?>
                        </div>
                    </div>
                    <div class="card-actions"><button title="Удалить баннер" class="delete-btn" onclick="deleteSet(<?= $set['ID'] ?>, '<?= CUtil::JSEscape($set['NAME']) ?>', event)">🗑️</button></div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <div id="create-popup">
        <div id="create-popup-content">
            <h3 style="margin-top:0; margin-bottom:15px;">Новый баннер</h3>
            <div style="margin-bottom:15px;"><label style="display:block; margin-bottom:5px; font-weight:bold;">Название:</label><input type="text" id="newSetName" class="adm-input" style="width:100%;" placeholder="Например: Акции на главной"></div>
            <div style="margin-bottom:20px;"><label><input type="checkbox" id="newSetAuto" checked> Заполнить автоматически из категорий</label></div>
            <div style="text-align:right; display:flex; gap:10px; justify-content:flex-end;">
                <button class="adm-btn" onclick="document.getElementById('create-popup').style.display='none'">Отмена</button>
                <button class="adm-btn adm-btn-save" id="doCreateBtn" onclick="doCreate()">Создать</button>
            </div>
        </div>
    </div>
</div>

<div id="view-editor" style="display:none;">
    <div class="popup-overlay">
        <div class="popup-header">Конструктор баннера: <span id="popup-set-id"></span></div>
        <div class="popup-content">
            <div class="left-panel">
                <div class="settings-panel">
                    <div class="settings-group">
                        <div class="section-title">Интеграция с каталогом</div>
                        <div class="control-row"><label for="iblockSelect">Инфоблок</label><select id="iblockSelect" class="adm-select"></select></div>
                        <div class="control-row"><label for="sectionSelect">Раздел</label><select id="sectionSelect" class="adm-select"></select></div>
                        <div class="control-row"><label><input type="checkbox" id="hoverAnimation"> Использовать анимацию ховера</label></div>
                    </div>

                    <div class="quick-edit-section">
                        <div class="quick-edit-header">
                            <span class="quick-edit-title">Форматирование текста</span>
                            <div class="toggle-switch-container">
                                <span id="editModeLabel">Для всех блоков</span>
                                <label class="switch"><input type="checkbox" id="editModeToggle"><span class="slider round"></span></label>
                            </div>
                        </div>
                        <div class="text-controls"><div class="text-control-group"><label>Цвет:</label><input type="color" class="color-picker" value="#000000" id="textColor"></div></div>
                        <div class="text-controls">
                            <div class="text-control-group" style="width: 100%;"><label>Размер:</label><label style="min-width: auto;">Заг:</label><input type="number" value="22" min="8" max="72" id="headerSize" style="width:60px"><label style="min-width: auto;">Анонс:</label><input type="number" value="14" min="8" max="72" id="announcementSize" style="width:60px"></div>
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
                         <div style="text-align: right; margin-top: 10px;"><button class="apply-btn" onclick="applyFormatting()">Применить</button></div>
                    </div>

                    <div class="settings-group">
                        <div class="section-title">Стили блока</div>
                        <div class="control-row"><input type="checkbox" id="textBg"><label for="textBg">Фон под текстом</label><input type="color" class="color-picker" id="textBgColor" value="#ffffff"></div>
                        <div class="control-row"><label>Прозрачность фона:</label><div class="slider-container"><input type="range" min="0" max="100" value="70" id="transparency"><input type="number" min="0" max="100" value="70" id="transparencyValue" style="width:60px"><span>%</span></div></div>
                    </div>

                     <div class="settings-group">
                        <div class="section-title">Обводка текста</div>
                        <div class="control-row"><label>Толщина (px):</label><input type="number" min="0" max="10" value="0" id="textStrokeWidth" style="width:60px"></div>
                        <div class="control-row"><label>Цвет:</label><input type="color" class="color-picker" id="textStrokeColor" value="#000000"></div>
                    </div>

                    <div class="settings-group">
                        <div class="section-title">Изображение</div>
                        <div class="control-row"><label>Масштаб:</label><div class="slider-container"><input type="range" min="10" max="250" value="100" id="scale"><span class="scale-label" id="scaleValue">100%</span></div></div>
                        <p style="font-size:11px; color:#666; margin-top:0;">Для кадрирования, просто перетаскивайте изображение в окне предпросмотра.</p>
                    </div>
                </div>
            </div>
            <div class="right-panel">
                <div class="banner-grid" id="popup-grid-container"></div>
            </div>
        </div>
        <div class="popup-footer">
            <button class="btn btn-secondary" onclick="closeEditor()">Закрыть</button>
            <button class="btn btn-primary" onclick="saveCurrentBlock(true)">Сохранить и закрыть</button>
        </div>
    </div>
</div>

<script>
    const bannersData = <?=json_encode($allBanners)?>;
    const currentSets = <?=json_encode($sets)?>;
    let currentEditedSetId = null;
    let currentSelectedSlotIndex = null;
    let currentSelectedBlockData = null;
    let draggedSlot = null;
    let editMode = 'global';
    
    const viewList = document.getElementById('view-list');
    const viewEditor = document.getElementById('view-editor');
    const createPopup = document.getElementById('create-popup');
    const doCreateBtn = document.getElementById('doCreateBtn');
    const popupSetIdSpan = document.getElementById('popup-set-id');
    const popupGridContainer = document.getElementById('popup-grid-container');
    const textColorInput = document.getElementById('textColor');
    const headerSizeInput = document.getElementById('headerSize');
    const announcementSizeInput = document.getElementById('announcementSize');
    const textBgCheckbox = document.getElementById('textBg');
    const textBgColorInput = document.getElementById('textBgColor');
    const transparencySlider = document.getElementById('transparency');
    const transparencyValueInput = document.getElementById('transparencyValue');
    const textStrokeWidthInput = document.getElementById('textStrokeWidth');
    const textStrokeColorInput = document.getElementById('textStrokeColor');
    const scaleSlider = document.getElementById('scale');
    const scaleValueLabel = document.getElementById('scaleValue');
    const formatButtons = document.querySelectorAll('#view-editor .format-btn');
    const editModeToggle = document.getElementById('editModeToggle');
    const editModeLabel = document.getElementById('editModeLabel');
    const iblockSelect = document.getElementById('iblockSelect');
    const sectionSelect = document.getElementById('sectionSelect');
    const hoverAnimationCheckbox = document.getElementById('hoverAnimation');

    function openEditor(setId) {
        currentEditedSetId = setId;
        viewList.style.display = 'none';
        viewEditor.style.display = 'flex';
        popupSetIdSpan.textContent = `ID: ${setId} - ${currentSets[setId]?.NAME || ''}`;
        loadIblocks();
        renderEditorGrid();
        selectBlock(1);
    }

    function closeEditor() {
        viewEditor.style.display = 'none';
        viewList.style.display = 'block';
    }

    function renderEditorGrid() {
        popupGridContainer.innerHTML = '';
        const setBanners = bannersData[currentEditedSetId] || [];
        const blockMap = setBanners.reduce((acc, block) => { acc[block.SLOT_INDEX] = block; return acc; }, {});
        for (let i = 1; i <= 8; i++) {
            const block = blockMap[i] || {};
            const blockEl = document.createElement('div');
            blockEl.className = `banner-block ${(i <= 2) ? 'large' : 'small'}`;
            blockEl.dataset.slotIndex = i;
            blockEl.draggable = true;
            blockEl.onclick = () => selectBlock(i);
            
            if (block.IMAGE) blockEl.style.backgroundImage = `url('${block.IMAGE}')`;
            else blockEl.style.backgroundColor = block.COLOR || '#f0f0f0';

            const textBgColor = block.TEXT_BG_COLOR || '#ffffff';
            const textBgOpacity = (block.TEXT_BG_OPACITY || 70) / 100;
            const textBgRgba = `rgba(${parseInt(textBgColor.slice(1, 3), 16)}, ${parseInt(textBgColor.slice(3, 5), 16)}, ${parseInt(textBgColor.slice(5, 7), 16)}, ${textBgOpacity})`;
            const backgroundStyle = block.TEXT_BG_SHOW === 'Y' ? `background-color: ${textBgRgba}; padding: 10px; border-radius: 8px;` : '';
            const textStroke = (block.TEXT_STROKE_WIDTH > 0) ? `${block.TEXT_STROKE_WIDTH}px ${block.TEXT_STROKE_COLOR}` : 'none';
            const textStyle = `color: ${block.TEXT_COLOR || '#000000'}; -webkit-text-stroke: ${textStroke};`;
            const titleStyle = `font-size:${block.TITLE_FONT_SIZE || '18px'}; ${block.TITLE_BOLD==='Y'?'font-weight:bold;':''} ${block.TITLE_ITALIC==='Y'?'font-style:italic;':''} ${block.TITLE_UNDERLINE==='Y'?'text-decoration:underline;':''}`;
            const subTitleStyle = `font-size:${block.SUBTITLE_FONT_SIZE || '13px'}; ${block.SUBTITLE_BOLD==='Y'?'font-weight:bold;':''} ${block.SUBTITLE_ITALIC==='Y'?'font-style:italic;':''} ${block.SUBTITLE_UNDERLINE==='Y'?'text-decoration:underline;':''}`;
            blockEl.innerHTML = `<div class="block-title-wrapper" style="${backgroundStyle}"><div class="block-title" style="${textStyle} ${titleStyle}">${block.TITLE || ''}</div><div class="block-text" style="${textStyle} ${subTitleStyle}">${block.SUBTITLE || ''}</div></div>`;
            popupGridContainer.appendChild(blockEl);
        }
    }
    
    function selectBlock(slotIndex) {
        currentSelectedSlotIndex = slotIndex;
        const setBanners = bannersData[currentEditedSetId] || [];
        currentSelectedBlockData = setBanners.find(b => b.SLOT_INDEX == slotIndex) || {SLOT_INDEX: slotIndex, SET_ID: currentEditedSetId};
        document.querySelectorAll('#view-editor .banner-block').forEach(el => el.classList.toggle('selected', el.dataset.slotIndex == slotIndex));
        
        textBgCheckbox.checked = currentSelectedBlockData.TEXT_BG_SHOW === 'Y';
        textBgColorInput.value = currentSelectedBlockData.TEXT_BG_COLOR || '#ffffff';
        transparencySlider.value = currentSelectedBlockData.TEXT_BG_OPACITY || 70;
        transparencyValueInput.value = currentSelectedBlockData.TEXT_BG_OPACITY || 70;
        textStrokeWidthInput.value = currentSelectedBlockData.TEXT_STROKE_WIDTH || 0;
        textStrokeColorInput.value = currentSelectedBlockData.TEXT_STROKE_COLOR || '#000000';
        scaleSlider.value = currentSelectedBlockData.IMG_SCALE || 100;
        scaleValueLabel.textContent = (currentSelectedBlockData.IMG_SCALE || 100) + '%';
        hoverAnimationCheckbox.checked = currentSelectedBlockData.HOVER_ANIMATION === 'Y';
        textColorInput.value = currentSelectedBlockData.TEXT_COLOR || '#000000';
        headerSizeInput.value = parseInt(currentSelectedBlockData.TITLE_FONT_SIZE) || 22;
        announcementSizeInput.value = parseInt(currentSelectedBlockData.SUBTITLE_FONT_SIZE) || 14;
        formatButtons.forEach(btn => {
            const type = btn.dataset.formatType === 'header' ? 'TITLE' : 'SUBTITLE';
            const style = btn.dataset.formatStyle.toUpperCase();
            btn.classList.toggle('active', currentSelectedBlockData[`${type}_${style}`] === 'Y');
        });
    }

    function saveCurrentBlock(andClose = false) {
        if (!currentSelectedBlockData) return;
        const fd = new FormData();
        fd.append('action', 'save_slot');
        fd.append('sessid', '<?=bitrix_sessid()?>');
        Object.assign(currentSelectedBlockData, {
            TEXT_BG_SHOW: textBgCheckbox.checked ? 'Y' : 'N',
            TEXT_BG_COLOR: textBgColorInput.value,
            TEXT_BG_OPACITY: transparencyValueInput.value,
            TEXT_STROKE_WIDTH: textStrokeWidthInput.value,
            TEXT_STROKE_COLOR: textStrokeColorInput.value,
            IMG_SCALE: scaleSlider.value,
            HOVER_ANIMATION: hoverAnimationCheckbox.checked ? 'Y' : 'N'
        });
        for(const key in currentSelectedBlockData) { fd.append(key, currentSelectedBlockData[key]); }

        fetch('mycompany_banner_ajax_save_banner.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(res => {
                if (res.success) {
                    if (!bannersData[currentEditedSetId]) bannersData[currentEditedSetId] = [];
                    const index = bannersData[currentEditedSetId].findIndex(b => b.SLOT_INDEX == res.data.SLOT_INDEX);
                    if (index > -1) bannersData[currentEditedSetId][index] = res.data;
                    else bannersData[currentEditedSetId].push(res.data);
                    currentSelectedBlockData = res.data;
                    renderEditorGrid();
                    if(andClose) closeEditor();
                } else alert('Ошибка сохранения: ' + (res.errors ? res.errors.join('\n') : ''));
            });
    }
    
    editModeToggle.addEventListener('change', function() {
        editMode = this.checked ? 'local' : 'global';
        editModeLabel.textContent = this.checked ? 'Для выбранного блока' : 'Для всех блоков';
    });

    function applyFormatting() {
        const changes = {
            TEXT_COLOR: textColorInput.value,
            TITLE_FONT_SIZE: headerSizeInput.value + 'px',
            SUBTITLE_FONT_SIZE: announcementSizeInput.value + 'px',
            TITLE_BOLD: document.querySelector('.format-btn[data-format-type="header"][data-format-style="bold"]').classList.contains('active') ? 'Y' : 'N',
            TITLE_ITALIC: document.querySelector('.format-btn[data-format-type="header"][data-format-style="italic"]').classList.contains('active') ? 'Y' : 'N',
            TITLE_UNDERLINE: document.querySelector('.format-btn[data-format-type="header"][data-format-style="underline"]').classList.contains('active') ? 'Y' : 'N',
            SUBTITLE_BOLD: document.querySelector('.format-btn[data-format-type="announcement"][data-format-style="bold"]').classList.contains('active') ? 'Y' : 'N',
            SUBTITLE_ITALIC: document.querySelector('.format-btn[data-format-type="announcement"][data-format-style="italic"]').classList.contains('active') ? 'Y' : 'N',
            SUBTITLE_UNDERLINE: document.querySelector('.format-btn[data-format-type="announcement"][data-format-style="underline"]').classList.contains('active') ? 'Y' : 'N',
        };
        if (editMode === 'local') {
            if (!currentSelectedBlockData) { alert('Сначала выберите блок для редактирования.'); return; }
            Object.assign(currentSelectedBlockData, changes);
            saveCurrentBlock();
        } else {
            bannersData[currentEditedSetId]?.forEach(block => Object.assign(block, changes));
            saveGlobalStyles(changes);
        }
    }

    function saveGlobalStyles(changes) {
        const fd = new FormData();
        fd.append('action', 'save_global_styles');
        fd.append('set_id', currentEditedSetId);
        fd.append('sessid', '<?=bitrix_sessid()?>');
        for (const key in changes) { fd.append('styles[' + key + ']', changes[key]); }
        fetch('mycompany_banner_ajax_save_banner.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(res => {
                if (res.success) { alert('Глобальные стили применены.'); renderEditorGrid(); } 
                else { alert('Ошибка: ' + (res.errors ? res.errors.join('\n') : '')); }
            });
    }

    popupGridContainer.addEventListener('dragstart', e => { if (e.target.classList.contains('banner-block')) { draggedSlot = e.target; e.target.classList.add('dragging'); } });
    popupGridContainer.addEventListener('dragover', e => { e.preventDefault(); const target = e.target.closest('.banner-block'); if (target && target !== draggedSlot) { document.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over')); target.classList.add('drag-over'); } });
    popupGridContainer.addEventListener('dragleave', e => { e.target.closest('.banner-block')?.classList.remove('drag-over'); });
    popupGridContainer.addEventListener('drop', e => {
        e.preventDefault();
        const dropTarget = e.target.closest('.banner-block');
        document.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over'));
        draggedSlot?.classList.remove('dragging');
        if (dropTarget && draggedSlot && dropTarget !== draggedSlot) {
            const fd = new FormData();
            fd.append('action', 'swap_blocks');
            fd.append('set_id', currentEditedSetId);
            fd.append('slot_index_1', draggedSlot.dataset.slotIndex);
            fd.append('slot_index_2', dropTarget.dataset.slotIndex);
            fd.append('sessid', '<?=bitrix_sessid()?>');
            fetch('mycompany_banner_ajax_save_banner.php', { method: 'POST', body: fd }).then(r => r.json()).then(res => {
                if (res.success) {
                    const setBanners = bannersData[currentEditedSetId];
                    const banner1 = setBanners.find(b => b.SLOT_INDEX == draggedSlot.dataset.slotIndex);
                    const banner2 = setBanners.find(b => b.SLOT_INDEX == dropTarget.dataset.slotIndex);
                    if (banner1 && banner2) {
                        [banner1.SLOT_INDEX, banner2.SLOT_INDEX] = [banner2.SLOT_INDEX, banner1.SLOT_INDEX];
                        [banner1.SORT, banner2.SORT] = [banner2.SORT, banner1.SORT];
                    }
                    renderEditorGrid();
                } else { alert('Ошибка смены порядка: ' + (res.errors ? res.errors.join('\n') : '')); }
            });
        }
        draggedSlot = null;
    });

    iblockSelect.addEventListener('change', () => loadSections(iblockSelect.value));
    sectionSelect.addEventListener('change', () => {
        if (!sectionSelect.value) return;
        fetch(`mycompany_banner_ajax_save_banner.php?action=get_section_data&section_id=${sectionSelect.value}&sessid=<?=bitrix_sessid()?>`).then(r=>r.json()).then(res => {
             if(res.success && currentSelectedBlockData) {
                Object.assign(currentSelectedBlockData, { TITLE: res.data.NAME, SUBTITLE: res.data.DESCRIPTION, IMAGE: res.data.PICTURE, LINK: res.data.SECTION_PAGE_URL });
                saveCurrentBlock();
             }
        });
    });

    function loadIblocks() {
        fetch(`mycompany_banner_ajax_save_banner.php?action=get_iblocks&sessid=<?=bitrix_sessid()?>`)
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    iblockSelect.innerHTML = '<option value="">- Выберите инфоблок -</option>';
                    res.data.forEach(iblock => {
                        iblockSelect.innerHTML += `<option value="${iblock.ID}">${iblock.NAME}</option>`;
                    });
                }
            });
    }

    function loadSections(iblockId) {
        if (!iblockId) {
            sectionSelect.innerHTML = '<option value="">- Сначала выберите инфоблок -</option>';
            return;
        }
        fetch(`mycompany_banner_ajax_save_banner.php?action=get_sections_tree&iblock_id=${iblockId}&sessid=<?=bitrix_sessid()?>`)
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    sectionSelect.innerHTML = '<option value="">- Выберите раздел -</option>';
                    res.data.forEach(section => {
                        const padding = '&nbsp;&nbsp;'.repeat(Math.max(0, section.DEPTH_LEVEL - 1));
                        sectionSelect.innerHTML += `<option value="${section.ID}">${padding}${section.NAME}</option>`;
                    });
                }
            });
    }
    function createSet() { createPopup.style.display = 'flex'; document.getElementById('newSetName').focus(); }
    function doCreate() {
        const nameInput = document.getElementById('newSetName');
        const autoFillCheckbox = document.getElementById('newSetAuto');
        const name = nameInput.value.trim();
        if (!name) {
            alert('Пожалуйста, введите название баннера.');
            nameInput.focus();
            return;
        }

        const fd = new FormData();
        fd.append('action', 'create_set');
        fd.append('name', name);
        fd.append('auto_fill', autoFillCheckbox.checked ? 'Y' : 'N');
        fd.append('sessid', '<?=bitrix_sessid()?>');

        doCreateBtn.disabled = true;

        fetch('mycompany_banner_ajax_save_banner.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    currentSets[res.data.set.ID] = res.data.set;
                    bannersData[res.data.set.ID] = res.data.banners;
                    createPopup.style.display = 'none';
                    nameInput.value = '';
                    // Перезагружаем страницу, чтобы перерисовать список. 
                    // Альтернатива - динамически добавить карточку, но перезагрузка проще и надежнее.
                    location.reload(); 
                    // После перезагрузки, можно было бы открывать редактор, но это усложнит код
                    // openEditor(res.data.set.ID);
                } else {
                    alert('Ошибка создания: ' + (res.errors ? res.errors.join('\\n') : 'Неизвестная ошибка.'));
                }
            })
            .finally(() => {
                doCreateBtn.disabled = false;
            });
    }
    function deleteSet(id, name, event) {
        event.stopPropagation();
        if (!confirm(`Вы уверены, что хотите удалить баннер "${name}"? Это действие нельзя отменить.`)) {
            return;
        }

        const fd = new FormData();
        fd.append('action', 'delete_set');
        fd.append('set_id', id);
        fd.append('sessid', '<?=bitrix_sessid()?>');

        fetch('mycompany_banner_ajax_save_banner.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    const card = document.querySelector(`.set-card[data-set-id='${id}']`);
                    if (card) {
                        card.style.transition = 'opacity 0.5s, transform 0.5s';
                        card.style.opacity = '0';
                        card.style.transform = 'scale(0.9)';
                        setTimeout(() => card.remove(), 500);
                    }
                    delete currentSets[id];
                    delete bannersData[id];
                } else {
                    alert('Ошибка удаления: ' + (res.errors ? res.errors.join('\\n') : 'Неизвестная ошибка.'));
                }
            });
    }
    document.addEventListener('DOMContentLoaded', () => { viewList.style.display = 'block'; viewEditor.style.display = 'none'; });
</script>

<?php require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php"); ?>