<?
/** @global CMain $APPLICATION */
use Bitrix\Main;
use Bitrix\Sale\Internals;

require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/sale/prolog.php');
Main\Loader::includeModule('sale');

$saleModulePermissions = $APPLICATION->GetGroupRight('sale');
$readOnly = ($saleModulePermissions < 'W');
if ($readOnly)
	$APPLICATION->AuthForm(GetMessage("ACCESS_DENIED"));

if ($ex = $APPLICATION->GetException())
{
	require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
	ShowError($ex->GetString());
	require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
	die();
}

IncludeModuleLangFile(__FILE__);

if (!Main\Loader::includeModule('catalog'))
{
	require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
	ShowError(GetMessage('BX_SALE_DISCOUNT_EDIT_ERR_MODULE_CATALOG_IS_ABSENT'));
	require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
	die();
}

$tabList = array(
	array('DIV' => 'edit1', 'ICON' => 'sale', 'TAB' => GetMessage("BT_SALE_DISCOUNT_EDIT_TAB_NAME_COMMON"), 'TITLE' => GetMessage("BT_SALE_DISCOUNT_EDIT_TAB_TITLE_COMMON")),
	array('DIV' => 'edit3', 'ICON' => 'sale', 'TAB' => GetMessage("BT_SALE_DISCOUNT_EDIT_TAB_NAME_ACTIONS"), 'TITLE' => GetMessage("BT_CAT_DISCOUNT_EDIT_TAB_TITLE_ACTIONS")),
	array('DIV' => 'edit2', 'ICON' => 'sale', 'TAB' => GetMessage("BT_SALE_DISCOUNT_EDIT_TAB_NAME_GROUPS"), 'TITLE' => GetMessage("BT_SALE_DISCOUNT_EDIT_TAB_TITLE_GROUPS")),
	array('DIV' => 'edit5', 'ICON' => 'sale', 'TAB' => GetMessage('BX_SALE_DISCOUNT_EDIT_TAB_NAME_COUPONS'), 'TITLE' => GetMessage('BX_SALE_DISCOUNT_EDIT_TAB_TITLE_COUPONS')),
	array('DIV' => 'edit4', 'ICON' => 'sale', 'TAB' => GetMessage("BT_SALE_DISCOUNT_EDIT_TAB_NAME_MISC"), 'TITLE' => GetMessage("BT_SALE_DISCOUNT_EDIT_TAB_TITLE_MISC")),
);

$control = new CAdminForm("sale_discount", $tabList);

$couponTypes = Internals\DiscountCouponTable::getCouponTypes(true);

$errors = array();
$boolCondParseError = false;
$boolActParseError = false;
$couponsAdd = false;

$discountID = 0;
$copy = false;
if (isset($_REQUEST['ID']))
{
	$discountID = (int)$_REQUEST['ID'];
	if ($discountID < 0)
		$discountID = 0;
}
if ($discountID > 0)
{
	$copy = (isset($_REQUEST['action']) && (string)$_REQUEST['action'] == 'copy');
}

$arFields = array();

