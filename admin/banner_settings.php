<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use MyCompany\Banner\BannerSetTable;

$module_id = "mycompany.banner";
Loader::includeModule($module_id);
$APPLICATION->SetTitle("Наборы баннеров");

$request = Application::getInstance()->getContext()->getRequest();

// Обработка AJAX запроса на создание набора
if ($request->isPost() && $request->getPost('action') === 'create_set' && check_bitrix_sessid()) {
    $APPLICATION->RestartBuffer();
    $name = $request->getPost('name');
    if (empty($name)) {
        echo json_encode(['error' => 'Название не может быть пустым']);
        die();
    }
    
    $result = BannerSetTable::add(['NAME' => $name]);
    
    if ($result->isSuccess()) {
        echo json_encode(['success' => true, 'id' => $result->getId()]);
    } else {
        echo json_encode(['error' => $result->getErrorMessages()]);
    }
    die();
}


// Получаем все сеты
$sets = BannerSetTable::getList()->fetchAll();

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
?>

<!-- Стили -->
<style>
    .set-list-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .set-card { background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); cursor: pointer; transition: all 0.2s; padding: 20px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; }
    .set-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.12); }
    .set-card-name { font-weight: 600; font-size: 16px; }
    .set-card-id { font-size: 13px; color: #888; }
</style>

<div class="set-list-header">
    <h1>Наборы баннеров</h1>
    <button id="createNewSetBtn" class="adm-btn-save">Создать новый набор</button>
</div>

<div class="set-list" id="setList">
    <?php if (empty($sets)): ?>
        <p>Пока не создано ни одного набора баннеров.</p>
    <?php else: ?>
        <?php foreach ($sets as $set): ?>
        <div class="set-card"
             onclick="location.href='mycompany_banner_constructor.php?set_id=<?=$set['ID']?>&lang=<?=LANGUAGE_ID?>'"
        >
            <div>
                <div class="set-card-name"><?=htmlspecialcharsbx($set['NAME'])?></div>
                <div class="set-card-id">ID: <?=$set['ID']?></div>
            </div>
            <span class="adm-btn">Редактировать</span>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
    document.getElementById('createNewSetBtn').addEventListener('click', function(e) {
        e.preventDefault();
        
        const name = prompt('Введите название нового набора:', 'Новый набор');
        
        if (name) {
            const data = new FormData();
            data.append('action', 'create_set');
            data.append('name', name);
            data.append('sessid', BX.bitrix_sessid());

            fetch('', {
                method: 'POST',
                body: data
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    location.href = `mycompany_banner_constructor.php?set_id=${result.id}&lang=<?=LANGUAGE_ID?>`;
                } else {
                    alert('Ошибка: ' + (result.error || 'Неизвестная ошибка'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Произошла ошибка при создании набора.');
            });
        }
    });
</script>


<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
