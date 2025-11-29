<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
use Bitrix\Main\Loader;
use MyCompany\Banner\BannerSetTable;
use Bitrix\Main\Application;

$module_id = "mycompany.banner";
Loader::includeModule($module_id);
$APPLICATION->SetTitle("Ваши наборы баннеров");

$request = Application::getInstance()->getContext()->getRequest();

if ($request->isPost() && $request->getPost('action') === 'create_set' && check_bitrix_sessid()) {
    $APPLICATION->RestartBuffer();
    $name = $request->getPost('name');
    $res = BannerSetTable::add(['NAME' => $name ?: 'Новый набор']);
    echo json_encode(['success' => $res->isSuccess(), 'id' => $res->getId()]);
    die();
}

$sets = BannerSetTable::getList()->fetchAll();
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
?>
<style>
    .sets-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; margin-top: 20px; }
    .set-card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); cursor: pointer; transition: 0.2s; text-align: center; border: 1px solid #eee; }
    .set-card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); border-color: #2bc647; }
    .set-name { font-size: 18px; font-weight: bold; margin-bottom: 10px; color: #333; }
    .set-id { color: #999; font-size: 12px; }
    .btn-create { background: #2bc647; color: #fff; border: none; padding: 12px 25px; border-radius: 30px; font-weight: bold; cursor: pointer; font-size: 14px; }
</style>

<div style="text-align: right;">
    <button class="btn-create" onclick="createSet()">+ Создать новый баннер</button>
</div>

<div class="sets-grid">
    <?php foreach($sets as $set): ?>
        <div class="set-card" onclick="location.href='mycompany_banner_constructor.php?set_id=<?=$set['ID']?>&lang=<?=LANGUAGE_ID?>'">
            <div class="set-name"><?=htmlspecialcharsbx($set['NAME'])?></div>
            <div class="set-id">ID набора: <?=$set['ID']?></div>
        </div>
    <?php endforeach; ?>
</div>

<script>
function createSet() {
    let name = prompt("Введите название для нового баннера:");
    if (name) {
        let fd = new FormData();
        fd.append('action', 'create_set');
        fd.append('name', name);
        fd.append('sessid', '<?=bitrix_sessid()?>');
        fetch('', {method: 'POST', body: fd})
            .then(r => r.json())
            .then(res => {
                if(res.success) location.href = 'mycompany_banner_constructor.php?set_id='+res.id+'&lang=<?=LANGUAGE_ID?>';
            });
    }
}
</script>
<?php require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php"); ?>