if (
	check_bitrix_sessid()
	&& !$readOnly
	&& $_SERVER['REQUEST_METHOD'] == 'POST'
	&& isset($_POST['Update']) && (string)$_POST['Update'] == 'Y'
)
{
	$obCond3 = new CSaleCondTree();

	$boolCond = $obCond3->Init(BT_COND_MODE_PARSE, BT_COND_BUILD_SALE, array('INIT_CONTROLS' => array(
		'SITE_ID' => $_POST['LID'],
		'CURRENCY' => CSaleLang::GetLangCurrency($_POST['LID']),
	)));
	if (!$boolCond)
	{
		if ($ex = $APPLICATION->GetException())
			$errors[] = $ex->GetString();
		else
			$errors[] = (0 < $discountID ? str_replace('#ID#', $discountID, GetMessage('BT_SALE_DISCOUNT_EDIT_ERR_UPDATE')) : GetMessage('BT_SALE_DISCOUNT_EDIT_ERR_ADD'));
	}
	else
	{
		$boolCond = false;
		if (array_key_exists('CONDITIONS', $_POST) && array_key_exists('CONDITIONS_CHECK', $_POST))
		{
			if (is_string($_POST['CONDITIONS']) && is_string($_POST['CONDITIONS_CHECK']) && md5($_POST['CONDITIONS']) == $_POST['CONDITIONS_CHECK'])
			{
				$CONDITIONS = base64_decode($_POST['CONDITIONS']);
				if (CheckSerializedData($CONDITIONS))
				{
					$CONDITIONS = unserialize($CONDITIONS);
					$boolCond = true;
				}
				else
				{
					$boolCondParseError = true;
				}
			}
		}

		if (!$boolCond)
			$CONDITIONS = $obCond3->Parse();
		if (empty($CONDITIONS))
		{
			if ($ex = $APPLICATION->GetException())
				$errors[] = $ex->GetString();
			else
				$errors[] = (0 < $discountID ? str_replace('#ID#', $discountID, GetMessage('BT_SALE_DISCOUNT_EDIT_ERR_UPDATE')) : GetMessage('BT_SALE_DISCOUNT_EDIT_ERR_ADD'));
			$boolCondParseError = true;
		}
	}

	$obAct3 = new CSaleActionTree();

	$boolAct = $obAct3->Init(BT_COND_MODE_PARSE, BT_COND_BUILD_SALE_ACTIONS, array('PREFIX' => 'actrl', 'INIT_CONTROLS' => array(
		'SITE_ID' => $_POST['LID'],
		'CURRENCY' => CSaleLang::GetLangCurrency($_POST['LID']),
	)));
	if (!$boolAct)
	{
		if ($ex = $APPLICATION->GetException())
			$errors[] = $ex->GetString();
		else
			$errors[] = (0 < $discountID ? str_replace('#ID#', $discountID, GetMessage('BT_SALE_DISCOUNT_EDIT_ERR_UPDATE')) : GetMessage('BT_SALE_DISCOUNT_EDIT_ERR_ADD'));
	}
	else
	{
		$boolAct = false;
		if (array_key_exists('ACTIONS', $_POST) && array_key_exists('ACTIONS_CHECK', $_POST))
		{
			if (is_string($_POST['ACTIONS']) && is_string($_POST['ACTIONS_CHECK']) && md5($_POST['ACTIONS']) == $_POST['ACTIONS_CHECK'])
			{
				$ACTIONS = base64_decode($_POST['ACTIONS']);
				if (CheckSerializedData($ACTIONS))
				{
					$ACTIONS = unserialize($ACTIONS);
					$boolAct = true;
				}
				else
				{
					$boolActParseError = true;
				}
			}
		}

		if (!$boolAct)
			$ACTIONS = $obAct3->Parse();
		if (empty($ACTIONS))
		{
			if ($ex = $APPLICATION->GetException())
				$errors[] = $ex->GetString();
			else
				$errors[] = (0 < $discountID ? str_replace('#ID#', $discountID, GetMessage('BT_SALE_DISCOUNT_EDIT_ERR_UPDATE')) : GetMessage('BT_SALE_DISCOUNT_EDIT_ERR_ADD'));
			$boolActParseError = true;
		}
	}

	$arGroupID = array();
	if (array_key_exists('USER_GROUPS', $_POST) && is_array($_POST['USER_GROUPS']))
	{
		foreach ($_POST['USER_GROUPS'] as &$intValue)
		{
			$intValue = intval($intValue);
			if ($intValue > 0)
			{
				$arGroupID[] = $intValue;
			}
		}
		if (isset($intValue))
			unset($intValue);
	}

	$arFields = array(
		"LID" => (array_key_exists('LID', $_POST) ? $_POST['LID'] : ''),
		"NAME" => (array_key_exists('NAME', $_POST) ? $_POST['NAME'] : ''),
		"ACTIVE_FROM" => (array_key_exists('ACTIVE_FROM', $_POST) ? $_POST['ACTIVE_FROM'] : ''),
		"ACTIVE_TO" => (array_key_exists('ACTIVE_TO', $_POST) ? $_POST['ACTIVE_TO'] : ''),
		"ACTIVE" => (array_key_exists('ACTIVE', $_POST) && 'Y' == $_POST['ACTIVE'] ? 'Y' : 'N'),
		"SORT" => (array_key_exists('SORT', $_POST) ? $_POST['SORT'] : 500),
		"PRIORITY" => (array_key_exists('PRIORITY', $_POST) ? $_POST['PRIORITY'] : ''),
		"LAST_DISCOUNT" => (array_key_exists('LAST_DISCOUNT', $_POST) && 'N' == $_POST['LAST_DISCOUNT'] ? 'N' : 'Y'),
		"LAST_LEVEL_DISCOUNT" => (array_key_exists('LAST_LEVEL_DISCOUNT', $_POST) && 'N' == $_POST['LAST_LEVEL_DISCOUNT'] ? 'N' : 'Y'),
		"XML_ID" => (array_key_exists('XML_ID', $_POST) ? $_POST['XML_ID'] : ''),
		'CONDITIONS' => $CONDITIONS,
		'ACTIONS' => $ACTIONS,
		'USER_GROUPS' => $arGroupID,
	);

	if ($discountID == 0 || $copy)
	{
		$additionalFields = array(
			'COUPON_ADD' => (isset($_POST['COUPON_ADD']) && $_POST['COUPON_ADD'] == 'Y' ? 'Y' : 'N'),
			'COUPON_COUNT' => (isset($_POST['COUPON_COUNT']) ? (int)$_POST['COUPON_COUNT'] : 0)
		);
		if ($additionalFields['COUPON_ADD'] == 'Y')
		{
			if ($additionalFields['COUPON_COUNT'] <= 0)
			{
				$errors[] = GetMessage('BX_SALE_DISCOUNT_EDIT_ERR_COUPONS_COUNT');
			}
			$couponsFields = array(
				'DISCOUNT_ID' => $discountID,
				'ACTIVE' => 'Y'
			);
			if (isset($_POST['COUPON']))
			{
				$couponsFields['TYPE'] = (isset($_POST['COUPON']['TYPE']) ? (int)$_POST['COUPON']['TYPE'] : 0);
				$couponsFields['ACTIVE_FROM'] = (!empty($_POST['COUPON']['ACTIVE_FROM']) ? new Main\Type\DateTime($_POST['COUPON']['ACTIVE_FROM']) : null);
				$couponsFields['ACTIVE_TO'] = (!empty($_POST['COUPON']['ACTIVE_TO']) ? new Main\Type\DateTime($_POST['COUPON']['ACTIVE_TO']) : null);
				$couponsFields['MAX_USE'] = (isset($_POST['COUPON']['MAX_USE']) ? (int)$_POST['COUPON']['MAX_USE'] : 0);
			}
			$couponsResult = Internals\DiscountCouponTable::checkPacket($couponsFields, ($discountID <= 0));
			if (!$couponsResult->isSuccess(true))
			{
				$errors = (empty($errors) ? $couponsResult->getErrorMessages() : array_merge($errors, $couponsResult->getErrorMessages()));
			}
			unset($couponsResult);
			$additionalFields['COUPON'] = $couponsFields;
			$couponsAdd = true;
		}
	}

	if (empty($errors))
	{
		if ($discountID > 0 && !$copy)
		{
			$arFields['PRESET_ID'] = '';
			$arFields['PREDICTIONS'] = '';
			$arFields['PREDICTIONS_APP'] = '';
			if (!CSaleDiscount::Update($discountID, $arFields))
			{
				if ($ex = $APPLICATION->GetException())
					$errors[] = $ex->GetString();
				else
					$errors[] = str_replace('#ID#', $discountID, GetMessage('BT_SALE_DISCOUNT_EDIT_ERR_UPDATE'));
			}
		}
		else
		{
			unset($arFields['PRESET_ID'], $arFields['PREDICTIONS'], $arFields['PREDICTIONS_APP']);
			$discountID = (int)CSaleDiscount::Add($arFields);
			if ($discountID <= 0)
			{
				if ($ex = $APPLICATION->GetException())
					$errors[] = $ex->GetString();
				else
					$errors[] = GetMessage('BT_SALE_DISCOUNT_EDIT_ERR_ADD');
			}
			else
			{
				if ($couponsAdd)
				{
					$couponsFields['DISCOUNT_ID'] = $discountID;
					$couponsResult = Internals\DiscountCouponTable::addPacket(
						$couponsFields,
						$additionalFields['COUPON_COUNT']
					);
					if (!$couponsResult->isSuccess())
					{
						$errors = $couponsResult->getErrorMessages();
					}
				}
			}
		}
	}
	if (empty($errors))
	{
		if (empty($_POST['apply']))
			LocalRedirect("/bitrix/admin/sale_discount.php?lang=".LANGUAGE_ID.GetFilterParams("filter_", false));
		else
			LocalRedirect("/bitrix/admin/sale_discount_edit.php?lang=".LANGUAGE_ID."&ID=".$discountID.'&'.$control->ActiveTabParam());
	}
}

