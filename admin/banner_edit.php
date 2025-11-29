<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use MyCompany\Banner\BannerTable;

$module_id = "mycompany.banner";
Loader::includeModule($module_id);
$request = Application::getInstance()->getContext()->getRequest();
$id = (int)$request->get('id');

$APPLICATION->SetTitle($id > 0 ? "Редактирование баннера №{$id}" : "Добавление нового баннера");

$errors = [];
$fields = [];

// --- Обработка сохранения ---
if ($request->isPost() && ($request->get('save') || $request->get('apply')) && check_bitrix_sessid()) {
    $fields = $request->getPostList()->toArray();
    
    if (empty($fields['NAME'])) {
        $errors[] = 'Поле "Название (для админки)" обязательно для заполнения.';
    }

    if (empty($errors)) {
        $data = [
            'NAME' => $fields['NAME'],
            'TITLE' => $fields['TITLE'],
            'ANNOUNCEMENT' => $fields['ANNOUNCEMENT'],
            'IMAGE_LINK' => $fields['IMAGE_LINK'],
            'IMAGE_POSITION' => $fields['IMAGE_POSITION'],
            'LINK_URL' => $fields['LINK_URL'],
            'THEME_COLOR' => $fields['THEME_COLOR'],
        ];

        if ($id > 0) {
            $result = BannerTable::update($id, $data);
        } else {
            $result = BannerTable::add($data);
        }

        if ($result->isSuccess()) {
            $newId = $id > 0 ? $id : $result->getId();
            if ($request->get('save')) {
                LocalRedirect("/bitrix/admin/mycompany_banner_settings.php?lang=" . LANGUAGE_ID);
            } else {
                LocalRedirect("/bitrix/admin/mycompany_banner_edit.php?id={$newId}&lang=" . LANGUAGE_ID);
            }
        } else {
            $errors = $result->getErrorMessages();
        }
    }
} elseif ($id > 0) {
    // --- Загрузка данных для редактирования ---
    $banner = BannerTable::getById($id)->fetch();
    if ($banner) {
        $fields = $banner;
    } else {
        $errors[] = "Баннер с ID {$id} не найден.";
    }
}

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

// --- Стили ---
?>
<style>
    .settings-container { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; background: #f5f9f9; padding: 20px; border-radius: 4px; }
    .settings-card { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; max-width: 900px; }
    .form-group { margin-bottom: 20px; }
    .form-label { display: block; font-weight: 600; margin-bottom: 10px; color: #555; font-size: 14px; }
    .text-input, .select-input { width: 100%; padding: 10px 15px; border-radius: 4px; border: 1px solid #ddd; box-sizing: border-box; }
    .color-input-wrapper { display: flex; align-items: center; gap: 10px; }
    .btn-submit { background: #2bc647; color: #fff; border: none; padding: 10px 25px; font-size: 14px; font-weight: bold; border-radius: 30px; cursor: pointer; }
    .btn-back { background: #ccc; color: #333; text-decoration: none; padding: 10px 25px; font-size: 14px; font-weight: bold; border-radius: 30px; }
    .error-message { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
</style>

<?php if (!empty($errors)): ?>
    <div class="error-message">
        <?=implode('<br>', $errors)?>
    </div>
<?php endif; ?>

<a href="/bitrix/admin/mycompany_banner_settings.php?lang=<?=LANGUAGE_ID?>" class="btn-back" style="margin-bottom: 20px; display: inline-block;">Назад к списку</a>

<form method="post" action="<?=$APPLICATION->GetCurPage()?>?id=<?=$id?>&lang=<?=LANGUAGE_ID?>">
    <?=bitrix_sessid_post()?>
    <div class="settings-container">
        <div class="settings-card">

            <div class="form-group">
                <label class="form-label">Название (для админки) <span style="color:red">*</span></label>
                <input type="text" name="NAME" value="<?=htmlspecialcharsbx($fields['NAME'] ?? '')?>" class="text-input">
            </div>

            <div class="form-group">
                <label class="form-label">Заголовок на сайте</label>
                <input type="text" name="TITLE" value="<?=htmlspecialcharsbx($fields['TITLE'] ?? '')?>" class="text-input">
            </div>

            <div class="form-group">
                <label class="form-label">Анонс</label>
                <input type="text" name="ANNOUNCEMENT" value="<?=htmlspecialcharsbx($fields['ANNOUNCEMENT'] ?? '')?>" class="text-input">
            </div>

            <div class="form-group">
                <label class="form-label">Ссылка на картинку</label>
                <input type="text" name="IMAGE_LINK" value="<?=htmlspecialcharsbx($fields['IMAGE_LINK'] ?? '')?>" class="text-input">
            </div>

            <div class="form-group">
                <label class="form-label">Расположение картинки</label>
                <select name="IMAGE_POSITION" class="select-input">
                    <option value="left" <?=($fields['IMAGE_POSITION'] ?? 'left') == 'left' ? 'selected' : ''?>>Слева</option>
                    <option value="right" <?=($fields['IMAGE_POSITION'] ?? '') == 'right' ? 'selected' : ''?>>Справа</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Ссылка при клике</label>
                <input type="text" name="LINK_URL" value="<?=htmlspecialcharsbx($fields['LINK_URL'] ?? '/')?>" class="text-input">
            </div>

            <div class="form-group">
                <label class="form-label">Цвет фона</label>
                <div class="color-input-wrapper">
                    <input type="color" name="THEME_COLOR_PICKER" value="<?=htmlspecialcharsbx($fields['THEME_COLOR'] ?? '#f5f5f5')?>" onchange="document.getElementsByName('THEME_COLOR')[0].value = this.value">
                    <input type="text" name="THEME_COLOR" value="<?=htmlspecialcharsbx($fields['THEME_COLOR'] ?? '#f5f5f5')?>" class="text-input" style="max-width: 150px;">
                </div>
            </div>

            <br>
            <input type="submit" name="save" value="Сохранить и вернуться" class="btn-submit">
            <input type="submit" name="apply" value="Применить" class="btn-submit" style="background-color: #0d6efd;">

        </div>
    </div>
</form>

<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
