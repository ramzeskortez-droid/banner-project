<?php
/**
 * Banner Constructor Interface.
 * This page provides a visual grid editor for arranging and editing Blocks (slots) within a single Banner (Set).
 *
 * Terminology Mapping:
 * - UI "Баннер" (Banner)    <=> DB `mycompany_banner_set` (BannerSetTable)
 * - UI "Блок" (Block)      <=> DB `mycompany_banner` (BannerTable)
 */

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
use Bitrix\Main\Loader;
use MyCompany\Banner\BannerTable;
use MyCompany\Banner\BannerSetTable;

// --- Pre-loading data for JS ---
Loader::includeModule("mycompany.banner");
Loader::includeModule("iblock");

// Get the current Banner (Set) ID from request
$setId = (int)($_REQUEST['set_id'] ?? 0);
if ($setId <= 0) {
    // Redirect or show error if no ID is provided
    LocalRedirect("mycompany_banner_settings.php?lang=".LANGUAGE_ID);
}
$set = BannerSetTable::getById($setId)->fetch();
$APPLICATION->SetTitle("Конструктор баннера: " . ($set['NAME'] ?? '...'));

// Fetch all IBlocks and their Sections to use in the "auto-fill" dropdowns in the popup.
$iblocks = [];
$sectionsByIblock = [];
$secRes = \CIBlockSection::GetList(
    ['LEFT_MARGIN'=>'ASC'],
    ['ACTIVE'=>'Y', 'GLOBAL_ACTIVE'=>'Y'],
    false,
    ['ID','NAME','DEPTH_LEVEL','IBLOCK_ID','SECTION_PAGE_URL','DESCRIPTION', 'PICTURE', 'IBLOCK_SECTION_ID']
);
while($sec = $secRes->GetNext()) {
    $sid = $sec['IBLOCK_ID'];
    if (!isset($iblocks[$sid])) {
        $ibRes = \CIBlock::GetByID($sid)->GetNext();
        $iblocks[$sid] = $ibRes['NAME'];
    }
    $sectionsByIblock[$sid][] = [
        'id' => $sec['ID'],
        'name' => str_repeat('. ', $sec['DEPTH_LEVEL'] - 1) . $sec['NAME'],
        'data' => [
            'title' => $sec['NAME'],
            'link' => $sec['SECTION_PAGE_URL'],
            'subtitle' => $sec['DESCRIPTION'] ? strip_tags($sec['DESCRIPTION']) : '',
            'image' => $sec['PICTURE'] ? \CFile::GetPath($sec['PICTURE']) : ''
        ]
    ];
}

// Fetch all existing Blocks (Banners) for this Set and key them by their slot index for easy access in JS.
$bannersRaw = BannerTable::getList(['filter' => ['=SET_ID' => $setId], 'order' => ['SORT' => 'ASC', 'ID' => 'ASC']])->fetchAll();
$banners = [];
foreach($bannersRaw as $b) {
    $banners[$b['SLOT_INDEX']] = $b;
}

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
?>