if ($discountID > 0 && !$copy)
	$APPLICATION->SetTitle(GetMessage('BT_SALE_DISCOUNT_EDIT_MESS_UPDATE_DISCOUNT', array('#ID#' => $discountID)));
else
	$APPLICATION->SetTitle(GetMessage('BT_SALE_DISCOUNT_EDIT_MESS_ADD_DISCOUNT'));

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

$defaultValues = array(
	'LID' => '',
	'NAME' => '',
	'CURRENCY' => '',
	'DISCOUNT_VALUE' => '',
	'DISCOUNT_TYPE' => 'P',
	'ACTIVE' => 'Y',
	'SORT' => '100',
	'ACTIVE_FROM' => '',
	'ACTIVE_TO' => '',
	'PRIORITY' => 1,
	'LAST_DISCOUNT' => 'Y',
	'LAST_LEVEL_DISCOUNT' => 'N',
	'CONDITIONS' => '',
	'XML_ID' => '',
	'ACTIONS' => '',
);
if (isset($_REQUEST['LID']))
	$defaultValues['LID'] = trim($_REQUEST['LID']);
if ('' == $defaultValues['LID'])
	$defaultValues['LID'] = 's1';

$defaultCoupons = array(
	'COUPON_ADD' => 'N',
	'COUPON_COUNT' => '',
	'COUPON' => array(
		'ACTIVE_FROM' => null,
		'ACTIVE_TO' => null,
		'TYPE' => Internals\DiscountCouponTable::TYPE_ONE_ORDER,
		'MAX_USE' => 0
	)
);

$arSelect = array_merge(array('ID'), array_keys($defaultValues));

$arDiscount = array();
$arDiscountGroupList = array();
$coupons = $defaultCoupons;

