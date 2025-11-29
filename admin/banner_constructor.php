<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");

use Bitrix\Main\Loader;
use MyCompany\Banner\BannerTable;
use MyCompany\Banner\BannerSetTable;

Loader::includeModule("mycompany.banner");
Loader::includeModule("iblock");

$setId = (int)$_REQUEST['set_id'];
$set = BannerSetTable::getById($setId)->fetch();

// Fetch iblock sections for category mode
$sections = [];
$res = CIBlockSection::GetList(
    ['LEFT_MARGIN' => 'ASC'],
    ['ACTIVE' => 'Y', 'IBLOCK_ACTIVE' => 'Y'],
    false,
    ['ID', 'NAME', 'DEPTH_LEVEL', 'IBLOCK_NAME', 'SECTION_PAGE_URL', 'DESCRIPTION']
);
while($sec = $res->Fetch()) {
    $sections[$sec['ID']] = [
        'id' => $sec['ID'],
        'name' => str_repeat(' . ', $sec['DEPTH_LEVEL']) . $sec['NAME'] . ' (' . $sec['IBLOCK_NAME'] . ')',
        'title' => $sec['NAME'],
        'link' => $sec['SECTION_PAGE_URL'],
        'subtitle' => $sec['DESCRIPTION']
    ];
}


if (!$set) {
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
    ShowError("Набор не найден");
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
    die();
}

