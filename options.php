<?php
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\HttpApplication;
use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;

$request = HttpApplication::getInstance()->getContext()->getRequest();
$module_id = htmlspecialcharsbx($request["mid"] != "" ? $request["mid"] : $request["id"]);

Loader::includeModule($module_id);

if ($request->isPost() && $request["Update"] && check_bitrix_sessid()) {
    // Здесь будет сохранение настроек
}

$aTabs = array(
    array(
        "DIV" => "edit1",
        "TAB" => "Настройки",
        "ICON" => "ib_settings",
        "TITLE" => "Настройки модуля баннеров"
    ),
);
$tabControl = new CAdminTabControl("tabControl", $aTabs);

$tabControl->Begin();
?>
<form method="post" action="<?echo $APPLICATION->GetCurPage()?>?mid=<?=htmlspecialcharsbx($module_id)?>&lang=<?=LANGUAGE_ID?>">
    <?php
    $tabControl->BeginNextTab();
    ?>
    <tr>
        <td width="40%">Тестовая настройка:</td>
        <td width="60%">
            <input type="text" name="test_field" value="Работает!">
        </td>
    </tr>
    <?php
    $tabControl->Buttons();
    ?>
    <input type="submit" name="Update" value="Сохранить" class="adm-btn-save">
    <?=bitrix_sessid_post();?>
</form>
<?php
$tabControl->End();