$rsDiscounts = CSaleDiscount::GetList(array(), array("ID" => $discountID), false, false, $arSelect);
if (!($arDiscount = $rsDiscounts->Fetch()))
{
	$discountID = 0;
	$arDiscount = $defaultValues;
}
else
{
	$rsDiscountGroups = CSaleDiscount::GetDiscountGroupList(array(),array('DISCOUNT_ID' => $discountID),false,false,array('GROUP_ID'));
	while ($arDiscountGroup = $rsDiscountGroups->Fetch())
	{
		$arDiscountGroupList[] = intval($arDiscountGroup['GROUP_ID']);
	}
}
if (!empty($errors))
{
	if ($boolCondParseError || $boolActParseError)
	{
		$mxTempo = $arDiscount['CONDITIONS'];
		$mxTempo2 = $arDiscount['ACTIONS'];
		$arDiscount = $arFields;
		if ($boolCondParseError)
			$arDiscount['CONDITIONS'] = $mxTempo;
		if ($boolActParseError)
			$arDiscount['ACTIONS'] = $mxTempo2;
		unset($mxTempo);
		unset($mxTempo2);
	}
	else
	{
		$arDiscount = $arFields;
	}
	$arDiscountGroupList = $arFields['USER_GROUPS'];
	if (isset($additionalFields))
		$coupons = array_merge($coupons, $additionalFields);
}

$contextMenuItems = array(
	array(
		"TEXT" => GetMessage("BT_SALE_DISCOUNT_EDIT_MESS_DISCOUNT_LIST"),
		"LINK" => "/bitrix/admin/sale_discount.php?lang=".LANGUAGE_ID.GetFilterParams("filter_"),
		"ICON" => "btn_list"
	)
);

if ($discountID > 0 && $saleModulePermissions == 'W')
{
	if (!$copy)
	{
		$contextMenuItems[] = array("SEPARATOR" => "Y");
		$contextMenuItems[] = array(
			"TEXT" => GetMessage("BT_SALE_DISCOUNT_EDIT_MESS_NEW_DISCOUNT"),
			"LINK" => "/bitrix/admin/sale_discount_edit.php?lang=".LANGUAGE_ID.GetFilterParams("filter_"),
			"ICON" => "btn_new"
		);
		$contextMenuItems[] = array(
			"TEXT"=>GetMessage("BT_SALE_DISCOUNT_EDIT_MESS_COPY_DISCOUNT"),
			"LINK"=>'/bitrix/admin/sale_discount_edit.php?lang='.LANGUAGE_ID.'&ID='.$discountID.'&action=copy&'.GetFilterParams('filter_'),
			"ICON"=>"btn_copy",
		);
		$contextMenuItems[] = array(
			"TEXT" => GetMessage("BT_SALE_DISCOUNT_EDIT_MESS_DELETE_DISCOUNT"),
			"LINK" => "javascript:if(confirm('".GetMessageJS("BT_SALE_DISCOUNT_EDIT_MESS_DELETE_DISCOUNT_CONFIRM")."')) window.location='/bitrix/admin/sale_discount.php?lang=".LANGUAGE_ID."&ID=".$discountID."&action=delete&".bitrix_sessid_get()."';",
			"WARNING" => "Y",
			"ICON" => "btn_delete"
		);
	}
}
$contextMenu = new CAdminContextMenu($contextMenuItems);
$contextMenu->Show();
unset($contextMenu, $contextMenuItems);

if (!empty($errors))
{
	$errorMessage = new CAdminMessage(
		array(
			"DETAILS" => implode('<br>', $errors),
			"TYPE" => "ERROR",
			"MESSAGE" => GetMessage("BT_SALE_DISCOUNT_EDIT_MESS_SAVE_ERROR"),
			"HTML" => true
		)
	);
	echo $errorMessage->Show();
	unset($errorMessage);
}

switch(LANGUAGE_ID)
{
	case 'en':
	case 'ru':
	case 'de':
		$hintLastDiscountImageName = 'hint_last_discount_' . LANGUAGE_ID .  '.png';
		break;
	default:
		$hintLastDiscountImageName = 'hint_last_discount_' . Main\Localization\Loc::getDefaultLang(LANGUAGE_ID) .  '.png';
		break;
}

$arSiteList = array();
$siteIterator = Main\SiteTable::getList(array(
	'select' => array('LID', 'NAME'),
	'order' => array('SORT' => 'ASC')
));
while ($site = $siteIterator->fetch())
{
	$arSiteList[$site['LID']] = '('.$site['LID'].') '.$site['NAME'];
}
unset($site, $siteIterator);

$control->BeginPrologContent();
CJSCore::Init(array('date'));
$control->EndPrologContent();

