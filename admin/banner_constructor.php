<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");

use Bitrix\Main\Loader;
use Bitrix\Main\Page\Asset;
use MyCompany\Banner\BannerTable;

$module_id = "mycompany.banner";
Loader::includeModule($module_id);
$APPLICATION->SetTitle("Визуальный конструктор баннеров");

// 1. Получаем баннеры и индексируем по слотам
$bannersRaw = BannerTable::getList()->fetchAll();
$banners = [];
foreach ($bannersRaw as $banner) {
    $banners[$banner['SLOT_INDEX']] = $banner;
}

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
?>

<!-- 2. Стили -->
<style>
    #banner-constructor { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
    .grid-container {
        display: grid;
        gap: 15px;
        grid-template-columns: repeat(6, 1fr);
        grid-template-rows: 200px 200px 140px;
    }
    .slot {
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.07);
        cursor: pointer;
        transition: all 0.2s;
        position: relative;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 15px;
    }
    .slot:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }

    .slot[data-slot-index='1'] { grid-column: 1 / 4; }
    .slot[data-slot-index='2'] { grid-column: 4 / 7; }
    .slot[data-slot-index='3'] { grid-column: 1 / 4; }
    .slot[data-slot-index='4'] { grid-column: 4 / 7; }
    .slot[data-slot-index='5'], .slot[data-slot-index='6'] { grid-column: span 2; }
    .slot[data-slot-index='7'], .slot[data-slot-index='8'], .slot[data-slot-index='9'], .slot[data-slot-index='10'] { grid-column: span 2; }
    .slot[data-slot-index='7'] { grid-column: 1 / 3; } /* Manual placement for wrap */
    .slot[data-slot-index='8'] { grid-column: 3 / 5; }
    .slot[data-slot-index='9'] { grid-column: 5 / 7; }
    .slot[data-slot-index='10'] { grid-column: 1 / 3; }


    .slot.empty { border: 2px dashed #ccc; color: #aaa; font-size: 24px; }
    .slot-content { text-align: center; color: #fff; text-shadow: 0 1px 3px rgba(0,0,0,0.3); }
    .slot-content-title { font-size: 1.5em; font-weight: bold; }
    .slot-content-subtitle { font-size: 0.9em; }
    .slot-img { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); max-height: 80%; max-width: 40%; object-fit: contain; }
    .slot.small .slot-img { display: none; } /* No image on small slots */

    /* Popup */
    .popup-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; display: none; }
    .popup-content { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #f5f9f9; padding: 30px; border-radius: 8px; width: 90%; max-width: 500px; z-index: 1001; }
    .popup-content h3 { margin-top: 0; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #555; }
    .form-group input { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
</style>

<!-- 3. HTML -->
<div id="banner-constructor">
    <h1>Конструктор баннеров (Сет #1)</h1>
    <div class="grid-container" id="grid-container"></div>
</div>

<div class="popup-overlay" id="edit-popup">
    <div class="popup-content">
        <h3 id="popup-title">Редактирование слота</h3>
        <form id="edit-form">
            <input type="hidden" name="slot_index" id="slot_index">
            <div class="form-group">
                <label for="title">Заголовок</label>
                <input type="text" name="title" id="title">
            </div>
            <div class="form-group">
                <label for="subtitle">Анонс</label>
                <input type="text" name="subtitle" id="subtitle">
            </div>
            <div class="form-group">
                <label for="link">Ссылка</label>
                <input type="text" name="link" id="link">
            </div>
            <div class="form-group">
                <label for="color">Цвет фона</label>
                <input type="color" name="color" id="color">
            </div>
            <div class="form-group">
                <label for="image">Картинка</label>
                <input type="file" name="image" id="image">
                <small>Если выбрано, заменит текущую картинку</small>
            </div>
            <button type="submit" class="adm-btn-save">Сохранить</button>
            <button type="button" id="close-popup">Закрыть</button>
        </form>
    </div>
</div>

<!-- 4. JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const bannersData = <?= CUtil::PhpToJSObject($banners) ?>;
        const grid = document.getElementById('grid-container');
        const popup = document.getElementById('edit-popup');
        const form = document.getElementById('edit-form');
        
        function renderGrid() {
            grid.innerHTML = '';
            for (let i = 1; i <= 10; i++) {
                const banner = bannersData[i];
                const isLarge = i <= 4;
                const slot = document.createElement('div');
                slot.className = `slot ${isLarge ? 'large' : 'small'}`;
                slot.dataset.slotIndex = i;

                if (banner) {
                    slot.style.backgroundColor = banner.COLOR;
                    let innerHTML = `<div class="slot-content">`;
                    if (banner.TITLE) innerHTML += `<div class="slot-content-title">${banner.TITLE}</div>`;
                    if (banner.SUBTITLE) innerHTML += `<div class="slot-content-subtitle">${banner.SUBTITLE}</div>`;
                    innerHTML += `</div>`;
                    if (isLarge && banner.IMAGE) {
                        innerHTML += `<img src="${banner.IMAGE}" class="slot-img">`;
                    }
                    slot.innerHTML = innerHTML;
                } else {
                    slot.classList.add('empty');
                    slot.innerHTML = '<span>+</span>';
                }
                grid.appendChild(slot);
            }
        }

        grid.addEventListener('click', function(e) {
            const slot = e.target.closest('.slot');
            if (slot) {
                const slotIndex = slot.dataset.slotIndex;
                const banner = bannersData[slotIndex] || {};
                
                document.getElementById('popup-title').innerText = `Редактирование слота #${slotIndex}`;
                document.getElementById('slot_index').value = slotIndex;
                document.getElementById('title').value = banner.TITLE || '';
                document.getElementById('subtitle').value = banner.SUBTITLE || '';
                document.getElementById('link').value = banner.LINK || '';
                document.getElementById('color').value = banner.COLOR || '#f5f5f5';
                document.getElementById('image').value = ''; // Сбрасываем поле файла

                popup.style.display = 'block';
            }
        });

        document.getElementById('close-popup').addEventListener('click', () => popup.style.display = 'none');
        popup.addEventListener('click', (e) => {
            if (e.target === popup) popup.style.display = 'none';
        });

        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(form);
            formData.append('sessid', '<?=bitrix_sessid()?>');

            try {
                const response = await fetch('/bitrix/admin/mycompany_banner_ajax_save_banner.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    bannersData[result.data.SLOT_INDEX] = result.data;
                    renderGrid();
                    popup.style.display = 'none';
                } else {
                    alert('Ошибка сохранения: ' + (result.errors || []).join('\n'));
                }
            } catch (err) {
                alert('Сетевая ошибка или ошибка сервера.');
                console.error(err);
            }
        });

        renderGrid();
    });
</script>

<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");

