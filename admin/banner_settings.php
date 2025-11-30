<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");

use Bitrix\Main\Loader;
use MyCompany\Banner\BannerSetTable;
use MyCompany\Banner\BannerTable;

Loader::includeModule("mycompany.banner");

$APPLICATION->SetTitle("Наборы баннеров");

$sTableID = "tbl_banner_sets";
$oSort = new CAdminSorting($sTableID, "ID", "desc");
$lAdmin = new CAdminList($sTableID, $oSort);

// --- Data Fetching ---
$setsRaw = BannerSetTable::getList(['order' => ['ID' => 'DESC']]);
$setIds = [];
$sets = [];
while($row = $setsRaw->fetch()) {
    $sets[] = $row;
    $setIds[] = $row['ID'];
}

$bannersBySet = [];
if (!empty($setIds)) {
    $bannersRes = BannerTable::getList([
        'filter' => ['@SET_ID' => $setIds],
        'order' => ['SORT' => 'ASC', 'ID' => 'DESC']
    ]);
    while ($banner = $bannersRes->fetch()) {
        $bannersBySet[$banner['SET_ID']][] = $banner;
    }
}

$rsData = new CAdminResult(new CDBResult, $sTableID);
$rsData->InitFromArray($sets);
$lAdmin->AddHeaders([
    ['id' => 'ID', 'content' => 'ID', 'sort' => 'id', 'default' => true],
    ['id' => 'NAME', 'content' => 'Название', 'sort' => 'name', 'default' => true],
]);

while($arRes = $rsData->NavNext()):
    $row =& $lAdmin->AddRow($arRes['ID'], $arRes, "mycompany_banner_constructor.php?set_id=".$arRes['ID']."&lang=".LANG, "Перейти в конструктор");
    $row->AddField("ID", $arRes['ID']);
    $row->AddField("NAME", '<div onmouseenter="showPreview('.$arRes['ID'].', this)" onmouseleave="hidePreview()">'.$arRes['NAME'].'</div>');
    $arActions = [
        ["ICON"=>"edit", "TEXT"=>"Редактировать", "ACTION"=>$lAdmin->ActionRedirect("mycompany_banner_constructor.php?set_id=".$arRes['ID']."&lang=".LANG)],
    ];
    $row->AddActions($arActions);
endwhile;

$aContext = [['TEXT'=>"Добавить набор", 'LINK'=>"mycompany_banner_set_edit.php?lang=".LANG, "TITLE"=>"Добавить новый набор баннеров", "ICON"=>"btn_new"]];
$lAdmin->AddAdminContextMenu($aContext);

$lAdmin->CheckListMode();
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
$lAdmin->DisplayList();
?>

<style>
    #preview-popup {
        position: absolute;
        display: none;
        background: #fff;
        border: 1px solid #ccc;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        padding: 10px;
        z-index: 1000;
    }
    #preview-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 5px;
        width: 500px;
        transform: scale(0.4);
        transform-origin: top left;
    }
    .preview-slot {
        background-color: #eee;
        background-size: cover;
        background-position: center;
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        text-shadow: 0 1px 2px rgba(0,0,0,0.5);
        font-size: 24px;
    }
    .preview-slot.large { grid-column: span 2; height: 150px; }
    .preview-slot.small { grid-column: span 1; height: 100px; }
</style>

<div id="preview-popup"><div id="preview-grid"></div></div>

<script>
    const setsData = <?=json_encode($bannersBySet)?>;
    const popup = document.getElementById('preview-popup');

    function showPreview(setId, el) {
        const banners = setsData[setId] || [];
        const grid = document.getElementById('preview-grid');
        grid.innerHTML = '';

        for (let i = 0; i < 8; i++) {
            const b = banners[i];
            const slot = document.createElement('div');
            const sizeClass = i < 4 ? 'large' : 'small';
            slot.className = `preview-slot ${sizeClass}`;

            if (b) {
                slot.style.backgroundColor = b.COLOR || '#fff';
                if (b.IMAGE) {
                    slot.style.backgroundImage = `url(${b.IMAGE})`;
                }
                slot.innerHTML = `<div>${b.TITLE || ''}</div>`;
            } else {
                slot.innerHTML = `<span>${i+1}</span>`;
                slot.style.color = '#aaa';
            }
            grid.appendChild(slot);
        }

        const rect = el.getBoundingClientRect();
        popup.style.display = 'block';
        popup.style.left = (rect.right + 10) + 'px';
        popup.style.top = (window.scrollY + rect.top) + 'px';
    }

    function hidePreview() {
        popup.style.display = 'none';
    }
</script>

<?php require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php"); ?>