$control->BeginEpilogContent();
echo GetFilterHiddens("filter_");?>
<input type="hidden" name="Update" value="Y">
<input type="hidden" name="lang" value="<? echo LANGUAGE_ID; ?>">
<input type="hidden" name="ID" value="<? echo $discountID; ?>">
<?
if ($copy)
{
	?><input type="hidden" name="action" value="copy"><?
}
echo bitrix_sessid_post();
$control->EndEpilogContent();
$control->Begin(array(
	"FORM_ACTION" => '/bitrix/admin/sale_discount_edit.php?lang='.LANGUAGE_ID,
));
$control->BeginNextFormTab();
	if ($discountID > 0 && !$copy)
		$control->AddViewField('ID','ID:',$discountID,false);
	$control->AddCheckBoxField("ACTIVE", GetMessage("SDEN_ACTIVE").":", false, "Y", $arDiscount['ACTIVE'] == "Y");
	$control->AddDropDownField("LID", GetMessage('SDEN_SITE').':', true, $arSiteList, $arDiscount['LID']);
	$control->AddEditField("NAME", GetMessage("BT_SALE_DISCOUNT_EDIT_FIELDS_NAME").":", false, array("size" => 50, "maxlength" => 255), htmlspecialcharsbx($arDiscount['NAME']));
	$control->BeginCustomField("PERIOD", GetMessage('SDEN_PERIOD').":",false);
	?><tr id="tr_PERIOD">
		<td width="40%"><? echo $control->GetCustomLabelHTML(); ?></td>
		<td width="60%"><?
			$periodValue = '';
			if ('' != $arDiscount['ACTIVE_FROM'] || '' != $arDiscount['ACTIVE_TO'])
				$periodValue = CAdminCalendar::PERIOD_INTERVAL;

			echo CAdminCalendar::CalendarPeriodCustom(
				'ACTIVE_FROM',
				'ACTIVE_TO',
				$arDiscount['ACTIVE_FROM'],
				$arDiscount['ACTIVE_TO'],
				true,
				19,
				true,
				array(
					CAdminCalendar::PERIOD_EMPTY => GetMessage('BT_SALE_DISCOUNT_EDIT_CALENDARE_PERIOD_EMPTY'),
					CAdminCalendar::PERIOD_INTERVAL => GetMessage('BT_SALE_DISCOUNT_EDIT_CALENDARE_PERIOD_INTERVAL')
				),
				$periodValue
			);
		?></td>
	</tr><?
	$control->EndCustomField("PERIOD",
		'<input type="hidden" name="ACTIVE_FROM" value="'.htmlspecialcharsbx($arDiscount['ACTIVE_FROM']).'">'.
		'<input type="hidden" name="ACTIVE_TO" value="'.htmlspecialcharsbx($arDiscount['ACTIVE_FROM']).'">'
	);
	$control->BeginCustomField('PRIORITY', GetMessage("BT_SALE_DISCOUNT_EDIT_FIELDS_PRIORITY").':', false);
	?><tr id="tr_PRIORITY">
		<td width="40%"><? echo $control->GetCustomLabelHTML(); ?><br /><? echo GetMessage('BT_SALE_DISCOUNT_EDIT_FIELDS_PRIORITY_DESCR'); ?></td>
		<td width="60%">
			<input type="text" name="PRIORITY" size="20" maxlength="20" value="<? echo intval($arDiscount['PRIORITY']); ?>">
		</td>
	</tr><?
	$control->EndCustomField("PRIORITY",
		'<input type="hidden" name="PRIORITY" value="'.intval($arDiscount['PRIORITY']).'">'
	);
	$control->BeginCustomField('SORT', GetMessage("BT_SALE_DISCOUNT_EDIT_FIELDS_SORT_2").':', false);
	?><tr id="tr_SORT">
		<td width="40%"><? echo $control->GetCustomLabelHTML(); ?><br /><? echo GetMessage('BT_SALE_DISCOUNT_EDIT_FIELDS_SORT_DESCR'); ?></td>
		<td width="60%">
			<input type="text" name="SORT" size="20" maxlength="20" value="<? echo intval($arDiscount['SORT']); ?>">
		</td>
	</tr><?
	$control->EndCustomField("SORT",
		'<input type="hidden" name="SORT" value="'.intval($arDiscount['SORT']).'">'
	);
	$control->BeginCustomField("LAST_LEVEL_DISCOUNT", GetMessage('BT_SALE_DISCOUNT_EDIT_FIELDS_LAST_LEVEL_DISCOUNT').":",false);
	?><tr id="tr_LAST_LEVEL_DISCOUNT">
		<td width="40%">
			<script type="text/javascript">BX.ready(function(){BX.hint_replace(BX("tr_HELP_notice"), '<img style="padding-left: 16px;" width="545" height="353" src="/bitrix/images/sale/discount/<?= $hintLastDiscountImageName ?>" alt="">');})</script>
			<span id="tr_HELP_notice"></span>
			<? echo $control->GetCustomLabelHTML(); ?>
		</td>
		<td width="60%">
			<input type="hidden" value="N" name="LAST_LEVEL_DISCOUNT">
			<input type="checkbox" value="Y" name="LAST_LEVEL_DISCOUNT" <? echo ('Y' == $arDiscount['LAST_LEVEL_DISCOUNT']? 'checked' : '');?>>
		</td>
	</tr><?
	$control->EndCustomField("LAST_LEVEL_DISCOUNT",
		'<input type="hidden" name="LAST_LEVEL_DISCOUNT" value="'.htmlspecialcharsbx($arDiscount['LAST_LEVEL_DISCOUNT']).'">'
	);
	$control->BeginCustomField("LAST_DISCOUNT", GetMessage('BT_SALE_DISCOUNT_EDIT_FIELDS_LAST_DISCOUNT').":",false);
	?><tr id="tr_LAST_DISCOUNT">
		<td width="40%">
			<script type="text/javascript">BX.ready(function(){BX.hint_replace(BX("tr_HELP_notice2"), '<img style="padding-left: 16px;" width="545" height="353" src="/bitrix/images/sale/discount/<?= $hintLastDiscountImageName ?>" alt="">');})</script>
			<span id="tr_HELP_notice2"></span>
			<? echo $control->GetCustomLabelHTML(); ?>
		</td>
		<td width="60%">
			<input type="hidden" value="N" name="LAST_DISCOUNT">
			<input type="checkbox" value="Y" name="LAST_DISCOUNT" <? echo ('Y' == $arDiscount['LAST_DISCOUNT']? 'checked' : '');?>>
		</td>
	</tr><?
	$control->EndCustomField("LAST_DISCOUNT",
		'<input type="hidden" name="LAST_DISCOUNT" value="'.htmlspecialcharsbx($arDiscount['LAST_DISCOUNT']).'">'
	);
