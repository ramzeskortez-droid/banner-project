<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");

use Bitrix\Main\Loader;
use MyCompany\Banner\BannerTable;

$module_id = "mycompany.banner";
Loader::includeModule($module_id);
$APPLICATION->SetTitle("Список баннеров");

// Получаем все баннеры
$banners = BannerTable::getList()->fetchAll();

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
?>

<!-- Стили -->
<style>
    .banner-list-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .btn-add { background: #2bc647; color: #fff; text-decoration: none; padding: 10px 25px; font-size: 14px; font-weight: bold; border-radius: 30px; }

    .banner-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
    .banner-card { background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); cursor: pointer; transition: all 0.2s; padding: 20px; }
    .banner-card:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.12); }
    .banner-card-name { font-weight: 600; font-size: 16px; margin-bottom: 5px; }
    .banner-card-id { font-size: 13px; color: #888; }
    .banner-card-color-indicator { width: 20px; height: 20px; border-radius: 50%; display: inline-block; vertical-align: middle; margin-right: 10px; border: 1px solid #eee; }

    /* Стили для всплывающего превью */
    #banner-preview {
        display: none;
        position: fixed;
        width: 350px;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        z-index: 1000;
        pointer-events: none; /* Чтобы не мешал наведению */

        /* Дублируем стили компонента для схожести */
        display: none;
        justify-content: space-between;
        align-items: center;
        color: #333;
    }
    #banner-preview .preview-content { max-width: 60%; }
    #banner-preview .preview-title { font-size: 18px; font-weight: 800; margin-bottom: 5px; color: #222; }
    #banner-preview .preview-announcement { font-size: 12px; color: #666; }
    #banner-preview .preview-image { max-width: 35%; }
    #banner-preview .preview-image img { max-width: 100%; max-height: 80px; object-fit: contain; }
</style>

<div class="banner-list-header">
    <h1>Список баннеров</h1>
    <a href="/bitrix/admin/mycompany_banner_edit.php?lang=<?=LANGUAGE_ID?>" class="btn-add">Добавить баннер</a>
</div>

<div class="banner-grid" id="bannerGrid">
    <?php foreach ($banners as $banner): ?>
        <div class="banner-card" 
             onclick="location.href='/bitrix/admin/mycompany_banner_edit.php?id=<?=$banner['ID']?>&lang=<?=LANGUAGE_ID?>'"
             data-name="<?=htmlspecialcharsbx($banner['NAME'])?>"
             data-title="<?=htmlspecialcharsbx($banner['TITLE'])?>"
             data-announcement="<?=htmlspecialcharsbx($banner['ANNOUNCEMENT'])?>"
             data-color="<?=htmlspecialcharsbx($banner['THEME_COLOR'])?>"
             data-image="<?=htmlspecialcharsbx($banner['IMAGE_LINK'])?>"
             data-position="<?=htmlspecialcharsbx($banner['IMAGE_POSITION'])?>"
        >
            <div class="banner-card-name">
                <span class="banner-card-color-indicator" style="background-color: <?=htmlspecialcharsbx($banner['THEME_COLOR'])?>;"></span>
                <?=htmlspecialcharsbx($banner['NAME'])?>
            </div>
            <div class="banner-card-id">ID: <?=$banner['ID']?> | Заголовок: <?=htmlspecialcharsbx($banner['TITLE'])?></div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Скрытый блок для превью -->
<div id="banner-preview">
    <div class="preview-content">
        <div class="preview-title"></div>
        <div class="preview-announcement"></div>
    </div>
    <div class="preview-image">
        <img src="" alt="preview">
    </div>
</div>

<!-- Скрипты -->
<script>
    const bannerGrid = document.getElementById('bannerGrid');
    const preview = document.getElementById('banner-preview');

    bannerGrid.addEventListener('mouseover', function(event) {
        const card = event.target.closest('.banner-card');
        if (!card) return;

        // 1. Получаем данные
        const data = card.dataset;

        // 2. Заполняем превью
        preview.style.backgroundColor = data.color;
        preview.querySelector('.preview-title').textContent = data.title;
        preview.querySelector('.preview-announcement').textContent = data.announcement;
        
        const img = preview.querySelector('.preview-image img');
        if (data.image) {
            img.src = data.image;
            img.style.display = 'block';
        } else {
            img.style.display = 'none';
        }

        // 3. Показываем превью
        preview.style.display = 'flex';
        preview.style.flexDirection = data.position === 'right' ? 'row-reverse' : 'row';
    });

    bannerGrid.addEventListener('mousemove', function(event) {
        // 4. Позиционируем превью у курсора
        const x = event.clientX;
        const y = event.clientY;
        preview.style.left = x + 20 + 'px';
        preview.style.top = y + 20 + 'px';
    });

    bannerGrid.addEventListener('mouseout', function(event) {
        const card = event.target.closest('.banner-card');
        if (!card) return;
        
        // 5. Скрываем превью
        preview.style.display = 'none';
    });
</script>

<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");