<style>
    /* Layout Fix: Use 100% width and box-sizing to prevent overflow when Bitrix menu is open */
    .construct-wrap {
        width: 100%;
        padding: 20px;
        box-sizing: border-box;
        margin: 0 auto;
        max-width: 1600px; /* Optional: limit max width on very large screens */
    }
    .grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 15px;
        width: 100%;
        box-sizing: border-box;
    }
    .construct-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }


    /* Visual styles for slots */
    .slot {
        position: relative;
        background-color: #f0f3f5;
        border: 2px dashed #cdd5db;
        border-radius: 8px;
        overflow: hidden;
        background-size: cover;
        background-position: center;
        transition: transform 0.2s, box-shadow 0.2s;
        cursor: pointer;
    }
    .slot:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.15);
        z-index: 50;
        border-color: #a9b7c4;
    }

    /* Grid layout definition */
    .slot[data-i="1"], .slot[data-i="2"], .slot[data-i="3"], .slot[data-i="4"] { grid-column: span 2; height: 300px; }
    .slot[data-i="5"], .slot[data-i="6"], .slot[data-i="7"], .slot[data-i="8"] { grid-column: span 1; height: 200px; }
    
    /* Content inside a slot */
    .slot-content { height: 100%; display: flex; flex-direction: column; justify-content: center; padding: 20px; box-sizing: border-box; }
    .b-text-wrapper { display: inline-block; padding: 10px 15px; border-radius: 6px; transition: background-color 0.1s linear; }
    .b-title { font-weight: bold; margin-bottom: 5px; }
    .slot-placeholder { text-align: center; color: #a9b7c4; width: 100%; display:flex; align-items:center; justify-content:center; height:100%; flex-direction:column; font-weight: bold; }
    .slot-placeholder small { font-weight: normal; margin-top: 5px; }

    /* Text alignment utilities */
    .text-left { align-items: flex-start; text-align: left; }
    .text-center { align-items: center; text-align: center; }
    .text-right { align-items: flex-end; text-align: right; }

    /* Popups and Overlays */
    .overlay { position: fixed; top:0; left:0; right:0; bottom:0; background: rgba(0,0,0,0.6); z-index: 10100; display: none; align-items: center; justify-content: center; }
    #adjusterOverlay { z-index: 10200; }
    .popup { background: #fdfdfd; width: 800px; max-height: 95vh; display: flex; flex-direction: column; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
    .popup-header { padding: 15px 25px; border-bottom: 1px solid #e0e0e0; display: flex; justify-content: space-between; align-items: center; }
    .popup-body { padding: 20px; overflow-y: auto; flex: 1; display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .popup-footer { padding: 15px 25px; background: #f7f7f7; text-align: right; border-top: 1px solid #e0e0e0; border-radius: 0 0 8px 8px; }

    /* Form elements */
    .settings-group { border: 1px solid #d3eaff; margin-bottom: 20px; border-radius: 6px; background: #fff; }
    .group-title { background: #eaf6ff; color: #0b66c2; padding: 12px 15px; font-weight: bold; border-bottom: 1px solid #d3eaff; margin: 0 0 15px 0; font-size: 13px; }
    .group-content { padding: 0 15px 15px; }
    .form-row { margin-bottom: 15px; }
    .form-row:last-child { margin-bottom: 0; }
    .form-row label { display: block; font-size: 13px; font-weight: 600; color: #555; margin-bottom: 5px; }
    .form-control { width: 100%; box-sizing: border-box; } /* Let Bitrix styles handle the rest */
    textarea.form-control { min-height: 80px; resize: vertical; }

    /* Other styles remain the same */

</style>

<div class="construct-wrap">
    <div class="construct-header">
        <h2>Сетка блоков</h2>
        <a href="mycompany_banner_settings.php?lang=<?=LANGUAGE_ID?>" class="adm-btn">← К списку баннеров</a>
    </div>

    <!-- The main grid where blocks are displayed -->
    <div class="grid" id="grid">
        <!-- Grid will be rendered by JavaScript -->
    </div>
</div>

<!-- Main editing popup for a block -->
<div class="overlay" id="popup">
    <div class="popup" style="width: 850px;">
        <div class="popup-header">
            <h3 style="margin:0" id="popupTitle">Настройка блока</h3>
            <span style="cursor:pointer; font-size:20px; color:#999;" onclick="closePopup()">✕</span>
        </div>
        <form id="editForm">
            <div class="popup-body">
                <!-- Left Column -->
                <div>
                    <div class="settings-group">
                        <div class="group-title">Источник данных (Автозаполнение)</div>
                        <div class="group-content" style="padding-top: 15px;">
                            <div class="form-row">
                                <label>Инфоблок</label>
                                <select id="iblockSelect" class="form-control" onchange="renderCategories(this.value)">
                                    <option value="0">-- Не выбрано --</option>
                                    <?php foreach($iblocks as $id => $name): ?>
                                        <option value="<?=$id?>"><?=htmlspecialcharsbx($name)?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-row">
                                <label>Раздел</label>
                                <select id="catSelect" name="category_id" class="form-control" disabled>
                                    <option value="0">-- Сначала выберите инфоблок --</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="settings-group">
                        <div class="group-title">Контент блока</div>
                        <div class="group-content" style="padding-top: 15px;">
                            <div class="form-row"><label>Заголовок</label><input type="text" name="title" id="inpTitle" class="form-control"></div>
                            <div class="form-row"><label>Анонс</label><textarea name="subtitle" id="inpSubtitle" class="form-control" rows="3"></textarea></div>
                            <div class="form-row"><label>Ссылка</label><input type="text" name="link" id="inpLink" class="form-control"></div>
                            <div class="form-row"><label>Сортировка</label><input type="number" name="sort" id="inpSort" class="form-control"></div>
                        </div>
                    </div>
                </div>
                <!-- Right Column -->
                <div>
                    <div class="settings-group">
                        <div class="group-title">Оформление</div>
                         <div class="group-content" style="padding-top: 15px;">
                            <div class="form-row"><label>Фон (URL или файл)</label>
                                <input type="text" name="image_url" id="inpImgUrl" class="form-control" placeholder="URL изображения">
                                <input type="file" name="image_file" id="inpFile" class="form-control" style="margin-top: 5px;">
                            </div>
                             <div class="form-row"><label>Цвет фона (если нет картинки)</label><input type="color" name="color" id="inpColor" class="form-control" style="height: 38px;"></div>
                             <div class="form-row"><label>Выравнивание текста</label>
                                 <select name="text_align" id="inpTextAlign" class="form-control">
                                     <option value="left">Слева</option>
                                     <option value="center" selected>По центру</option>
                                     <option value="right">Справа</option>
                                 </select>
                             </div>
                             <div class="form-row">
                                <label for="inpTextColor">Цвет текста</label>
                                <input type="color" name="text_color" id="inpTextColor" value="#000000" class="form-control" style="height: 38px;">
                            </div>
                             <div class="form-row" style="display:flex; gap: 10px;">
                                 <div style="flex: 1;"><label>Размер заголовка (px)</label><input type="number" name="title_font_size" id="inpTitleSize" class="form-control"></div>
                                 <div style="flex: 1;"><label>Размер анонса (px)</label><input type="number" name="subtitle_font_size" id="inpSubSize" class="form-control"></div>
                             </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="popup-footer">
                <!-- Hidden fields for the form -->
                <input type="hidden" name="slot_index" id="slotIndex">
                <input type="hidden" name="set_id" value="<?=$setId?>">
                <input type="hidden" name="action" value="save_slot">
                <input type="hidden" name="sessid" value="<?=bitrix_sessid()?>">
                <button type="submit" class="adm-btn adm-btn-save">Сохранить</button>
            </div>
        </form>
    </div>
</div>


<script>
// Data passed from PHP
let blocks = <?=CUtil::PhpToJSObject($banners)?>; // Note: PHP $banners are JS `blocks`
let bannerSettings = <?=CUtil::PhpToJSObject($set)?>;
const sectionsData = <?=CUtil::PhpToJSObject($sectionsByIblock)?>;

// A flat map for quick section data lookup by ID
const allSectionsFlat = Object.values(sectionsData).flat().reduce((acc, s) => {
    acc[s.id] = s.data;
    return acc;
}, {});

const grid = document.getElementById('grid');

/**
 * Renders the entire grid of 8 blocks.
 */
function renderGrid() {
    grid.innerHTML = '';
    const sortedBlocks = Object.values(blocks).sort((a,b) => (parseInt(a.SORT) || 500) - (parseInt(b.SORT) || 500));
    
    // Create a map of existing blocks by slot_index
    const blockMap = sortedBlocks.reduce((acc, block) => {
        acc[block.SLOT_INDEX] = block;
        return acc;
    }, {});

    for(let i = 1; i <= 8; i++) {
        const block = blockMap[i];
        const el = document.createElement('div');
        el.className = 'slot';
        el.dataset.slotIndex = i;
        el.dataset.i = i; // For CSS grid-column rules

        if (block) {
            // This slot is filled, render its data
            el.style.backgroundColor = block.COLOR || '#fff';
            if(block.IMAGE) {
                el.style.backgroundImage = `url(${block.IMAGE})`;
            }
            
            const content = document.createElement('div');
            content.className = `slot-content text-${block.TEXT_ALIGN || 'center'}`;
            content.style.color = block.TEXT_COLOR || '#000000';
            
            const wrapper = document.createElement('div');
            wrapper.className = 'b-text-wrapper';
            
            let innerHTML = '';
            if (block.TITLE) innerHTML += `<div class="b-title" style="font-size: ${block.TITLE_FONT_SIZE || '18px'};">${block.TITLE}</div>`;
            if (block.SUBTITLE) innerHTML += `<div class="b-sub" style="font-size: ${block.SUBTITLE_FONT_SIZE || '14px'};">${block.SUBTITLE}</div>`;
            wrapper.innerHTML = innerHTML;

            content.appendChild(wrapper);
            el.appendChild(content);
            el.onclick = () => openEditPopup(i);
        } else {
            // This slot is empty, show a placeholder
            el.innerHTML = `<div class="slot-placeholder">Блок #${i}<br><small>Настроить</small></div>`;
            el.onclick = () => openEditPopup(i);
        }
        grid.appendChild(el);
    }
}

/**
 * Populates the category select dropdown for a given IBlock ID.
 * @param {string} ibId - The IBlock ID.
 */
function renderCategories(ibId) {
    const catSelect = document.getElementById('catSelect');
    catSelect.innerHTML = '<option value="0">-- Не выбрано --</option>';
    catSelect.disabled = true;
    
    if (ibId && sectionsData[ibId]) {
        catSelect.disabled = false;
        sectionsData[ibId].forEach(sec => {
            const opt = document.createElement('option');
            opt.value = sec.id;
            opt.innerHTML = sec.name;
            if (sec.name.startsWith('.')) {
                opt.style.fontWeight = 'bold';
            }
            catSelect.appendChild(opt);
        });
    }
}

/**
 * Opens the popup to edit a block. Can be an existing or a new one.
 * @param {number} slotIndex - The index of the slot (1-8).
 */
function openEditPopup(slotIndex) {
    const form = document.getElementById('editForm');
    form.reset();
    
    const blockData = blocks[slotIndex] || {};
    
    document.getElementById('popupTitle').innerText = `Настройка блока #${slotIndex}`;
    
    // Set hidden fields
    form.slot_index.value = slotIndex;

    // Populate form fields
    form.title.value = blockData.TITLE || '';
    form.subtitle.value = blockData.SUBTITLE || '';
    form.link.value = blockData.LINK || '';
    form.sort.value = blockData.SORT || (slotIndex * 10);
    form.text_align.value = blockData.TEXT_ALIGN || 'center';
    form.color.value = blockData.COLOR || '#ffffff';
    form.image_url.value = blockData.IMAGE || '';
    form.text_color.value = blockData.TEXT_COLOR || '#000000';
    form.title_font_size.value = parseInt(blockData.TITLE_FONT_SIZE) || 22;
    form.subtitle_font_size.value = parseInt(blockData.SUBTITLE_FONT_SIZE) || 14;

    // Auto-select IBlock/Section if a category_id is set
    document.getElementById('iblockSelect').value = 0;
    renderCategories(0);
    if (blockData.CATEGORY_ID) {
        for (const ibId in sectionsData) {
            const section = sectionsData[ibId].find(s => s.id == blockData.CATEGORY_ID);
            if (section) {
                document.getElementById('iblockSelect').value = ibId;
                renderCategories(ibId);
                document.getElementById('catSelect').value = blockData.CATEGORY_ID;
                break;
            }
        }
    }
    
    document.getElementById('popup').style.display = 'flex';
}

function closePopup() {
    document.getElementById('popup').style.display = 'none';
}

// --- Event Listeners ---

// Auto-fill form fields when a section is selected
document.getElementById('catSelect').addEventListener('change', function() {
    const secId = this.value;
    const data = allSectionsFlat[secId];
    if(data) {
        document.getElementById('inpTitle').value = data.title;
        document.getElementById('inpSubtitle').value = data.subtitle;
        document.getElementById('inpLink').value = data.link;
        if(data.image) document.getElementById('inpImgUrl').value = data.image;
    }
});

// Handle form submission
document.getElementById('editForm').onsubmit = async function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    try {
        const response = await fetch('mycompany_banner_ajax_save_banner.php', { method: 'POST', body: formData });
        const result = await response.json();

        if (result.success && result.data) {
            // Update local data and re-render
            blocks[result.data.SLOT_INDEX] = result.data;
            renderGrid();
            closePopup();
        } else {
            alert('Ошибка сохранения: ' + (result.errors ? result.errors.join('\n') : 'Неизвестная ошибка.'));
        }
    } catch (error) {
        alert('Сетевая ошибка при сохранении.');
        console.error(error);
    }
};

// --- Initial Render ---
renderGrid();

</script>
<?php require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php"); ?>