$control->BeginNextFormTab();
	$control->AddSection("BT_SALE_DISCOUNT_SECT_APP", GetMessage("BT_SALE_DISCOUNT_SECTIONS_APP"));
	$control->BeginCustomField("ACTIONS", GetMessage('BT_SALE_DISCOUNT_EDIT_FIELDS_APP').":",false);
	?><tr id="ACTIONS">
		<td valign="top" colspan="2"><div id="tree_actions" style="position: relative; z-index: 1;"></div><?
			if (!is_array($arDiscount['APPICATIONS']))
			{
				if (CheckSerializedData($arDiscount['APPICATIONS']))
				{
					$arDiscount['APPICATIONS'] = unserialize($arDiscount['APPICATIONS']);
				}
				else
				{
					$arDiscount['APPICATIONS'] = '';
				}
			}
			$arCondParams = array(
				'FORM_NAME' => 'sale_discount_form',
				'CONT_ID' => 'tree_actions',
				'JS_NAME' => 'JSSaleAct',
				'PREFIX' => 'actrl',
				'INIT_CONTROLS' => array(
					'SITE_ID' => $arDiscount['LID'],
					'CURRENCY' => CSaleLang::GetLangCurrency($arDiscount['LID']),
				),
				'SYSTEM_MESSAGES' => array(
					'SELECT_CONTROL' => GetMessage('BT_SALE_DISCOUNT_ACTIONS_SELECT_CONTROL'),
					'ADD_CONTROL' => GetMessage('BT_SALE_DISCOUNT_ACTIONS_ADD_CONTROL'),
					'DELETE_CONTROL' => GetMessage('BT_SALE_DISCOUNT_ACTIONS_DELETE_CONTROL'),
				),
			);
			$obAct = new CSaleActionTree();
			$boolAct = $obAct->Init(BT_COND_MODE_DEFAULT, BT_COND_BUILD_SALE_ACTIONS, $arCondParams);
			if (!$boolAct)
			{
				if ($ex = $APPLICATION->GetException())
					echo $ex->GetString()."<br>";
			}
			else
			{
				$obAct->Show($arDiscount['ACTIONS']);
			}
		?></td>
	</tr><?
	$strApp = base64_encode(serialize($arDiscount['ACTIONS']));

	$control->EndCustomField('ACTIONS',
		'<input type="hidden" name="ACTIONS" value="'.htmlspecialcharsbx($strApp).'">'.
		'<input type="hidden" name="ACTIONS_CHECK" value="'.htmlspecialcharsbx(md5($strApp)).'">'
	);
	$control->AddSection("BT_SALE_DISCOUNT_SECT_COND", GetMessage("BT_SALE_DISCOUNT_SECTIONS_COND_ADD"));
	$control->BeginCustomField("CONDITIONS", GetMessage('BT_SALE_DISCOUNT_EDIT_FIELDS_COND_ADD').":",false);
	?><tr id="tr_CONDITIONS">
		<td valign="top" colspan="2"><div id="tree" style="position: relative; z-index: 1;"></div><?
			if (!is_array($arDiscount['CONDITIONS']))
			{
				if (CheckSerializedData($arDiscount['CONDITIONS']))
				{
					$arDiscount['CONDITIONS'] = unserialize($arDiscount['CONDITIONS']);
				}
				else
				{
					$arDiscount['CONDITIONS'] = '';
				}
			}
			$arCondParams = array(
				'FORM_NAME' => 'sale_discount_form',
				'CONT_ID' => 'tree',
				'JS_NAME' => 'JSSaleCond',
				'INIT_CONTROLS' => array(
					'SITE_ID' => $arDiscount['LID'],
					'CURRENCY' => CSaleLang::GetLangCurrency($arDiscount['LID']),
				),
			);
			$obCond = new CSaleCondTree();
			$boolCond = $obCond->Init(BT_COND_MODE_DEFAULT, BT_COND_BUILD_SALE, $arCondParams);
			if (!$boolCond)
			{
				if ($ex = $APPLICATION->GetException())
					echo $ex->GetString()."<br>";
			}
			else
			{
				$obCond->Show($arDiscount['CONDITIONS']);
			}
		?></td>
	</tr><?
	$strCond = base64_encode(serialize($arDiscount['CONDITIONS']));
	$control->EndCustomField('CONDITIONS',
		'<input type="hidden" name="CONDITIONS" value="'.htmlspecialcharsbx($strCond).'">'.
		'<input type="hidden" name="CONDITIONS_CHECK" value="'.htmlspecialcharsbx(md5($strCond)).'">'
	);