$APPLICATION->SetTitle("Конструктор баннера: " . htmlspecialcharsbx($set['NAME']));
$bannersRaw = BannerTable::getList(['filter' => ['=SET_ID' => $setId]])->fetchAll();
$banners = [];
foreach($bannersRaw as $b) $banners[$b['SLOT_INDEX']] = $b;

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
?>
<style>
    .construct-wrap { max-width: 1200px; margin: 20px auto; }
    .grid { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 15px; margin-top: 20px; }
    .slot { background: #fff; border: 2px dashed #ddd; border-radius: 8px; min-height: 200px; display: flex; align-items: center; justify-content: center; cursor: pointer; position: relative; overflow: hidden; transition: 0.2s; box-sizing: border-box; }
    .slot:hover { border-color: #999; transform: scale(1.01); }
    .slot.filled { border-style: solid; border-color: #eee; text-shadow: 0 1px 2px rgba(0,0,0,0.3); color: #fff; }
    .slot-placeholder { color:#ccc; font-size:18px; text-align: center; }

    /* GRID SIZES */
    .slot[data-i="1"], .slot[data-i="2"], .slot[data-i="3"], .slot[data-i="4"] { grid-column: span 2; height: 280px; }
    .slot[data-i="5"], .slot[data-i="6"], .slot[data-i="7"], .slot[data-i="8"] { grid-column: span 1; height: 180px; }

    .slot-content { text-align: center; z-index: 2; padding: 10px; }
    .slot-title { font-size: 20px; font-weight: bold; text-transform: uppercase; }
    .slot-desc { font-size: 14px; opacity: 0.9; }
    .slot-img { position: absolute; right: 10px; bottom: 10px; max-height: 80%; max-width: 40%; object-fit: contain; z-index: 1; }

    /* POPUP */
    .overlay { position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.6); z-index:9000; display: none; align-items: flex-start; justify-content: center; padding-top: 50px; }
    .popup { background: #fcfcfc; width: 600px; padding: 0; border-radius: 10px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
    .popup h3 { margin: 0; padding: 15px 25px; border-bottom: 1px solid #eee; }
    .popup form { padding: 25px; }
    .popup .form-row { margin-bottom: 15px; }
    .popup .form-row label { display: block; font-weight: bold; margin-bottom: 5px; color: #555; }
    .popup .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
    .popup .form-control[readonly] { background: #f0f0f0; cursor: not-allowed; }

    .popup-tabs { display: flex; border-bottom: 1px solid #eee; margin-bottom: 15px; background: #f0f0f0; }
    .popup-tab { padding: 10px 15px; cursor: pointer; color: #555; font-weight: bold; }
    .popup-tab.active { background: #fff; border-top: 2px solid #2e73b9; color: #000; }
    .popup-tab-content { display: none; }
    .popup-tab-content.active { display: block; }
</style>

<div class="construct-wrap">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h2>Блоки для баннера "<?=htmlspecialcharsbx($set['NAME'])?>"</h2>
        <a href="mycompany_banner_settings.php?lang=<?=LANGUAGE_ID?>" class="adm-btn">← Вернуться к списку баннеров</a>
    </div>
    <div class="grid" id="grid"></div>
</div>

<!-- POPUP -->
<div class="overlay" id="popup">
    <div class="popup">
        <h3>Редактирование блока</h3>
        <form id="editForm">
            <input type="hidden" name="slot_index" id="slotIndex">
            <input type="hidden" name="set_id" value="<?=$setId?>">
            <input type="hidden" name="action" value="save_slot">
            <input type="hidden" name="sessid" value="<?=bitrix_sessid()?>">

            <div class="popup-tabs">
                <div class="popup-tab active" data-tab="content">Контент</div>
                <div class="popup-tab" data-tab="visual">Визуал</div>
            </div>

            <!-- TAB CONTENT -->
            <div id="tab-content" class="popup-tab-content active">
                <div class="form-row" style="border-bottom: 1px solid #eee; padding-bottom: 15px;">
                    <label><input type="checkbox" id="isCategoryMode"> &nbsp;Режим категории (данные из раздела инфоблока)</label>
                    <div id="categoryWrapper" style="display:none; margin-top: 10px;">
                        <select name="category_id" id="categorySelect" class="form-control">
                            <option value="0">-- Выберите категорию --</option>
                            <?php foreach($sections as $s):
                                ?><option value="<?=$s['id']?>"><?=$s['name']?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                 <div class="form-row">
                    <label>Заголовок</label>
                    <input type="text" name="title" id="inpTitle" class="form-control">
                </div>
                <div class="form-row">
                    <label>Анонс</label>
                    <input type="text" name="subtitle" id="inpSubtitle" class="form-control">
                </div>
                <div class="form-row">
                    <label>Ссылка</label>
                    <input type="text" name="link" id="inpLink" class="form-control">
                </div>
            </div>

            <!-- TAB VISUAL -->
            <div id="tab-visual" class="popup-tab-content">
                <div class="form-row">
                    <label>Тип картинки</label>
                    <label><input type="radio" name="image_type" value="background" checked> Фон (картинка на весь блок)</label>
                    <label><input type="radio" name="image_type" value="icon"> Иконка (маленькая картинка)</label>
                </div>
                <div id="iconAlignWrapper" class="form-row" style="display:none;">
                    <label>Выравнивание иконки</label>
                    <select name="image_align" class="form-control">
                        <option value="left">Слева</option>
                        <option value="center" selected>По центру</option>
                        <option value="right">Справа</option>
                    </select>
                </div>
                <div class="form-row">
                    <label>Изображение</label>
                    <div class="popup-tabs">
                         <div class="popup-tab active" data-tab="img-file">Загрузить файл</div>
                         <div class="popup-tab" data-tab="img-url">Ссылка (URL)</div>
                    </div>
                    <div id="tab-img-file" class="popup-tab-content active"><input type="file" name="image_file" class="form-control"></div>
                    <div id="tab-img-url" class="popup-tab-content"><input type="text" name="image_url" placeholder="https://..." class="form-control"></div>
                </div>
                <div class="form-row">
                    <label>Цвет фона (если нет картинки)</label>
                    <input type="color" name="color" id="inpColor" class="form-control" style="height: 40px;">
                </div>
            </div>

            <div style="text-align: right; margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
                <button type="button" class="adm-btn" onclick="closePopup()">Отмена</button>
                <button type="submit" class="adm-btn adm-btn-save">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<script>
const banners = <?=CUtil::PhpToJSObject($banners)?>;
const sections = <?=CUtil::PhpToJSObject($sections)?>;
const grid = document.getElementById('grid');

function render() {
    grid.innerHTML = '';
    for(let i=1; i<=8; i++) {
        const b = banners[i];
        const el = document.createElement('div');
        el.className = 'slot';
        el.dataset.i = i;
        if(b) {
            el.classList.add('filled');
            el.style.backgroundColor = b.COLOR;
            if (b.IMAGE_TYPE === 'background' && b.IMAGE) {
                el.style.backgroundImage = `url(${b.IMAGE})`;
                el.style.backgroundSize = 'cover';
                el.style.backgroundPosition = 'center';
            }
            el.innerHTML = `<div class="slot-content">
                    <div class="slot-title">${b.TITLE || ''}</div>
                    <div class="slot-desc">${b.SUBTITLE || ''}</div>
                </div>
                ${(b.IMAGE_TYPE === 'icon' && b.IMAGE) ? `<img src="${b.IMAGE}" class="slot-img">` : ''}`;
        } else {
            el.innerHTML = '<div class="slot-placeholder">Пустой блок<br><small>Нажмите для настройки</small></div>';
        }
        el.onclick = () => openPopup(i);
        grid.appendChild(el);
    }
}

function openPopup(i) {
    const form = document.getElementById('editForm');
    form.reset();
    document.getElementById('slotIndex').value = i;
    
    const b = banners[i] || {};

    // Content
    const isCategory = !!(b.CATEGORY_ID && b.CATEGORY_ID > 0);
    form.querySelector('#isCategoryMode').checked = isCategory;
    form.querySelector('#categorySelect').value = b.CATEGORY_ID || 0;
    
    form.querySelector('#inpTitle').value = b.TITLE || '';
    form.querySelector('#inpSubtitle').value = b.SUBTITLE || '';
    form.querySelector('#inpLink').value = b.LINK || '';
    toggleCategoryMode(isCategory);

    // Visual
    form.querySelector('#inpColor').value = b.COLOR || '#ffffff';
    const imageType = b.IMAGE_TYPE || 'background';
    form.querySelector(`input[name="image_type"][value="${imageType}"]`).checked = true;
    toggleImageType(imageType);

    form.querySelector('select[name="image_align"]').value = b.IMAGE_ALIGN || 'center';

    document.getElementById('popup').style.display = 'flex';
}

function closePopup() { document.getElementById('popup').style.display = 'none'; }

function toggleCategoryMode(isCategory) {
    document.getElementById('categoryWrapper').style.display = isCategory ? 'block' : 'none';
    document.getElementById('inpTitle').readOnly = isCategory;
    document.getElementById('inpSubtitle').readOnly = isCategory;
    document.getElementById('inpLink').readOnly = isCategory;
}

function toggleImageType(type) {
    document.getElementById('iconAlignWrapper').style.display = type === 'icon' ? 'block' : 'none';
}

// Event Listeners
document.getElementById('isCategoryMode').addEventListener('change', e => {
    toggleCategoryMode(e.target.checked);
    if (!e.target.checked) {
        document.getElementById('categorySelect').value = 0;
    }
});

document.getElementById('categorySelect').addEventListener('change', e => {
    const sectionId = e.target.value;
    const section = sections[sectionId];
    if (section) {
        document.getElementById('inpTitle').value = section.title;
        document.getElementById('inpSubtitle').value = section.subtitle;
        document.getElementById('inpLink').value = section.link;
    }
});

document.querySelectorAll('input[name="image_type"]').forEach(radio => {
    radio.addEventListener('change', e => toggleImageType(e.target.value));
});

document.querySelectorAll('.popup-tab').forEach(tab => {
    tab.addEventListener('click', e => {
        const tabName = e.target.dataset.tab;
        const parent = e.target.parentElement;
        parent.querySelectorAll('.popup-tab').forEach(t => t.classList.remove('active'));
        e.target.classList.add('active');
        
        parent.nextElementSibling.querySelectorAll('.popup-tab-content').forEach(c => c.classList.remove('active'));
        const content = document.getElementById('tab-' + tabName);
        if(content) content.classList.add('active');
    });
});


document.getElementById('editForm').onsubmit = async function(e) {
    e.preventDefault();
    let fd = new FormData(this);
    if (!fd.get('category_id') > 0) {
        fd.set('category_id', '0');
    }
    let res = await fetch('mycompany_banner_ajax_save_banner.php', {method:'POST', body:fd});
    let data = await res.json();
    if(data.success) {
        banners[data.data.SLOT_INDEX] = data.data;
        render();
        closePopup();
    } else {
        alert('Ошибка: ' + (data.errors ? data.errors.join('\n') : 'Неизвестная ошибка'));
    }
};

render();
</script>
<?php require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php"); ?>