$control->BeginNextFormTab();
	$strHidden = '';
	$control->BeginCustomField('USER_GROUPS', GetMessage('BT_SALE_DISCOUNT_EDIT_FIELDS_GROUPS').':', true);
	?><tr id="tr_USER_GROUPS" class="adm-detail-required-field">
		<td valign="top" width="40%"><? echo $control->GetCustomLabelHTML(); ?></td>
		<td valign="top" width="60%">
			<select name="USER_GROUPS[]" multiple size="8">
			<?
			$groupIterator = Main\GroupTable::getList(array(
				'select' => array('ID', 'NAME'),
				'order' => array('C_SORT' => 'ASC', 'ID' => 'ASC')
			));
			while ($group = $groupIterator->fetch())
			{
				$group['ID'] = (int)$group['ID'];
				$selected = (in_array($group['ID'], $arDiscountGroupList) ? ' selected' : '');
				?><option value="<? echo $group['ID']; ?>"<? echo $selected; ?>>[<? echo $group['ID']; ?>] <? echo htmlspecialcharsex($group['NAME']); ?></option><?
			}
			unset($selected, $group, $groupIterator);
			?>
			</select>
		</td>
	</tr><?
	if ($discountID > 0 && !empty($arDiscountGroupList))
	{
		$arHidden = array();
		foreach ($arDiscountGroupList as &$value)
		{
			if (0 < intval($value))
				$arHidden[] = '<input type="hidden" name="USER_GROUPS[]" value="'.intval($value).'">';
		}
		if (isset($value))
			unset($value);
		$strHidden = implode('',$arHidden);
	}
	if ($strHidden == '')
		$strHidden = '<input type="hidden" name="USER_GROUPS[]" value="">';
	$control->EndCustomField('USER_GROUPS', $strHidden);
$control->BeginNextFormTab();
	$control->BeginCustomField('COUPONS', GetMessage('BX_SALE_DISCOUNT_EDIT_FIELDS_COUPONS'), false);
	define('B_ADMIN_SUBCOUPONS', 1);
	define('B_ADMIN_SUBCOUPONS_LIST', false);
	$couponsReadOnly = $readOnly;
	$couponsAjaxPath = '/bitrix/tools/sale/discount_coupon_list.php?lang='.LANGUAGE_ID.'&find_discount_id='.$discountID;
	if ($discountID > 0 && !$copy)
	{
		?><tr id="tr_COUPONS"><td colspan="2"><?
		require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/sale/tools/discount_coupon_list.php');
		?></td></tr><?
	}
	else
	{
		?><tr id="tr_COUPON_ADD">
		<td width="40%"><? echo GetMessage('BX_SALE_DISCOUNT_EDIT_FIELDS_COUPON_ADD'); ?></td>
		<td width="60%">
			<input type="hidden" value="N" name="COUPON_ADD" id="COUPON_ADD_N">
			<input type="checkbox" value="Y" name="COUPON_ADD" id="COUPON_ADD_Y" <? echo ($coupons['COUPON_ADD'] == 'Y' ? 'checked' : ''); ?>>
		</td>
		</tr>
		<tr id="tr_COUPON_COUNT" class="adm-detail-required-field" style="display: <? echo ($coupons['COUPON_ADD'] == 'Y' ? 'table-row' : 'none'); ?>;">
			<td width="40%"><? echo GetMessage('BX_SALE_DISCOUNT_EDIT_FIELDS_COUPON_COUNT'); ?></td>
			<td width="60%"><input type="text" name="COUPON_COUNT" value="<? echo (int)$coupons['COUPON_COUNT']; ?>"></td>
		</tr>
		<tr id="tr_COUPON_PERIOD" style="display: <? echo ($coupons['COUPON_ADD'] == 'Y' ? 'table-row' : 'none'); ?>;">
		<td width="40%"><? echo GetMessage('BX_SALE_DISCOUNT_EDIT_FIELDS_COUPON_PERIOD'); ?></td>
		<td width="60%"><?
			$periodValue = '';
			$activeFrom = ($coupons['COUPON']['ACTIVE_FROM'] instanceof Main\Type\DateTime ? $coupons['COUPON']['ACTIVE_FROM']->toString() : '');
			$activeTo = ($coupons['COUPON']['ACTIVE_TO'] instanceof Main\Type\DateTime ? $coupons['COUPON']['ACTIVE_TO']->toString() : '');
			if ($activeFrom != '' || $activeTo != '')
				$periodValue = CAdminCalendar::PERIOD_INTERVAL;

			$calendar = new CAdminCalendar;
			echo $calendar->CalendarPeriodCustom(
				'COUPON[ACTIVE_FROM]', 'COUPON[ACTIVE_TO]',
				$activeFrom, $activeTo,
				true, 19, true,
				array(
					CAdminCalendar::PERIOD_EMPTY => GetMessage('BX_SALE_DISCOUNT_COUPON_PERIOD_EMPTY'),
					CAdminCalendar::PERIOD_INTERVAL => GetMessage('BX_SALE_DISCOUNT_COUPON_PERIOD_INTERVAL')
				),
				$periodValue
			);
			unset($calendar);
			?></td>
		</tr>
		<tr id="tr_COUPON_TYPE" class="adm-detail-required-field" style="display: <? echo ($coupons['COUPON_ADD'] == 'Y' ? 'table-row' : 'none'); ?>;">
			<td width="40%"><? echo GetMessage('BX_SALE_DISCOUNT_EDIT_FIELDS_COUPON_TYPE'); ?></td>
			<td width="60%">
				<select name="COUPON[TYPE]" size="3"><?
					foreach ($couponTypes as $type => $title)
					{
						?><option value="<? echo $type; ?>" <? echo ($type == $coupons['COUPON']['TYPE'] ? 'selected' : ''); ?>><? echo htmlspecialcharsex($title); ?></option><?
					}
					?></select>
			</td>
		</tr>
		<tr id="tr_COUPON_MAX_USE" style="display: <? echo ($coupons['COUPON_ADD'] == 'Y' ? 'table-row' : 'none'); ?>;">
			<td width="40%"><? echo GetMessage('BX_SALE_DISCOUNT_EDIT_FIELDS_COUPON_MAX_USE'); ?></td>
			<td width="60%"><input type="text" name="COUPON[MAX_USE]" value="<? echo ($coupons['COUPON_MAX_USE'] > 0 ? $coupons['COUPON_MAX_USE'] : ''); ?>"></td>
		</tr><?
	}
	$control->EndCustomField('COUPONS');
$control->BeginNextFormTab();
	$control->AddEditField("XML_ID", GetMessage("BT_SALE_DISCOUNT_EDIT_FIELDS_XML_ID").":", false, array("size" => 50, "maxlength" => 255), htmlspecialcharsbx($arDiscount['XML_ID']));

$control->Buttons(
	array(
		"disabled" => ($saleModulePermissions < "W"),
		"back_url" => "/bitrix/admin/sale_discount.php?lang=".LANGUAGE_ID.GetFilterParams("filter_")
	)
);
$control->Show();
?>
<script type="text/javascript">
	BX.ready(function(){
		var obCouponAdd = BX('COUPON_ADD_Y'),
			obCouponType = BX('tr_COUPON_TYPE'),
			obCouponCount = BX('tr_COUPON_COUNT'),
			obCouponPeriod = BX('tr_COUPON_PERIOD'),
			obCouponMaxUse = BX('tr_COUPON_MAX_USE');

		if (!!obCouponAdd && (!!obCouponType || !!obCouponCount || !!obCouponPeriod || !!obCouponMaxUse))
		{
			BX.bind(obCouponAdd, 'click', function(){
				if (!!obCouponType)
					BX.style(obCouponType, 'display', (obCouponAdd.checked ? 'table-row' : 'none'));
				if (!!obCouponCount)
					BX.style(obCouponCount, 'display', (obCouponAdd.checked ? 'table-row' : 'none'));
				if (!!obCouponPeriod)
					BX.style(obCouponPeriod, 'display', (obCouponAdd.checked ? 'table-row' : 'none'));
				if (!!obCouponMaxUse)
					BX.style(obCouponMaxUse, 'display', (obCouponAdd.checked ? 'table-row' : 'none'));
			});
			if (!!obCouponType)
				BX.style(obCouponType, 'display', (obCouponAdd.checked ? 'table-row' : 'none'));
			if (!!obCouponCount)
				BX.style(obCouponCount, 'display', (obCouponAdd.checked ? 'table-row' : 'none'));
			if (!!obCouponPeriod)
				BX.style(obCouponPeriod, 'display', (obCouponAdd.checked ? 'table-row' : 'none'));
			if (!!obCouponMaxUse)
				BX.style(obCouponMaxUse, 'display', (obCouponAdd.checked ? 'table-row' : 'none'));
		}
	});
</script>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");